<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Command;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
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
        symlink($projectRoot . '/src', $this->fixture . '/src');
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

    #[DataProvider('composerAvailability')]
    public function testDoctrineCommandsCheckInputs(bool $composerInstalled): void
    {
        if (!$composerInstalled) {
            unlink($this->fixture . '/vendor');
        }

        self::assertSame([2, '', "Usage: php scripts/migrate.php migrate|status\n"], $this->runCommand([
            'migrate.php', 'status', 'extra',
        ]));
        self::assertSame(
            [2, '', "Usage: php scripts/enroll-migrations.php --database=NAME --writers-stopped --evidence=FILE\n"],
            $this->runCommand(['enroll-migrations.php', '--database=fixture', '--evidence=evidence.json'])
        );
        self::assertSame(
            [1, '', "Enrollment failed: --database does not match DB_NAME. Select the intended database before retrying.\n"],
            $this->runCommand(
                ['enroll-migrations.php', '--database=fixture', '--writers-stopped', '--evidence=evidence.json'],
                ['DB_NAME' => 'other_fixture']
            )
        );
    }

    public static function composerAvailability(): array
    {
        return ['Composer installed' => [true], 'Composer missing' => [false]];
    }

    public function testMigrationUsesExportedDatabaseSelection(): void
    {
        file_put_contents($this->fixture . '/.env', "DB_HOST=127.0.0.1\nDB_NAME=fixture\nDB_USER=fixture\nDB_PASS=fixture\nDB_PORT=0\n");

        self::assertSame(
            [1, '', "Migration command failed: Set DB_HOST, DB_NAME, DB_USER and DB_PASS in the migration command environment.\n"],
            $this->runCommand(['migrate.php', 'status'])
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

    public function testPendingSweepHonorsTheSharedLock(): void
    {
        $pending = $this->fixture . '/uploads/students/pending';
        mkdir($pending . '/abandoned', 0700, true);
        $stale = $pending . '/abandoned/stale.pdf';
        touch($stale, time() - 7200);

        mkdir($this->fixture . '/storage');
        $lock = fopen($this->fixture . '/storage/.shared-jobs.lock', 'c');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        try {
            self::assertSame([0, '', ''], $this->runCommand(['run-shared-jobs.php', 'pending']));
            self::assertFileExists($stale);
        } finally {
            fclose($lock);
        }
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

    public function testMailQueueSubprocessEnforcesDailyLimitAcrossRuns(): void
    {
        $queue = $this->fixture . '/storage/mail-queue';
        mkdir($queue, 0700, true);
        file_put_contents($this->fixture . '/.env', "MAIL_DAILY_LIMIT=2\nMAIL_SEND_PAUSE_MS=0\n");

        foreach (['01-first.json', '02-second.json', '03-third.json'] as $idx => $filename) {
            file_put_contents($queue . '/' . $filename, json_encode([
                'to' => 'member' . ($idx + 1) . '@example.invalid',
                'subject' => 'Test ' . ($idx + 1),
                'body' => '<p>Corps</p>',
                'attempts' => 0,
                'next_attempt_at' => 0,
            ], JSON_THROW_ON_ERROR));
        }

        [$status1, $stdout1] = $this->runCommand(['process-mail-queue.php'], ['APP_ENV' => 'test']);
        self::assertSame(0, $status1);
        self::assertStringContainsString('Daily limit reached mid-run; stopping.', $stdout1);
        self::assertStringContainsString('delivered=2 (file=2, db=0)', $stdout1);
        self::assertStringContainsString('(sent today: 2/2)', $stdout1);
        self::assertFileDoesNotExist($queue . '/01-first.json');
        self::assertFileDoesNotExist($queue . '/02-second.json');
        self::assertFileExists($queue . '/03-third.json');

        $counterFile = $queue . '/.sent-' . gmdate('Ymd');
        self::assertFileExists($counterFile);
        self::assertSame('2', trim((string) file_get_contents($counterFile)));

        // Second invocation on same day defers immediately without sending 03-third.json
        [$status2, $stdout2] = $this->runCommand(['process-mail-queue.php'], ['APP_ENV' => 'test']);
        self::assertSame(0, $status2);
        self::assertStringContainsString('Daily limit reached (2/2); deferring remaining queue.', $stdout2);
        self::assertFileExists($queue . '/03-third.json');
    }

    public function testMailQueuePreservesMessagesWhenLegacyCounterIsInvalid(): void
    {
        $queue = $this->fixture . '/storage/mail-queue';
        mkdir($queue, 0700, true);
        $counter = $queue . '/.sent-' . gmdate('Ymd');
        file_put_contents($counter, 'unreadable count');
        file_put_contents($queue . '/mail.json', '{"to":"member@example.invalid"}');

        [$status, $stdout, $stderr] = $this->runCommand(['process-mail-queue.php'], ['APP_ENV' => 'test']);

        self::assertNotSame(0, $status);
        self::assertStringContainsString('Cannot read legacy daily mail usage.', $stderr);
        self::assertStringNotContainsString('Delivered to', $stdout);
        self::assertFileExists($queue . '/mail.json');
        self::assertSame('unreadable count', file_get_contents($counter));
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
