<?php

declare(strict_types=1);

namespace Amea\Tests\Unit\Core;

use Amea\Config\Database;
use Amea\Core\Runtime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RuntimeTest extends TestCase
{
    private string $tempDir;
    /** @var array<string, mixed> */
    private array $savedEnv = [];
    /** @var array<string, string|false> */
    private array $savedGetenv = [];

    private const MANAGED_KEYS = [
        'AMEA_CONFIG_FILE',
        'AMEA_STATE_DIR',
        'AMEA_SESSION_SAVE_PATH',
        'AMEA_PUBLIC_SESSION_PATH',
        'AMEA_STAFF_SESSION_PATH',
        'AMEA_TWIG_CACHE_DIR',
        'FILE_WRITE_GATE_PATH',
        'AMEA_MAIL_QUEUE_DIR',
        'AMEA_JOBS_LOCK_PATH',
        'AMEA_LOG_DIR',
        'AMEA_STORAGE_ROOT',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'APP_URL',
        'PUBLIC_APP_URL',
        'STAFF_APP_URL',
        'AMEA_TRASH_BACKUP_CONFIRMED',
        'TEST_RUNTIME_KEY',
    ];

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/amea-runtime-test-' . bin2hex(random_bytes(8));
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

    public function testLoadEnvReadsPrivateExternalConfigAndPreservesProcessOverrides(): void
    {
        $config = $this->tempDir . '/runtime.env';
        file_put_contents($config, implode("\n", [
            '# Comment line',
            'TEST_RUNTIME_KEY=from-config',
            'AMEA_TRASH_BACKUP_CONFIRMED=0',
            'PUBLIC_APP_URL=https://aeesgs.example',
            '',
        ]));
        chmod($config, 0600);

        putenv('AMEA_TRASH_BACKUP_CONFIRMED=1');

        $loaded = Runtime::loadEnv($this->tempDir, $config);

        self::assertSame($config, $loaded);
        self::assertSame('from-config', Runtime::env('TEST_RUNTIME_KEY'));
        self::assertSame('from-config', getenv('TEST_RUNTIME_KEY'));
        self::assertSame('1', Runtime::env('AMEA_TRASH_BACKUP_CONFIRMED'));
    }

    public function testLoadEnvRejectsWorldReadableExternalConfig(): void
    {
        $config = $this->tempDir . '/loose.env';
        file_put_contents($config, "TEST_RUNTIME_KEY=secret\n");
        chmod($config, 0644);

        $this->expectException(RuntimeException::class);
        Runtime::loadEnv($this->tempDir, $config);
    }

    public function testLoadEnvRejectsSymlinkExternalConfig(): void
    {
        $target = $this->tempDir . '/real.env';
        $link = $this->tempDir . '/link.env';
        file_put_contents($target, "TEST_RUNTIME_KEY=secret\n");
        chmod($target, 0600);
        symlink($target, $link);

        $this->expectException(RuntimeException::class);
        Runtime::loadEnv($this->tempDir, $link);
    }

    public function testStateDirIsolatesSessionsAndTwigCachesWhileSharingGateQueueAndJobsLock(): void
    {
        $stateDir = $this->tempDir . '/state';
        mkdir($stateDir, 0700);
        $_ENV['AMEA_STATE_DIR'] = $stateDir;

        $publicSessions = Runtime::sessionSavePath('public');
        $staffSessions = Runtime::sessionSavePath('staff');
        $jobsSessions = Runtime::sessionSavePath('jobs');

        self::assertSame($stateDir . '/sessions/public', $publicSessions);
        self::assertSame($stateDir . '/sessions/staff', $staffSessions);
        self::assertNull($jobsSessions);
        self::assertSame(0700, fileperms((string) $publicSessions) & 0777);
        self::assertSame(0700, fileperms((string) $staffSessions) & 0777);

        self::assertSame($stateDir . '/cache/twig/public', Runtime::twigCacheDir($this->tempDir, 'public'));
        self::assertSame($stateDir . '/cache/twig/staff', Runtime::twigCacheDir($this->tempDir, 'staff'));
        self::assertSame($stateDir . '/cache/app', Runtime::appCacheDir($this->tempDir));

        self::assertSame($stateDir . '/write-gate', Runtime::writeGateDir($this->tempDir));
        self::assertSame($stateDir . '/mail-queue', Runtime::mailQueueDir($this->tempDir));
        self::assertSame($stateDir . '/.shared-jobs.lock', Runtime::jobsLockPath($this->tempDir));
        self::assertSame($stateDir . '/logs', Runtime::logDir($this->tempDir));
        self::assertSame($stateDir, Runtime::storageProjectRoot($this->tempDir));
    }

    public function testDockerAndLocalDefaultsAreRetainedWhenNativePathsAreUnset(): void
    {
        self::assertSame('/var/lib/amea/sessions', Runtime::sessionSavePath('public'));
        self::assertSame('/var/lib/amea/sessions', Runtime::sessionSavePath('staff'));
        self::assertNull(Runtime::sessionSavePath('jobs'));
        self::assertSame($this->tempDir . '/storage/cache/twig', Runtime::twigCacheDir($this->tempDir, 'public'));
        self::assertSame($this->tempDir . '/storage/app-cache', Runtime::appCacheDir($this->tempDir));
        self::assertSame($this->tempDir . '/storage/write-gate', Runtime::writeGateDir($this->tempDir));
        self::assertSame($this->tempDir . '/storage/mail-queue', Runtime::mailQueueDir($this->tempDir));
        self::assertSame($this->tempDir . '/storage/.shared-jobs.lock', Runtime::jobsLockPath($this->tempDir));
        self::assertSame($this->tempDir . '/logs', Runtime::logDir($this->tempDir));
        self::assertSame($this->tempDir, Runtime::storageProjectRoot($this->tempDir));
    }

    public function testSessionSavePathRejectsTmpDirectory(): void
    {
        $_ENV['AMEA_PUBLIC_SESSION_PATH'] = '/tmp';

        $this->expectException(RuntimeException::class);
        Runtime::sessionSavePath('public');
    }

    public function testDatabaseDsnAndPortValidation(): void
    {
        self::assertNull(Database::parsePort(''));
        self::assertSame(3307, Database::parsePort('3307'));
        self::assertSame('mysql:host=127.0.0.1;dbname=amea;charset=utf8mb4', Database::buildDsn('127.0.0.1', 'amea', null));
        self::assertSame('mysql:host=127.0.0.1;port=3307;dbname=amea;charset=utf8mb4', Database::buildDsn('127.0.0.1', 'amea', 3307));

        $this->expectException(RuntimeException::class);
        Database::parsePort('70000');
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
