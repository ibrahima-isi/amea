<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Command;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CliCommandsTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $this->fixture = sys_get_temp_dir() . '/amea-cli-' . bin2hex(random_bytes(8));
        mkdir($this->fixture . '/scripts', 0700, true);
        symlink($projectRoot . '/vendor', $this->fixture . '/vendor');
        foreach (glob($projectRoot . '/scripts/*.php') as $script) {
            copy($script, $this->fixture . '/scripts/' . basename($script));
        }
    }

    protected function tearDown(): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->fixture, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->fixture);
    }

    public function testDoctrineCommandsRequireTheirExistingArguments(): void
    {
        self::assertSame([2, '', "Usage: php scripts/migrate.php migrate|status\n"], $this->runCommand([
            'migrate.php', 'status', 'extra',
        ]));
        self::assertSame(
            [2, '', "Usage: php scripts/enroll-migrations.php --database=NAME --writers-stopped --evidence=FILE\n"],
            $this->runCommand(['enroll-migrations.php', '--database=fixture', '--evidence=evidence.json'])
        );
    }

    public function testDoctrineCommandsUseExportedDatabaseSelection(): void
    {
        file_put_contents($this->fixture . '/.env', "DB_HOST=127.0.0.1\nDB_NAME=fixture\nDB_USER=fixture\nDB_PASS=fixture\nDB_PORT=0\n");

        self::assertSame(
            [1, '', "Migration command failed: Set DB_HOST, DB_NAME, DB_USER and DB_PASS in the migration command environment.\n"],
            $this->runCommand(['migrate.php', 'status'])
        );
        self::assertSame(
            [1, '', "Enrollment failed: --database does not match DB_NAME. Select the intended database before retrying.\n"],
            $this->runCommand(
                ['enroll-migrations.php', '--database=fixture', '--writers-stopped', '--evidence=evidence.json'],
                ['DB_NAME' => 'other_fixture']
            )
        );
    }

    public function testSharedTrashRequiresExportedBackupConfirmation(): void
    {
        file_put_contents($this->fixture . '/.env', "AMEA_TRASH_BACKUP_CONFIRMED=1\n");

        self::assertSame(
            [1, '', "Trash purge requires a verified backup and AMEA_TRASH_BACKUP_CONFIRMED=1.\n"],
            $this->runCommand(['run-shared-jobs.php', 'trash'], ['AMEA_TRASH_BACKUP_CONFIRMED' => '0'])
        );
    }

    public function testPendingSweepHonorsTheSharedLockAndLocalRetentionSetting(): void
    {
        $pending = $this->fixture . '/uploads/students/pending';
        mkdir($pending . '/abandoned', 0700, true);
        $stale = $pending . '/abandoned/stale.pdf';
        $recent = $pending . '/recent.pdf';
        touch($stale, time() - 7200);
        touch($recent);
        file_put_contents($this->fixture . '/.env', "# Fixture settings\nPENDING_UPLOAD_TTL_HOURS = 1\n");

        mkdir($this->fixture . '/storage');
        $lock = fopen($this->fixture . '/storage/.shared-jobs.lock', 'c');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        $command = ['run-shared-jobs.php', 'pending', 'ignored'];
        $environment = ['PENDING_UPLOAD_TTL_HOURS' => '24'];
        try {
            self::assertSame([0, '', ''], $this->runCommand($command, $environment));
            self::assertFileExists($stale);
        } finally {
            fclose($lock);
        }

        [$status, $stdout, $stderr] = $this->runCommand($command, $environment);
        self::assertSame(0, $status);
        self::assertSame('', $stderr);
        self::assertStringContainsString('Removed stale staged file: stale.pdf', $stdout);
        self::assertStringContainsString('Pending-uploads sweep complete: removed=1 (TTL 1h).', $stdout);
        self::assertFileDoesNotExist($stale);
        self::assertFileExists($recent);
    }

    public function testMailQueueRetriesAndDeadLettersFailedDelivery(): void
    {
        $queue = $this->fixture . '/storage/mail-queue';
        mkdir($queue, 0700, true);
        file_put_contents($this->fixture . '/.env', "MAIL_MAX_ATTEMPTS=2\n");
        // PHPMailer rejects these addresses before opening an SMTP connection.
        file_put_contents($queue . '/01-retry.json', '{"to":"invalid-first"}');
        file_put_contents($queue . '/02-failed.json', '{"to":"invalid-last","attempts":1}');

        $startedAt = time();
        [$status, $stdout] = $this->runCommand(
            ['process-mail-queue.php'],
            ['APP_ENV' => 'cli-failure-fixture', 'MAIL_MAX_ATTEMPTS' => '9']
        );

        self::assertSame(0, $status);
        self::assertStringContainsString('Retry 1/2 scheduled in 120s: invalid-first', $stdout);
        self::assertStringContainsString('Dead-lettered after 2 attempts: invalid-last (02-failed.json)', $stdout);
        $retry = json_decode(file_get_contents($queue . '/01-retry.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $retry['attempts']);
        self::assertGreaterThanOrEqual($startedAt + 120, $retry['next_attempt_at']);
        self::assertFileDoesNotExist($queue . '/02-failed.json');
        $failed = json_decode(file_get_contents($queue . '/failed/02-failed.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(2, $failed['attempts']);
        self::assertGreaterThanOrEqual($startedAt, $failed['failed_at']);
    }

    /** @param list<string> $arguments */
    private function runCommand(array $arguments, array $environment = []): array
    {
        $script = array_shift($arguments);
        $process = proc_open(
            [PHP_BINARY, $this->fixture . '/scripts/' . $script, ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->fixture,
            $environment
        );
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
