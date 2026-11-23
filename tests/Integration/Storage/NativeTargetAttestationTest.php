<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Storage;

use Amea\Core\FileWriteGate;
use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\Cutover;
use Amea\Storage\Migration\Manifest;
use Amea\Storage\Migration\ReferenceAudit;
use Amea\Storage\Migration\TargetAttestation;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../storage/migration-fixture.php';

class NativeTargetAttestationTest extends TestCase
{
    private string $tempDir;
    /** @var array<string, mixed> */
    private array $savedEnv = [];
    /** @var array<string, string|false> */
    private array $savedGetenv = [];

    private const MANAGED_KEYS = [
        'AMEA_APP',
        'AMEA_CONFIG_FILE',
        'AMEA_STATE_DIR',
        'STORAGE_DRIVER',
        'APP_ENV',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'PUBLIC_APP_URL',
        'STAFF_APP_URL',
        'APP_URL',
    ];

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/amea-native-attest-' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0700, true);
        $this->tempDir = (string) realpath($this->tempDir);

        foreach (self::MANAGED_KEYS as $key) {
            $this->savedEnv[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
            $this->savedGetenv[$key] = getenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::MANAGED_KEYS as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            if ($this->savedEnv[$key] !== null) {
                $_ENV[$key] = $this->savedEnv[$key];
            }
            if ($this->savedGetenv[$key] !== false) {
                putenv($key . '=' . $this->savedGetenv[$key]);
            } else {
                putenv($key);
            }
        }
        $this->removeTree($this->tempDir);
    }

    public function testNativeSplitTargetsVerifyDocrootsJobWrapperAndCutoverIdentity(): void
    {
        $root = (string) realpath(dirname(__DIR__, 3));
        $stateDir = $this->tempDir . '/state';
        $publicDocroot = $this->tempDir . '/public_html';
        $staffDocroot = $this->tempDir . '/staff_html';
        $binDir = $this->tempDir . '/bin';
        $configFile = $this->tempDir . '/runtime.env';

        foreach ([$stateDir, $stateDir . '/sessions', $stateDir . '/sessions/public', $stateDir . '/sessions/staff', $stateDir . '/write-gate', $publicDocroot, $staffDocroot, $binDir] as $dir) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($configFile, implode("\n", [
            'APP_ENV=test',
            'STORAGE_DRIVER=local',
            'PUBLIC_APP_URL=https://demo.aeesgs.example',
            'STAFF_APP_URL=https://bo.aeesgs.example',
            '',
        ]));
        chmod($configFile, 0600);

        $this->writeRenderedEntry($publicDocroot . '/index.php', $root, $configFile, $stateDir, 'public');
        $this->writeRenderedEntry($staffDocroot . '/index.php', $root, $configFile, $stateDir, 'staff');

        $currentLink = $this->tempDir . '/current';
        symlink($root, $currentLink);
        $jobWrapper = $binDir . '/run-job.php';
        file_put_contents($jobWrapper, implode("\n", [
            '<?php',
            'declare(strict_types=1);',
            "define('AMEA_APP', 'jobs');",
            "define('AMEA_CONFIG_FILE', '" . $configFile . "');",
            "define('AMEA_STATE_DIR', '" . $stateDir . "');",
            "require '" . $currentLink . "' . '/scripts/run-shared-jobs.php';",
            '',
        ]));
        chmod($jobWrapper, 0700);

        $gate = new FileWriteGate($stateDir . '/write-gate');
        $state = $gate->pause(['environment' => 'synthetic']);

        $db = makeTestDb();
        $reports = [
            TargetAttestation::report($root, [
                'role' => 'public',
                'config' => $configFile,
                'state_dir' => $stateDir,
                'docroot' => $publicDocroot,
            ], $db),
            TargetAttestation::report($root, [
                'role' => 'staff',
                'config' => $configFile,
                'state_dir' => $stateDir,
                'docroot' => $staffDocroot,
            ], $db),
            TargetAttestation::report($root, [
                'role' => 'jobs',
                'config' => $configFile,
                'state_dir' => $stateDir,
                'job_wrapper' => $jobWrapper,
            ], $db),
        ];

        self::assertSame('public', $reports[0]['role']);
        self::assertSame($stateDir . '/sessions/public', $reports[0]['session_path']);
        self::assertSame('staff', $reports[1]['role']);
        self::assertSame($stateDir . '/sessions/staff', $reports[1]['session_path']);
        self::assertSame('jobs', $reports[2]['role']);
        self::assertNull($reports[2]['session_path']);
        self::assertNull($reports[2]['session_store']);

        $backend = Manifest::backendIdentity(new LocalStorage($stateDir));
        $database = ReferenceAudit::databaseIdentity($db);
        $serializer = (string) ini_get('session.serialize_handler');
        $identity = Cutover::targetIdentity(
            $reports,
            $state,
            $backend,
            $database,
            $serializer,
            $gate,
            ['public' => $stateDir . '/sessions/public', 'staff' => $stateDir . '/sessions/staff']
        );
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $identity);
    }

    public function testNativeAttestationRejectsMismatchedDocrootRoleWiring(): void
    {
        $root = (string) realpath(dirname(__DIR__, 3));
        $stateDir = $this->tempDir . '/state';
        $publicDocroot = $this->tempDir . '/public_html';
        $configFile = $this->tempDir . '/runtime.env';

        foreach ([$stateDir, $stateDir . '/sessions', $stateDir . '/sessions/public', $stateDir . '/write-gate', $publicDocroot] as $dir) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($configFile, "APP_ENV=test\nSTORAGE_DRIVER=local\n");
        chmod($configFile, 0600);
        // Deliberately wire staff router inside public docroot
        $this->writeRenderedEntry($publicDocroot . '/index.php', $root, $configFile, $stateDir, 'staff');

        $gate = new FileWriteGate($stateDir . '/write-gate');
        $gate->pause(['environment' => 'synthetic']);

        $this->expectException(RuntimeException::class);
        TargetAttestation::report($root, [
            'role' => 'public',
            'config' => $configFile,
            'state_dir' => $stateDir,
            'docroot' => $publicDocroot,
        ], makeTestDb());
    }

    public function testNativeAttestationRequiresPausedWriteGate(): void
    {
        $root = (string) realpath(dirname(__DIR__, 3));
        $stateDir = $this->tempDir . '/state';
        $configFile = $this->tempDir . '/runtime.env';

        mkdir($stateDir . '/write-gate', 0700, true);
        file_put_contents($configFile, "APP_ENV=test\nSTORAGE_DRIVER=local\n");
        chmod($configFile, 0600);

        $this->expectException(RuntimeException::class);
        TargetAttestation::report($root, [
            'role' => 'jobs',
            'config' => $configFile,
            'state_dir' => $stateDir,
        ], makeTestDb());
    }

    private function writeRenderedEntry(string $path, string $root, string $config, string $stateDir, string $role): void
    {
        file_put_contents($path, implode("\n", [
            '<?php',
            'declare(strict_types=1);',
            "require_once '" . $root . "/vendor/autoload.php';",
            "define('AMEA_CONFIG_FILE', '" . $config . "');",
            "define('AMEA_STATE_DIR', '" . $stateDir . "');",
            "(new \\Amea\\Core\\Router())->dispatch('" . $role . "');",
            '',
        ]));
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
