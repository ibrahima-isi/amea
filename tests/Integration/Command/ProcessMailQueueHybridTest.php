<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Command;

use Amea\Command\ProcessMailQueueCommand;
use Amea\Core\View;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\MailDailyUsageRepository;
use Amea\Repository\SettingRepository;
use Amea\Service\CommunicationService;
use Amea\Service\EmailService;
use Amea\Service\MailBatchTransportInterface;
use Amea\Service\MailQuotaService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ProcessMailQueueHybridTest extends TestCase
{
    private string $tmpRoot;
    private PDO $db;
    /** @var array<string, string|null> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/amea-hybrid-worker-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot . '/storage/mail-queue', 0700, true);
        symlink(dirname(__DIR__, 3) . '/templates', $this->tmpRoot . '/templates');

        $this->db = makeTestDb();
        $this->db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('contact_email', 'contact@aeesgs.org')");

        $stmt = $this->db->prepare(
            'INSERT INTO students (last_name, first_name, gender, institution, status, email, consent_privacy, kyc_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(['Diallo', 'Awa', 'Féminin', 'UCAD', 'ETUDIANT', 'awa@example.invalid', 0, 'APPROVED']);
        $stmt->execute(['Sow', 'Ibrahima', 'Masculin', 'UGB', 'ETUDIANT', 'ibrahima@example.invalid', 1, 'APPROVED']);
        $stmt->execute(['Barry', 'Fatou', 'Féminin', 'ESP', 'ELEVE', 'fatou@example.invalid', 1, 'APPROVED']);

        foreach (['MAIL_DAILY_LIMIT', 'MAIL_BATCH_SIZE', 'MAIL_MAX_ATTEMPTS', 'MAIL_SEND_PAUSE_MS', 'PUBLIC_APP_URL', 'APP_ENV'] as $key) {
            $this->savedEnv[$key] = $_ENV[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
        if (is_dir($this->tmpRoot)) {
            $this->removeDir($this->tmpRoot);
        }
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDir($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * @param list<string> $failEmails
     */
    private function makeTransport(array $failEmails = [], ?\Closure $afterSend = null): object
    {
        return new class($failEmails, $afterSend) implements MailBatchTransportInterface {
            public int $sessions = 0;
            /** @var list<array<string, mixed>> */
            public array $sent = [];

            /** @param list<string> $failEmails */
            public function __construct(private array $failEmails, private ?\Closure $afterSend) {}

            public function open(): void
            {
                $this->sessions++;
            }

            public function send(
                string $to,
                string $subject,
                string $htmlBody,
                string $fromAddress,
                string $fromName,
                ?string $replyTo = null
            ): array {
                $this->sent[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $htmlBody,
                    'from' => $fromAddress,
                    'reply_to' => $replyTo,
                ];
                if ($this->afterSend !== null) {
                    ($this->afterSend)($to);
                }
                if (in_array($to, $this->failEmails, true)) {
                    return ['ok' => false, 'error' => 'SMTP 451 temporary error'];
                }
                return ['ok' => true, 'error' => null];
            }

            public function close(): void {}
        };
    }

    public function testFileMessagesRespectBothUtcQuotasAcrossMidnight(): void
    {
        $directory = $this->tmpRoot . '/storage/mail-queue';
        foreach (['a', 'b', 'c'] as $name) {
            file_put_contents($directory . '/' . $name . '.json', json_encode([
                'to' => $name . '@example.invalid', 'subject' => 'Test', 'body' => 'Test',
            ], JSON_THROW_ON_ERROR));
        }
        $_ENV['MAIL_DAILY_LIMIT'] = '1';
        $_ENV['MAIL_SEND_PAUSE_MS'] = '0';
        $now = strtotime('2100-01-01 23:59:59 UTC');
        $transport = $this->makeTransport(afterSend: static function (string $address) use (&$now): void {
            if ($address === 'a@example.invalid') {
                $now += 2;
            }
        });
        $worker = new ProcessMailQueueCommand($this->db, $transport, static function () use (&$now): int {
            return $now;
        });

        self::assertSame(0, $worker->run($this->tmpRoot));
        self::assertSame(['a@example.invalid', 'b@example.invalid'], array_column($transport->sent, 'to'));
        self::assertFileExists($directory . '/c.json');
        $usage = new MailDailyUsageRepository($this->db);
        self::assertSame(1, $usage->getUsage('2100-01-01'));
        self::assertSame(1, $usage->getUsage('2100-01-02'));
    }

    public function testWebReservationImportsLegacyUsageBeforeWorkerRuns(): void
    {
        $directory = $this->tmpRoot . '/storage/mail-queue';
        file_put_contents($directory . '/.sent-' . gmdate('Ymd'), '2');
        $usage = new MailDailyUsageRepository($this->db);
        $web = new MailQuotaService($usage, dailyLimit: 4, queueDir: $directory);
        self::assertTrue($web->tryReserveOneSlot());
        self::assertSame(3, $usage->getUsage(gmdate('Y-m-d')));

        $_ENV['MAIL_DAILY_LIMIT'] = '4';
        file_put_contents($directory . '/due.json', json_encode([
            'to' => 'test@example.invalid', 'subject' => 'Test', 'body' => 'Test'
        ]));
        $transport = $this->makeTransport();
        self::assertSame(0, (new ProcessMailQueueCommand($this->db, $transport))->run($this->tmpRoot));
        self::assertCount(1, $transport->sent);
        self::assertFalse($web->tryReserveOneSlot());
        self::assertSame(4, $usage->getUsage(gmdate('Y-m-d')));
    }

    public function testUnavailableConfiguredSchemaLeavesQueueIntact(): void
    {
        $file = $this->tmpRoot . '/storage/mail-queue/due.json';
        file_put_contents($file, '{}');
        $this->db->exec('DROP TABLE mail_daily_usage');
        $transport = $this->makeTransport();
        self::assertSame(1, (new ProcessMailQueueCommand($this->db, $transport))->run($this->tmpRoot));
        self::assertFileExists($file);
        self::assertSame(0, $transport->sessions);
    }

    public function testWorkerDrainsFileQueueAndDbCommunicationsSharingOneDailyLimit(): void
    {
        $_ENV['MAIL_DAILY_LIMIT'] = '2';
        $_ENV['MAIL_BATCH_SIZE'] = '10';
        $_ENV['MAIL_SEND_PAUSE_MS'] = '0';
        $_ENV['APP_ENV'] = 'production';

        // 1 transactional file-queued email
        file_put_contents(
            $this->tmpRoot . '/storage/mail-queue/20261127_000001_tx.json',
            json_encode([
                'to' => 'tx@example.invalid',
                'subject' => 'Inscription reçue',
                'body' => '<p>Bienvenue</p>',
                'attempts' => 0,
                'created_at' => time() - 10,
                'next_attempt_at' => time() - 10,
            ])
        );

        // 3 DB bulk communication recipients
        $commRepo = new CommunicationRepository($this->db);
        $usageRepo = new MailDailyUsageRepository($this->db);
        $quotaService = new MailQuotaService($usageRepo, $commRepo, 2);
        $view = new View(dirname(__DIR__, 3));
        $transport = $this->makeTransport();
        $commService = new CommunicationService(
            $commRepo,
            $quotaService,
            new SettingRepository($this->db),
            new EmailService('u', 'p', 'noreply@aeesgs.org', 'AEESGS', $view, $this->tmpRoot, $transport),
            $view,
            5,
            0
        );
        $comm = $commService->queueBulkCommunication('Annonce', 'Bonjour {{prenom}}', [], 1);

        $command = new ProcessMailQueueCommand($this->db, $transport);
        $exitCode = $command->run($this->tmpRoot);

        self::assertSame(0, $exitCode);
        // Daily limit is 2: 1 file-queue email + 1 DB recipient delivered, 2 DB recipients paused!
        self::assertCount(2, $transport->sent);
        self::assertSame('tx@example.invalid', $transport->sent[0]['to']);
        self::assertFileDoesNotExist($this->tmpRoot . '/storage/mail-queue/20261127_000001_tx.json');

        $updated = $commRepo->findById((int) $comm['id']);
        self::assertSame('PAUSED_LIMIT', $updated['status']);
        self::assertSame(1, $updated['sent_count']);
        self::assertSame(2, $updated['pending_count']);
        self::assertSame(2, $usageRepo->getUsage(gmdate('Y-m-d')));

        // Simulate next day (quota resets): worker resumes remaining 2 recipients cleanly without duplicates
        $this->db->exec('DELETE FROM mail_daily_usage');
        @unlink($this->tmpRoot . '/storage/mail-queue/.sent-' . gmdate('Ymd'));
        $exitCode2 = $command->run($this->tmpRoot);
        self::assertSame(0, $exitCode2);
        $completed = $commRepo->findById((int) $comm['id']);
        self::assertSame('COMPLETED', $completed['status']);
        self::assertSame(3, $completed['sent_count']);
        self::assertSame(0, $completed['pending_count']);
        $deliveredEmails = array_column($transport->sent, 'to');
        self::assertCount(4, $deliveredEmails);
        self::assertCount(4, array_unique($deliveredEmails));
    }

    public function testWorkerDeliversConsentReminderAndUpdatesStudentReminderTimestamp(): void
    {
        $_ENV['MAIL_DAILY_LIMIT'] = '50';
        $_ENV['MAIL_BATCH_SIZE'] = '10';
        $_ENV['MAIL_SEND_PAUSE_MS'] = '0';
        $_ENV['PUBLIC_APP_URL'] = 'https://aeesgs.org';
        $_ENV['APP_ENV'] = 'production';

        $commRepo = new CommunicationRepository($this->db);
        $quotaService = new MailQuotaService(new MailDailyUsageRepository($this->db), $commRepo, 50);
        $view = new View(dirname(__DIR__, 3));
        $transport = $this->makeTransport();
        $commService = new CommunicationService(
            $commRepo,
            $quotaService,
            new SettingRepository($this->db),
            new EmailService('u', 'p', 'noreply@aeesgs.org', 'AEESGS', $view, $this->tmpRoot, $transport),
            $view,
            5,
            0
        );

        $comm = $commService->queueConsentReminderCommunication(
            [['id' => 1, 'first_name' => 'Awa', 'last_name' => 'Diallo', 'email' => 'awa@example.invalid']],
            ['scope' => 'not_consented'],
            1
        );

        $command = new ProcessMailQueueCommand($this->db, $transport);
        $exitCode = $command->run($this->tmpRoot);

        self::assertSame(0, $exitCode);
        self::assertCount(1, $transport->sent);
        self::assertSame('awa@example.invalid', $transport->sent[0]['to']);
        self::assertStringContainsString('https://aeesgs.org/accept-cgu.php?token=', $transport->sent[0]['body']);

        $studentRow = $this->db->query('SELECT cgu_token, cgu_reminder_sent_at FROM students WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        self::assertNotEmpty($studentRow['cgu_token']);
        self::assertSame(64, strlen((string) $studentRow['cgu_token']));
        self::assertNotEmpty($studentRow['cgu_reminder_sent_at']);

        $updated = $commRepo->findById((int) $comm['id']);
        self::assertSame('COMPLETED', $updated['status']);
        self::assertSame(1, $updated['sent_count']);
    }

    public function testWorkerAppliesBackoffAndMarksDbRecipientFailedAtMaxAttemptsWithoutBurningQuota(): void
    {
        $_ENV['MAIL_DAILY_LIMIT'] = '50';
        $_ENV['MAIL_BATCH_SIZE'] = '10';
        $_ENV['MAIL_MAX_ATTEMPTS'] = '2';
        $_ENV['MAIL_SEND_PAUSE_MS'] = '0';
        $_ENV['APP_ENV'] = 'production';

        $commRepo = new CommunicationRepository($this->db);
        $usageRepo = new MailDailyUsageRepository($this->db);
        $quotaService = new MailQuotaService($usageRepo, $commRepo, 50);
        $view = new View(dirname(__DIR__, 3));
        $transport = $this->makeTransport(['awa@example.invalid']);
        $commService = new CommunicationService(
            $commRepo,
            $quotaService,
            new SettingRepository($this->db),
            new EmailService('u', 'p', 'noreply@aeesgs.org', 'AEESGS', $view, $this->tmpRoot, $transport),
            $view,
            2,
            0
        );

        $comm = $commService->queueBulkCommunication('Urgent', 'Message', ['institution' => 'UCAD'], 1);
        $command = new ProcessMailQueueCommand($this->db, $transport);

        // Run 1: transient failure -> retry scheduled with future next_attempt_at, daily quota stays 0
        self::assertSame(0, $command->run($this->tmpRoot));
        $recipientAfter1 = $this->db->query('SELECT status, attempts, next_attempt_at, last_error FROM communication_recipients LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('PENDING', $recipientAfter1['status']);
        self::assertSame(1, (int) $recipientAfter1['attempts']);
        self::assertGreaterThan(gmdate('Y-m-d H:i:s'), (string) $recipientAfter1['next_attempt_at']);
        self::assertStringContainsString('SMTP 451', (string) $recipientAfter1['last_error']);
        self::assertSame(0, $usageRepo->getUsage(gmdate('Y-m-d')));

        // Fast-forward backoff and run 2 -> reaches MAIL_MAX_ATTEMPTS=2 and transitions to FAILED / COMPLETED_WITH_ERRORS
        $this->db->exec("UPDATE communication_recipients SET next_attempt_at = '2020-01-01 00:00:00'");
        self::assertSame(0, $command->run($this->tmpRoot));
        $recipientAfter2 = $this->db->query('SELECT status, attempts FROM communication_recipients LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('FAILED', $recipientAfter2['status']);
        self::assertSame(2, (int) $recipientAfter2['attempts']);

        $updated = $commRepo->findById((int) $comm['id']);
        self::assertSame('COMPLETED_WITH_ERRORS', $updated['status']);
        self::assertSame(1, $updated['failed_count']);
        self::assertSame(0, $usageRepo->getUsage(gmdate('Y-m-d')));
    }
}
