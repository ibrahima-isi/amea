<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;

class LegacySessionHandoverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/amea-session-handover-' . bin2hex(random_bytes(8));
        mkdir($this->tempDir . '/state/sessions/public', 0700, true);
        mkdir($this->tempDir . '/state/sessions/staff', 0700, true);
        mkdir($this->tempDir . '/state/write-gate', 0700, true);
        $this->tempDir = (string) realpath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($this->tempDir);
    }

    public function testPublicEntryAdoptsLegacyPhpSessIdRotatesToAmeaPublicAndStripsNonPublicKeys(): void
    {
        $legacyId = 'legacyreg' . bin2hex(random_bytes(6));
        $publicSessions = $this->tempDir . '/state/sessions/public';
        $payload = [
            'input' => ['first_name' => 'Mariama'],
            'db_data' => ['identity_document' => 'uploads/students/pending/id.pdf'],
        ];
        file_put_contents(
            $publicSessions . '/sess_' . $legacyId,
            'last_activity|' . serialize(time())
            . 'csrf_token|' . serialize('kept-csrf')
            . 'user_id|' . serialize(7)
            . 'username|' . serialize('legacy-admin')
            . 'role|' . serialize('admin')
            . 'pending_registration|' . serialize($payload)
        );
        chmod($publicSessions . '/sess_' . $legacyId, 0600);

        $result = $this->runProbe('public', ['PHPSESSID' => $legacyId], false);

        self::assertSame('AMEA_PUBLIC', $result['session_name']);
        self::assertNotSame($legacyId, $result['session_id']);
        self::assertSame('Mariama', $result['pending_first_name']);
        self::assertSame('kept-csrf', $result['csrf_token']);
        self::assertSame(0, $result['user_id']);
        self::assertNull($result['username']);
        self::assertFileDoesNotExist($publicSessions . '/sess_' . $legacyId);
        self::assertFileExists($publicSessions . '/sess_' . $result['session_id']);
    }

    public function testPublicEntryRejectsUnknownLegacyPhpSessIdToPreventSessionFixation(): void
    {
        $forgedId = 'attackerfixedsession12345';

        $result = $this->runProbe('public', ['PHPSESSID' => $forgedId], false);

        self::assertSame('AMEA_PUBLIC', $result['session_name']);
        self::assertNotSame($forgedId, $result['session_id']);
        self::assertNull($result['pending_first_name']);
        self::assertFileDoesNotExist($this->tempDir . '/state/sessions/public/sess_' . $forgedId);
    }

    public function testStaffEntryIgnoresLegacyPhpSessIdCompletely(): void
    {
        $legacyId = 'legacystaff' . bin2hex(random_bytes(6));
        $staffSessions = $this->tempDir . '/state/sessions/staff';
        file_put_contents(
            $staffSessions . '/sess_' . $legacyId,
            'last_activity|' . serialize(time())
            . 'user_id|' . serialize(1)
            . 'role|' . serialize('admin')
            . 'session_version|' . serialize(1)
        );
        chmod($staffSessions . '/sess_' . $legacyId, 0600);

        $result = $this->runProbe('staff', ['PHPSESSID' => $legacyId], false);

        self::assertSame('AMEA_STAFF', $result['session_name']);
        self::assertNotSame($legacyId, $result['session_id']);
        self::assertSame(0, $result['user_id']);
    }

    public function testPublicPausedReadOnlyPreviewAdoptsLegacyPhpSessIdWithoutMutatingFile(): void
    {
        $legacyId = 'legacypaused' . bin2hex(random_bytes(6));
        $publicSessions = $this->tempDir . '/state/sessions/public';
        $bytes = 'last_activity|' . serialize(time())
            . 'pending_registration|' . serialize([
                'input' => ['first_name' => 'Oumar'],
                'db_data' => ['identity_document' => 'uploads/students/pending/id.pdf'],
            ]);
        file_put_contents($publicSessions . '/sess_' . $legacyId, $bytes);
        chmod($publicSessions . '/sess_' . $legacyId, 0600);

        $result = $this->runProbe('public', ['PHPSESSID' => $legacyId], true);

        self::assertTrue($result['allowed_paused_read']);
        self::assertSame($legacyId, $result['session_id']);
        self::assertSame('Oumar', $result['pending_first_name']);
        self::assertSame($bytes, file_get_contents($publicSessions . '/sess_' . $legacyId));
    }

    /**
     * @param array<string, string> $cookies
     * @return array<string, mixed>
     */
    private function runProbe(string $role, array $cookies, bool $pausedRead): array
    {
        $root = (string) realpath(dirname(__DIR__, 3));
        $stateDir = $this->tempDir . '/state';
        $script = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
$role = $argv[2];
$stateDir = $argv[3];
$cookies = json_decode($argv[4], true, 16, JSON_THROW_ON_ERROR);
$pausedRead = $argv[5] === '1';

define('AMEA_APP', $role);
define('AMEA_STATE_DIR', $stateDir);
$_COOKIE = $cookies;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $pausedRead ? '/registration-preview.php?type=identite' : '/registration-details.php';

$allowedPausedRead = false;
if ($pausedRead) {
    $allowedPausedRead = \Amea\Core\Session::allowPausedRead();
}

$session = new \Amea\Core\Session();
$session->start();

$pending = $session->get('pending_registration');
$result = [
    'allowed_paused_read' => $allowedPausedRead,
    'session_name' => session_name(),
    'session_id' => session_id(),
    'pending_first_name' => is_array($pending) ? ($pending['input']['first_name'] ?? null) : null,
    'csrf_token' => $session->get('csrf_token'),
    'user_id' => $session->userId(),
    'username' => $session->get('username'),
];
session_write_close();
echo json_encode($result, JSON_THROW_ON_ERROR);
PHP;

        $proc = proc_open(
            [PHP_BINARY, '-r', $script, $root, $role, $stateDir, json_encode($cookies, JSON_THROW_ON_ERROR), $pausedRead ? '1' : '0'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root
        );
        self::assertIsResource($proc);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        self::assertSame(0, $exit, 'Session probe failed: ' . $stderr);

        $decoded = json_decode($stdout, true);
        self::assertIsArray($decoded, 'Invalid probe output: ' . $stdout . ' / ' . $stderr);
        return $decoded;
    }
}
