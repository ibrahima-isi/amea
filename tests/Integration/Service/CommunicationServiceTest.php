<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Service;

use Amea\Core\View;
use Amea\Model\CommunicationType;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\MailDailyUsageRepository;
use Amea\Repository\SettingRepository;
use Amea\Service\CommunicationService;
use Amea\Service\EmailService;
use Amea\Service\MailBatchTransportInterface;
use Amea\Service\MailQuotaService;
use PDO;
use PHPUnit\Framework\TestCase;

final class CommunicationServiceTest extends TestCase
{
    private PDO $db;
    private View $view;
    private CommunicationRepository $commRepo;
    private SettingRepository $settingRepo;

    protected function setUp(): void
    {
        $this->db = makeTestDb();
        $this->view = new View(dirname(__DIR__, 3));
        $this->commRepo = new CommunicationRepository($this->db);
        $this->settingRepo = new SettingRepository($this->db);
        $this->db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('contact_email', 'bureau@aeesgs.org')");
        $this->seedMembers();
    }

    private function seedMembers(): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO students (last_name, first_name, gender, institution, status, email, consent_privacy, kyc_status, deleted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        // 1. Active approved ETUDIANT with consent
        $stmt->execute(['Diallo', 'Awa', 'Féminin', 'UCAD', 'ETUDIANT', 'awa@example.invalid', 1, 'APPROVED', null]);
        // 2. Active legacy STUDENT without consent (under review)
        $stmt->execute(['Bah', 'Mamadou <script>', 'Masculin', 'UCAD', 'STUDENT', 'mamadou@example.invalid', 0, 'UNDER_REVIEW', null]);
        // 3. Active legacy graduate Diplômé
        $stmt->execute(['Sow', 'Mariama', 'Féminin', 'UGB', 'Diplômé', 'mariama@example.invalid', 1, 'APPROVED', null]);
        // 4. Active GRADUATE
        $stmt->execute(['Barry', 'Ibrahima', 'Masculin', 'ESP', 'GRADUATE', 'ibrahima@example.invalid', 1, 'APPROVED', null]);
        // 5. Active REJECTED member
        $stmt->execute(['Camara', 'Oumar', 'Masculin', 'UCAD', 'ETUDIANT', 'rejected@example.invalid', 1, 'REJECTED', null]);
        // 6. Active member with empty email
        $stmt->execute(['Keita', 'Fatou', 'Féminin', 'UCAD', 'ELEVE', '', 1, 'APPROVED', null]);
        // 7. Active member with malformed email
        $stmt->execute(['Sylla', 'Alpha', 'Masculin', 'UCAD', 'ELEVE', 'not-an-email', 1, 'APPROVED', null]);
        // 8. Soft-deleted member (must never be counted or emailed)
        $stmt->execute(['Trashed', 'Ghost', 'Masculin', 'UCAD', 'ETUDIANT', 'ghost@example.invalid', 1, 'APPROVED', '2026-01-01 00:00:00']);
    }

    /**
     * @param list<string> $failEmails
     */
    private function makeRecordingTransport(array $failEmails = []): object
    {
        return new class($failEmails) implements MailBatchTransportInterface {
            public int $sessions = 0;
            /** @var list<array<string, mixed>> */
            public array $sent = [];

            /** @param list<string> $failEmails */
            public function __construct(private array $failEmails) {}

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
                if (in_array($to, $this->failEmails, true)) {
                    return ['ok' => false, 'error' => 'Temporary 421 defer'];
                }
                return ['ok' => true, 'error' => null];
            }

            public function close(): void {}
        };
    }

    private function makeService(
        MailBatchTransportInterface $transport,
        int $dailyLimit = 300,
        int $maxAttempts = 5,
        ?\Closure $clock = null
    ): CommunicationService
    {
        $emailService = new EmailService('user', 'pass', 'noreply@aeesgs.org', 'AEESGS', $this->view, null, $transport);
        $quotaService = new MailQuotaService(new MailDailyUsageRepository($this->db), $this->commRepo, $dailyLimit);
        return new CommunicationService($this->commRepo, $quotaService, $this->settingRepo, $emailService, $this->view, $maxAttempts, 0, $clock);
    }

    public function testAggregateStatsAndAudienceFilteringRespectEligibilityAndLegacyStatuses(): void
    {
        $transport = $this->makeRecordingTransport();
        $service = $this->makeService($transport);

        $stats = $service->getDashboardStats();
        self::assertSame(7, $stats['active_total']);
        self::assertSame(5, $stats['reachable_with_email']);
        self::assertSame(2, $stats['missing_email']);

        // Default KYC filter excludes REJECTED (so 4 reachable non-rejected members)
        self::assertSame(4, $service->countAudience([]));
        // Explicit KYC=all includes REJECTED too (5 reachable members)
        self::assertSame(5, $service->countAudience(['kyc_status' => 'all']));
        // Status ETUDIANT matches both ETUDIANT and legacy STUDENT (excluding rejected by default = 2)
        self::assertSame(2, $service->countAudience(['status' => 'ETUDIANT']));
        // Status Diplômé(e) matches both Diplômé and GRADUATE (= 2)
        self::assertSame(2, $service->countAudience(['status' => 'Diplômé(e)']));
        // Consent filter
        self::assertSame(1, $service->countAudience(['consent' => 'not_consented']));
        self::assertSame(3, $service->countAudience(['consent' => 'consented']));
        // Institution filter
        self::assertSame(1, $service->countAudience(['institution' => 'UGB']));
    }

    public function testQueueAndLiveChunkDispatchPersonalizesEscapesAndReusesSmtpSession(): void
    {
        $transport = $this->makeRecordingTransport();
        $service = $this->makeService($transport);

        $queued = $service->queueBulkCommunication(
            'Assemblée générale',
            "Bonjour {{prenom}} {{nom}},\n<b>Rendez-vous</b> samedi.",
            ['status' => 'ETUDIANT'],
            1
        );

        self::assertSame(2, $queued['total_recipients']);
        self::assertSame('QUEUED', $queued['status']);

        $result = $service->dispatchChunk($queued['id'], 25);

        self::assertTrue($result['done']);
        self::assertSame(2, $result['delivered']);
        self::assertSame(0, $result['failed']);
        self::assertSame('COMPLETED', $result['communication']['status']);
        self::assertSame(1, $transport->sessions, 'One SMTP session must serve the entire chunk');
        self::assertCount(2, $transport->sent);

        $mamadouMail = array_values(array_filter($transport->sent, fn($m) => $m['to'] === 'mamadou@example.invalid'))[0];
        self::assertSame('noreply@aeesgs.org', $mamadouMail['from']);
        self::assertSame('bureau@aeesgs.org', $mamadouMail['reply_to']);
        self::assertStringContainsString('Mamadou &lt;script&gt;', $mamadouMail['body']);
        self::assertStringNotContainsString('<script>', $mamadouMail['body']);
        self::assertStringContainsString('&lt;b&gt;Rendez-vous&lt;/b&gt;', $mamadouMail['body']);
        self::assertStringContainsString('<br>', $mamadouMail['body']);
        self::assertStringNotContainsString('&lt;br&gt;', $mamadouMail['body']);
    }

    public function testDailyLimitPausesRemainingRecipientsAndResumesCleanly(): void
    {
        $transport = $this->makeRecordingTransport();
        $service = $this->makeService($transport, dailyLimit: 2);

        $queued = $service->queueBulkCommunication('Info', 'Message test', [], 1);
        self::assertSame(4, $queued['total_recipients']);

        $firstChunk = $service->dispatchChunk($queued['id'], 25);
        self::assertSame(2, $firstChunk['delivered']);
        self::assertTrue($firstChunk['daily_limit_reached']);
        self::assertFalse($firstChunk['done']);
        self::assertSame('PAUSED_LIMIT', $firstChunk['communication']['status']);
        self::assertSame(2, $firstChunk['communication']['sent_count']);
        self::assertSame(2, $firstChunk['communication']['pending_count']);

        // Second attempt on same day sends nothing more
        $secondChunk = $service->dispatchChunk($queued['id'], 25);
        self::assertSame(0, $secondChunk['delivered']);
        self::assertTrue($secondChunk['daily_limit_reached']);
        self::assertCount(2, $transport->sent);

        // Simulate next day (quota resets): resume must deliver ONLY the remaining 2 recipients without duplicates
        $this->db->exec('DELETE FROM mail_daily_usage');
        $resumedChunk = $service->dispatchChunk($queued['id'], 25);
        self::assertSame(2, $resumedChunk['delivered']);
        self::assertFalse($resumedChunk['daily_limit_reached']);
        self::assertTrue($resumedChunk['done']);
        self::assertSame('COMPLETED', $resumedChunk['communication']['status']);
        self::assertSame(4, $resumedChunk['communication']['sent_count']);
        self::assertSame(0, $resumedChunk['communication']['pending_count']);

        $deliveredEmails = array_column($transport->sent, 'to');
        self::assertCount(4, $deliveredEmails);
        self::assertCount(4, array_unique($deliveredEmails), 'Resuming after pause must never duplicate already-sent recipients');
    }

    public function testDispatchReservesQuotaAndPersistsSentImmediatelyPerMessageInSingleSession(): void
    {
        $db = $this->db;
        $today = gmdate('Y-m-d');
        $transport = new class($db, $today) implements MailBatchTransportInterface {
            public int $sessions = 0;
            /** @var list<array{to: string, usage_before: int, sent_in_db_before: int}> */
            public array $observations = [];

            public function __construct(private PDO $db, private string $today) {}

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
                $stmtUsage = $this->db->prepare('SELECT sent_count FROM mail_daily_usage WHERE usage_date = ?');
                $stmtUsage->execute([$this->today]);
                $usage = (int) ($stmtUsage->fetchColumn() ?: 0);

                $sentInDb = (int) $this->db->query(
                    "SELECT COUNT(*) FROM communication_recipients WHERE status = 'SENT'"
                )->fetchColumn();

                $this->observations[] = [
                    'to' => $to,
                    'usage_before' => $usage,
                    'sent_in_db_before' => $sentInDb,
                ];

                // Fail the 2nd recipient to confirm its quota slot is released immediately while #1 stays SENT
                if (count($this->observations) === 2) {
                    return ['ok' => false, 'error' => 'Temporary 451 defer'];
                }

                return ['ok' => true, 'error' => null];
            }

            public function close(): void {}
        };

        $service = $this->makeService($transport, dailyLimit: 300, maxAttempts: 3);
        $queued = $service->queueBulkCommunication('Incremental', 'Corps {{prenom}}', [], 1);
        self::assertSame(4, $queued['total_recipients']);

        $res = $service->dispatchChunk((int) $queued['id'], 25);

        self::assertSame(1, $transport->sessions, 'All 4 recipients must share one persistent SMTP session');
        self::assertCount(4, $transport->observations);
        // Message #1: 1 slot reserved (not all 4!), 0 persisted SENT yet
        self::assertSame(1, $transport->observations[0]['usage_before']);
        self::assertSame(0, $transport->observations[0]['sent_in_db_before']);
        // Message #2: #1 is ALREADY persisted SENT in DB before #2 even finishes; usage is 2
        self::assertSame(2, $transport->observations[1]['usage_before']);
        self::assertSame(1, $transport->observations[1]['sent_in_db_before']);
        // Message #3: #2 failed and immediately released its slot, so usage for #3 is 2 (not 3!), sent_in_db is still 1
        self::assertSame(2, $transport->observations[2]['usage_before']);
        self::assertSame(1, $transport->observations[2]['sent_in_db_before']);
        // Message #4: #3 is ALREADY persisted SENT in DB; usage is 3, sent_in_db is 2
        self::assertSame(3, $transport->observations[3]['usage_before']);
        self::assertSame(2, $transport->observations[3]['sent_in_db_before']);

        self::assertSame(3, $res['delivered']);
        self::assertSame(1, $res['retried']);
        self::assertSame(3, $res['daily_sent']);
    }

    public function testFailureSchedulesBackoffAndMarksFailedAtMaxAttempts(): void
    {
        $transport = $this->makeRecordingTransport(['awa@example.invalid']);
        $service = $this->makeService($transport, dailyLimit: 300, maxAttempts: 2);

        $queued = $service->queueBulkCommunication('Alert', 'Test body', ['institution' => 'UCAD', 'consent' => 'consented'], 1);
        self::assertSame(1, $queued['total_recipients']);

        // Attempt 1 -> scheduled for retry (still PENDING with future next_attempt_at)
        $r1 = $service->dispatchChunk($queued['id'], 25);
        self::assertSame(0, $r1['delivered']);
        self::assertSame(1, $r1['retried']);
        self::assertSame(0, $r1['failed']);
        // Quota must not be burned on failed attempt
        self::assertSame(0, $r1['daily_sent']);

        // Fast-forward next_attempt_at so attempt 2 is due now
        $this->db->exec("UPDATE communication_recipients SET next_attempt_at = '2020-01-01 00:00:00'");

        $r2 = $service->dispatchChunk($queued['id'], 25);
        self::assertSame(0, $r2['delivered']);
        self::assertSame(1, $r2['failed']);
        self::assertSame('COMPLETED_WITH_ERRORS', $r2['communication']['status']);
    }

    public function testStaleSendingClaimIsRecoveredWhileFreshClaimIsNotDuplicated(): void
    {
        $transport = $this->makeRecordingTransport();
        $service = $this->makeService($transport);

        $queued = $service->queueBulkCommunication('Sync', 'Check', ['status' => 'ETUDIANT'], 1);
        $ids = $this->db->query('SELECT id FROM communication_recipients ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(2, $ids);

        // Recipient 1: stale SENDING claim (10 minutes ago) -> should be recovered & sent
        // Recipient 2: fresh SENDING claim (10 seconds ago) -> must NOT be double-sent
        $stale = gmdate('Y-m-d H:i:s', time() - 600);
        $fresh = gmdate('Y-m-d H:i:s', time() - 10);
        $this->db->prepare("UPDATE communication_recipients SET status = 'SENDING', claimed_at = ? WHERE id = ?")->execute([$stale, $ids[0]]);
        $this->db->prepare("UPDATE communication_recipients SET status = 'SENDING', claimed_at = ? WHERE id = ?")->execute([$fresh, $ids[1]]);

        // A live dispatcher still holds the batch lock even if its claims look stale.
        $directory = \Amea\Core\Runtime::mailQueueDir(dirname(__DIR__, 3));
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $lock = fopen($directory . '/.dispatch.lock', 'c');
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        try {
            self::assertSame(0, $service->dispatchChunk($queued['id'])['processed']);
            self::assertSame(0, $transport->sessions);
        } finally {
            fclose($lock);
        }

        $res = $service->dispatchChunk($queued['id'], 25);
        self::assertSame(1, $res['delivered']);
        self::assertCount(1, $transport->sent);
    }

    public function testPreviousClaimCannotAcknowledgeReclaimedRecipient(): void
    {
        $service = $this->makeService($this->makeRecordingTransport());
        $queued = $service->queueBulkCommunication('Claim', 'Check', [], 1);
        $previous = gmdate('Y-m-d H:i:s', time() + 1);
        $current = gmdate('Y-m-d H:i:s', time() + 601);
        $first = $this->commRepo->claimDueRecipients($queued['id'], 1, $previous, $previous);
        $second = $this->commRepo->claimDueRecipients($queued['id'], 1, $current, $previous);
        self::assertSame($first[0]['id'], $second[0]['id']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Recipient claim lost');
        $this->commRepo->markRecipientSent((int) $first[0]['id'], $previous, $current);
    }

    public function testSelfTestSendsPersonalizedPreviewWithoutPersistingCommunicationRows(): void
    {
        $transport = $this->makeRecordingTransport();
        $service = $this->makeService($transport);

        $ok = $service->sendSelfTest('admin@aeesgs.org', 'Aissatou', 'Barry', 'Invitation', "Salut {{prenom}} {{nom}}!\nOn se retrouve.");
        self::assertTrue($ok['ok']);
        self::assertCount(1, $transport->sent);
        self::assertSame('[Test] Invitation', $transport->sent[0]['subject']);
        self::assertStringContainsString('Salut Aissatou Barry!', $transport->sent[0]['body']);
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM communications')->fetchColumn());
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM communication_recipients')->fetchColumn());

        $bad = $service->sendSelfTest('not-an-email', 'Admin', 'User', 'Invitation', 'Corps');
        self::assertFalse($bad['ok']);
        self::assertStringContainsString('valide', (string) $bad['error']);
    }

    public function testHistoryFailureInspectionRetryFailedAndCancelRemainingPreserveSentRecipients(): void
    {
        $failingTransport = $this->makeRecordingTransport(['mamadou@example.invalid']);
        $service = $this->makeService($failingTransport, dailyLimit: 300, maxAttempts: 1);

        $queued = $service->queueBulkCommunication('Réunion', 'Ordre du jour', ['status' => 'ETUDIANT'], 1);
        $first = $service->dispatchChunk($queued['id'], 25);
        self::assertSame(1, $first['delivered']);
        self::assertSame(1, $first['failed']);
        self::assertSame('COMPLETED_WITH_ERRORS', $first['communication']['status']);

        $history = $service->getRecentHistoryWithFailures(CommunicationType::BULK, 15, 0);
        self::assertCount(1, $history);
        self::assertSame('Réunion', $history[0]['subject']);
        self::assertSame('Test SA', $history[0]['author_name']);
        self::assertCount(1, $history[0]['failures']);
        self::assertSame('mamadou@example.invalid', $history[0]['failures'][0]['email']);

        $failures = $service->getFailedRecipients($queued['id']);
        self::assertCount(1, $failures);
        self::assertSame('mamadou@example.invalid', $failures[0]['email']);
        self::assertStringContainsString('Temporary 421 defer', (string) $failures[0]['last_error']);

        // Retry failed with a healthy transport -> must ONLY re-send to Mamadou, never duplicate Awa!
        $healthyTransport = $this->makeRecordingTransport();
        $healthyService = $this->makeService($healthyTransport, dailyLimit: 300, maxAttempts: 1);
        $retriedCount = $healthyService->retryFailed($queued['id']);
        self::assertSame(1, $retriedCount);

        $afterRetry = $healthyService->dispatchChunk($queued['id'], 25);
        self::assertSame(1, $afterRetry['delivered']);
        self::assertSame('COMPLETED', $afterRetry['communication']['status']);
        self::assertCount(1, $healthyTransport->sent);
        self::assertSame('mamadou@example.invalid', $healthyTransport->sent[0]['to']);

        // Cancel remaining on a partially sent communication
        $secondComm = $healthyService->queueBulkCommunication('Annonce 2', 'Contenu', [], 1);
        $partial = $healthyService->dispatchChunk($secondComm['id'], 1);
        self::assertSame(1, $partial['delivered']);
        $cancelled = $healthyService->cancelRemaining($secondComm['id']);
        self::assertSame(3, $cancelled);

        $updated = $healthyService->getCommunication($secondComm['id']);
        self::assertSame('CANCELLED', $updated['status']);
        self::assertSame(1, $updated['sent_count']);
        self::assertSame(3, $updated['cancelled_count']);
        self::assertSame(0, $updated['pending_count']);
    }

    public function testCountParityWithEdgeCaseEmailsAndZeroRecipientCreatesNoGhostHistory(): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO students (last_name, first_name, gender, institution, status, email, consent_privacy, kyc_status, deleted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)'
        );
        // Invalid syntax that passes naive SQL LIKE but fails FILTER_VALIDATE_EMAIL
        $stmt->execute(['BadAt', 'Double', 'Masculin', 'EDGE', 'ETUDIANT', 'a@@b.co', 1, 'APPROVED']);
        foreach (['a.@b.co', 'a@-bad.co', 'a@bad-.co', 'a<z>@b.co', 'a@b.123', "a@b.co\n"] as $invalid) {
            $stmt->execute(['Malformed', 'Address', 'Masculin', 'EDGE', 'ETUDIANT', $invalid, 1, 'APPROVED']);
        }
        // Valid email with surrounding whitespace
        $stmt->execute(['Trimmed', 'Space', 'Féminin', 'EDGE', 'ETUDIANT', '  trimmed@example.invalid  ', 1, 'APPROVED']);

        $transport = $this->makeRecordingTransport();
        $service = $this->makeService($transport);

        self::assertSame(6, $service->getDashboardStats()['reachable_with_email']);
        self::assertSame(1, $service->countAudience(['institution' => 'EDGE']));
        $queued = $service->queueBulkCommunication('Edge', 'Corps', ['institution' => 'EDGE'], 1);
        self::assertSame(1, $queued['total_recipients']);

        // Zero-recipient audience must not persist a ghost history row
        $beforeCount = (int) $this->db->query('SELECT COUNT(*) FROM communications')->fetchColumn();
        $empty = $service->queueBulkCommunication('Empty', 'Nobody', ['institution' => 'NONEXISTENT'], 1);
        self::assertSame(0, $empty['total_recipients']);
        self::assertSame(0, $empty['id']);
        self::assertSame($beforeCount, (int) $this->db->query('SELECT COUNT(*) FROM communications')->fetchColumn());
    }

    public function testReservationsAndFailureReleaseUseTheUtcDayOfEachSend(): void
    {
        $transport = new class implements MailBatchTransportInterface {
            public int $time;
            public int $sent = 0;

            public function open(): void {}
            public function close(): void {}

            public function send(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, ?string $replyTo = null): array
            {
                $this->sent++;
                if ($this->sent === 1) {
                    $this->time += 2;
                    return ['ok' => false, 'error' => 'SMTP temporarily unavailable'];
                }
                return ['ok' => true, 'error' => null];
            }
        };
        $transport->time = strtotime('2100-01-01 23:59:59 UTC');
        $service = $this->makeService($transport, dailyLimit: 1, clock: static fn (): int => $transport->time);
        $campaign = $service->queueBulkCommunication('Annonce', 'Message', [], 1);
        $result = $service->dispatchChunk($campaign['id']);

        self::assertSame(2, $transport->sent);
        self::assertSame(1, $result['retried']);
        self::assertSame(1, $result['delivered']);
        self::assertSame(0, $service->getDashboardStats('2100-01-01')['daily_sent']);
        self::assertSame(1, $service->getDashboardStats('2100-01-02')['daily_sent']);
    }

    public function testReminderOutcomeRollsBackWhenAcknowledgementCannotBeRecorded(): void
    {
        $transport = $this->makeRecordingTransport();
        $service = $this->makeService($transport);
        $campaign = $service->queueConsentReminderCommunication([
            ['id' => 1, 'first_name' => 'Awa', 'last_name' => 'Diallo', 'email' => 'awa@example.invalid'],
        ], [], 1);

        try {
            $service->dispatchChunk(
                $campaign['id'],
                prepareConsentLink: static fn (): array => [
                    'url' => 'https://public.example.invalid/accept-cgu.php', 'token' => str_repeat('a', 64), 'previous_token' => null,
                ],
                onConsentDelivered: static function (): never {
                    throw new \RuntimeException('Could not record reminder timestamp');
                }
            );
            self::fail('Expected the acknowledgement write to fail.');
        } catch (\RuntimeException $error) {
            self::assertSame('Could not record reminder timestamp', $error->getMessage());
        }

        $summary = $service->getCommunication($campaign['id']);
        self::assertSame(0, $summary['sent_count']);
        self::assertSame(1, $summary['sending_count']);
        self::assertCount(1, $transport->sent);
    }

    public function testChangedAudienceRequiresNewConfirmation(): void
    {
        $service = $this->makeService($this->makeRecordingTransport());
        $filters = ['institution' => 'UCAD'];
        $confirmedCount = $service->countAudience($filters);
        $this->db->exec("UPDATE students SET kyc_status = 'APPROVED' WHERE email = 'rejected@example.invalid'");

        $result = $service->queueBulkCommunication('Annonce', 'Message', $filters, 1, $confirmedCount);
        self::assertSame('AUDIENCE_CHANGED', $result['status']);
        self::assertSame([], $service->getRecentHistoryWithFailures());
    }

    public function testFailedOutcomeAndQuotaRollbackTogetherWhenSummaryCannotBeRecorded(): void
    {
        $service = $this->makeService($this->makeRecordingTransport(['mariama@example.invalid']), maxAttempts: 1);
        $campaign = $service->queueBulkCommunication('Annonce', 'Message', ['institution' => 'UGB'], 1);
        $this->db->exec("CREATE TRIGGER fail_summary BEFORE UPDATE OF failed_count ON communications
            WHEN NEW.failed_count > 0 BEGIN SELECT RAISE(ABORT, 'Cannot write failure summary'); END");

        try {
            $service->dispatchChunk($campaign['id']);
            self::fail('Expected the summary write to fail.');
        } catch (\PDOException $error) {
            self::assertStringContainsString('Cannot write failure summary', $error->getMessage());
        }

        $summary = $service->getCommunication($campaign['id']);
        self::assertSame(0, $summary['failed_count']);
        self::assertSame(1, $summary['sending_count']);
        self::assertSame(1, $service->getDashboardStats()['daily_sent']);
    }

    public function testStaleCancellationDoesNotChangeCompletedCampaign(): void
    {
        $service = $this->makeService($this->makeRecordingTransport());
        $campaign = $service->queueBulkCommunication('Annonce', 'Message', ['institution' => 'UGB'], 1);
        self::assertSame(1, $service->dispatchChunk($campaign['id'])['delivered']);

        self::assertSame(0, $service->cancelRemaining($campaign['id']));
        self::assertSame('COMPLETED', $service->getCommunication($campaign['id'])['status']);
    }

    public function testAbandonedClaimsAreCancelledAfterStaffCancelsTheCampaign(): void
    {
        $transport = $this->makeRecordingTransport();
        $service = $this->makeService($transport);
        $queued = $service->queueBulkCommunication('Annonce', 'Message', ['institution' => 'UGB'], 1);
        $this->db->prepare("UPDATE communication_recipients SET status = 'SENDING', claimed_at = ? WHERE communication_id = ?")
            ->execute([gmdate('Y-m-d H:i:s', time() - 600), $queued['id']]);

        $service->cancelRemaining($queued['id']);
        $service->dispatchChunk();

        $campaign = $service->getCommunication($queued['id']);
        self::assertSame('CANCELLED', $campaign['status']);
        self::assertSame(1, $campaign['cancelled_count']);
        self::assertSame(0, $campaign['pending_count']);
        self::assertSame([], $transport->sent);
    }

    public function testQuotaReleaseDoesNotRequeueCancelledRecipients(): void
    {
        $transport = $this->makeRecordingTransport(['awa@example.invalid']);
        $service = $this->makeService($transport, dailyLimit: 1, maxAttempts: 1);
        $campaign = $service->queueBulkCommunication('Annonce', 'Message', ['institution' => 'UCAD'], 1);
        self::assertSame(1, $service->dispatchChunk($campaign['id'], 1)['failed']);
        $quota = new MailQuotaService(new MailDailyUsageRepository($this->db), dailyLimit: 1);
        self::assertTrue($quota->tryReserveOneSlot());
        $claimedAt = gmdate('Y-m-d H:i:s');
        $claimed = $this->commRepo->claimDueRecipients($campaign['id'], 1, $claimedAt, '2000-01-01 00:00:00');
        self::assertFalse($quota->tryReserveOneSlot());

        $service->cancelRemaining($campaign['id']);
        $this->commRepo->releaseClaimedRecipients($claimed, $claimedAt, $claimedAt);

        self::assertSame(1, $service->getCommunication($campaign['id'])['cancelled_count']);
        self::assertSame(1, $service->retryFailed($campaign['id']));
        $quota->releaseOneSlot();
        $service->dispatchChunk($campaign['id']);
        self::assertSame(['awa@example.invalid', 'awa@example.invalid'], array_column($transport->sent, 'to'));
    }

    public function testRetryCannotReopenCancelledCampaignWithAbandonedClaims(): void
    {
        $transport = $this->makeRecordingTransport(['awa@example.invalid']);
        $service = $this->makeService($transport, maxAttempts: 1);
        $queued = $service->queueBulkCommunication('Annonce', 'Message', ['institution' => 'UCAD'], 1);
        self::assertSame(1, $service->dispatchChunk($queued['id'], 1)['failed']);
        $this->db->prepare("UPDATE communication_recipients SET status = 'SENDING', claimed_at = ?
            WHERE communication_id = ? AND status = 'PENDING'")
            ->execute([gmdate('Y-m-d H:i:s', time() - 600), $queued['id']]);

        $service->cancelRemaining($queued['id']);
        self::assertSame(0, $service->retryFailed($queued['id']));
        $service->dispatchChunk();
        self::assertSame(1, $service->getCommunication($queued['id'])['cancelled_count']);
        self::assertSame(1, $service->retryFailed($queued['id']));
        $service->dispatchChunk();
        self::assertSame(['awa@example.invalid', 'awa@example.invalid'], array_column($transport->sent, 'to'));
    }

    public function testCancelDuringInFlightFailureDoesNotResurrectCancelledCommunication(): void
    {
        // Trigger cancellation while a recipient fails inside dispatchChunk
        $commRepo = $this->commRepo;
        $transport = new class($commRepo) implements MailBatchTransportInterface {
            public int $communicationId = 0;

            public function __construct(private CommunicationRepository $commRepo) {}
            public function open(): void {}
            public function send(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, ?string $replyTo = null): array
            {
                // Simulate concurrent admin cancellation mid-send
                $this->commRepo->markPendingRecipientsCancelled($this->communicationId);
                return ['ok' => false, 'error' => 'Transient SMTP disconnect'];
            }
            public function close(): void {}
        };

        $service = $this->makeService($transport, dailyLimit: 300, maxAttempts: 5);
        $queued = $service->queueBulkCommunication('Race', 'Body', ['institution' => 'UGB'], 1);
        $transport->communicationId = (int) $queued['id'];

        $res = $service->dispatchChunk((int) $queued['id'], 25);
        self::assertSame('CANCELLED', $res['communication']['status']);
        self::assertSame(0, $res['communication']['pending_count']);
        self::assertSame(1, $res['communication']['cancelled_count']);
    }
}
