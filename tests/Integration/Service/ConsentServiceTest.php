<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Service;

use Amea\Core\View;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\ConsentRepository;
use Amea\Repository\MailDailyUsageRepository;
use Amea\Repository\SettingRepository;
use Amea\Repository\StudentRepository;
use Amea\Service\CommunicationService;
use Amea\Service\ConsentService;
use Amea\Service\EmailService;
use Amea\Service\MailBatchTransportInterface;
use Amea\Service\MailQuotaService;
use Amea\Service\UserService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConsentServiceTest extends TestCase
{
    private PDO $db;
    private View $view;
    private ?string $savedPublicUrl = null;

    protected function setUp(): void
    {
        $this->db = makeTestDb();
        $this->view = new View(dirname(__DIR__, 3));
        $this->db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('contact_email', 'contact@aeesgs.org')");
        $this->savedPublicUrl = $_ENV['PUBLIC_APP_URL'] ?? null;
        $_ENV['PUBLIC_APP_URL'] = 'https://aeesgs.org';

        $stmt = $this->db->prepare(
            'INSERT INTO students (last_name, first_name, gender, institution, status, email, consent_privacy, kyc_status, review_token, cgu_token, deletion_requested_at, deleted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        // 1: pending consent with email & review_token (must NOT work for accept-cgu)
        $stmt->execute(['Diallo', 'Awa', 'Féminin', 'UCAD', 'ETUDIANT', 'awa@example.invalid', 0, 'APPROVED', str_repeat('r', 64), null, null, null]);
        // 2: consented with email
        $stmt->execute(['Bah', 'Mamadou', 'Masculin', 'UGB', 'ETUDIANT', 'mamadou@example.invalid', 1, 'APPROVED', null, null, null, null]);
        // 3: refused & requested deletion
        $stmt->execute(['Camara', 'Fatou', 'Féminin', 'ESP', 'STAGIAIRE', 'fatou@example.invalid', 0, 'APPROVED', null, str_repeat('c', 64), '2026-11-26 12:00:00', null]);
        // 4: trashed deletion request (must be excluded)
        $stmt->execute(['Trashed', 'Deleted', 'Masculin', 'UCAD', 'ETUDIANT', 'gone@example.invalid', 0, 'APPROVED', null, str_repeat('t', 64), '2026-11-25 10:00:00', '2026-11-26 15:00:00']);
        // 5: pending consent without email
        $stmt->execute(['NoMail', 'Alpha', 'Masculin', 'UCAD', 'ELEVE', '', 0, 'APPROVED', null, null, null, null]);
    }

    protected function tearDown(): void
    {
        if ($this->savedPublicUrl === null) {
            unset($_ENV['PUBLIC_APP_URL']);
        } else {
            $_ENV['PUBLIC_APP_URL'] = $this->savedPublicUrl;
        }
    }

    private function makeTransport(): object
    {
        return new class implements MailBatchTransportInterface {
            /** @var list<array<string, mixed>> */
            public array $sent = [];
            public bool $fail = false;

            public function open(): void {}

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
                return $this->fail
                    ? ['ok' => false, 'error' => 'Temporary SMTP failure']
                    : ['ok' => true, 'error' => null];
            }

            public function close(): void {}
        };
    }

    public function testConsentModulePermissionIsRegisteredAndSeparateFromCommunications(): void
    {
        self::assertContains('consent', UserService::MODULES);
        self::assertContains('communications', UserService::MODULES);
    }

    public function testStatsPaginationSearchAndDeletionListingExcludeTrashedMembers(): void
    {
        $repo = new ConsentRepository($this->db);
        $stats = $repo->getStats();

        self::assertSame(4, $stats['total_active']);
        self::assertSame(1, $stats['consented']);
        self::assertSame(3, $stats['not_consented']);
        self::assertSame(0, $stats['reminders_sent']);
        self::assertSame(1, $stats['deletion_requests']);

        $pending = $repo->findPendingConsentPaginated(1, 2, '');
        self::assertSame(3, $pending['total']);
        self::assertCount(2, $pending['items']);

        $searched = $repo->searchSelectableMembers('Diallo', 1, 10);
        self::assertSame(1, $searched['total']);
        self::assertSame('awa@example.invalid', $searched['items'][0]['email']);

        $deletions = $repo->findDeletionRequests();
        self::assertCount(1, $deletions);
        self::assertSame('fatou@example.invalid', $deletions[0]['email']);
    }

    public function testConsentReminderFailsClosedWithoutPublicAppUrlAndDispatchesWithCguToken(): void
    {
        $transport = $this->makeTransport();
        $commRepo = new CommunicationRepository($this->db);
        $consentRepo = new ConsentRepository($this->db);
        $quotaService = new MailQuotaService(new MailDailyUsageRepository($this->db), $commRepo, 300);
        $settingRepo = new SettingRepository($this->db);
        $emailService = new EmailService('u', 'p', 'noreply@aeesgs.org', 'AEESGS', $this->view, null, $transport);
        $commService = new CommunicationService(
            $commRepo,
            $quotaService,
            $settingRepo,
            $emailService,
            $this->view,
            5,
            0
        );
        $consentService = new ConsentService(
            $consentRepo,
            $commService,
            new StudentRepository($this->db),
            $settingRepo,
            $emailService
        );

        unset($_ENV['PUBLIC_APP_URL']);
        putenv('PUBLIC_APP_URL');
        try {
            $consentService->queueConsentReminder('not_consented', [], 1);
            self::fail('Expected RuntimeException when PUBLIC_APP_URL is missing');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('PUBLIC_APP_URL', $e->getMessage());
        }

        $_ENV['PUBLIC_APP_URL'] = 'https://aeesgs.org';
        $queued = $consentService->queueConsentReminder('not_consented', [], 1);
        // Active non-consented with valid email = Awa + Fatou (Alpha has empty email, Trashed is deleted)
        self::assertSame(2, $queued['total_recipients']);

        $res = $consentService->dispatchChunk((int) $queued['id'], 25);
        self::assertSame(2, $res['delivered']);
        self::assertTrue($res['done']);
        self::assertCount(2, $transport->sent);
        self::assertStringContainsString('https://aeesgs.org/accept-cgu.php?token=', $transport->sent[0]['body']);

        // Verify cgu_token is generated separately from review_token and cgu_reminder_sent_at is set
        $awa = $this->db->query('SELECT review_token, cgu_token, cgu_reminder_sent_at FROM students WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(str_repeat('r', 64), $awa['review_token']);
        self::assertNotEmpty($awa['cgu_token']);
        self::assertNotSame($awa['review_token'], $awa['cgu_token']);
        self::assertNotEmpty($awa['cgu_reminder_sent_at']);

        $transport->fail = true;
        $replacement = $consentService->queueConsentReminder('not_consented', [], 1);
        $failed = $consentService->dispatchChunk($replacement['id']);
        self::assertSame(0, $failed['delivered']);
        self::assertNotNull($consentService->findMemberByCguToken($awa['cgu_token']));
        self::assertSame(1, preg_match('/[?]token=([a-f0-9]{64})/', $transport->sent[2]['body'], $failedLink));
        self::assertNull($consentService->findMemberByCguToken($failedLink[1]));
    }

    public function testCguTokenLookupRejectsReviewTokenAndTransitionsLifecycleCleanly(): void
    {
        $transport = $this->makeTransport();
        $studentRepo = new StudentRepository($this->db);
        $consentRepo = new ConsentRepository($this->db);
        $settingRepo = new SettingRepository($this->db);
        $emailService = new EmailService('u', 'p', 'noreply@aeesgs.org', 'AEESGS', $this->view, null, $transport);
        $consentService = new ConsentService($consentRepo, null, $studentRepo, $settingRepo, $emailService);

        // KYC review_token must NEVER resolve in findMemberByCguToken
        self::assertNull($consentService->findMemberByCguToken(str_repeat('r', 64)));
        // Trashed student's cgu_token must NOT resolve
        self::assertNull($consentService->findMemberByCguToken(str_repeat('t', 64)));
        // Active student's cgu_token resolves
        $fatou = $consentService->findMemberByCguToken(str_repeat('c', 64));
        self::assertNotNull($fatou);
        self::assertSame(3, $fatou->getId());

        // Refuse records consent_refused_at and keeps cgu_token active
        $consentService->handleMemberRefusal($fatou, '2026-11-27 08:00:00');
        self::assertNotNull($consentService->findMemberByCguToken(str_repeat('c', 64)));
        $refusedRow = $this->db->query('SELECT consent_privacy, consent_refused_at, cgu_token FROM students WHERE id = 3')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(0, (int) $refusedRow['consent_privacy']);
        self::assertSame('2026-11-27 08:00:00', $refusedRow['consent_refused_at']);

        // Accept sets consent_privacy=1, clears refusal/deletion timestamps and invalidates cgu_token
        $consentService->handleMemberAcceptance($fatou);
        self::assertNull($consentService->findMemberByCguToken(str_repeat('c', 64)));
        $acceptedRow = $this->db->query('SELECT consent_privacy, consent_refused_at, deletion_requested_at, cgu_token FROM students WHERE id = 3')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(1, (int) $acceptedRow['consent_privacy']);
        self::assertNull($acceptedRow['consent_refused_at']);
        self::assertNull($acceptedRow['deletion_requested_at']);
        self::assertNull($acceptedRow['cgu_token']);
        self::assertFalse($consentService->softDeleteMember(3, 1));

        // Assign fresh token and test deletion request invalidates token, records timestamp, and notifies admin
        $newToken = str_repeat('d', 64);
        self::assertFalse($consentService->softDeleteMember(3, 1));
        self::assertNull($consentRepo->findConsentLinkState(3, 'obsolete@example.invalid'));
        $consentRepo->markReminderSent(3, gmdate('Y-m-d H:i:s'), $fatou->getEmail(), $newToken, null);
        self::assertFalse($consentService->handleMemberAcceptance($fatou));
        self::assertFalse($consentService->handleMemberRefusal($fatou));
        self::assertFalse($consentService->handleMemberDeletionRequest($fatou));
        $fatou = $consentService->findMemberByCguToken($newToken);
        self::assertNotNull($fatou);
        $consentService->handleMemberDeletionRequest($fatou, '2026-11-27 09:00:00');
        self::assertNull($consentService->findMemberByCguToken($newToken));
        $delRow = $this->db->query('SELECT deletion_requested_at, cgu_token FROM students WHERE id = 3')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('2026-11-27 09:00:00', $delRow['deletion_requested_at']);
        self::assertNull($delRow['cgu_token']);
        self::assertCount(1, $transport->sent);
        self::assertSame('contact@aeesgs.org', $transport->sent[0]['to']);
        self::assertStringContainsString('Demande de suppression de données (#3)', $transport->sent[0]['subject']);

        // Soft-delete moves member #3 to Trash and removes them from deletion requests list
        self::assertCount(1, $consentService->getDeletionRequests());
        self::assertTrue($consentService->softDeleteMember(3, 1));
        self::assertSame([], $consentService->getDeletionRequests());
        $trashedRow = $this->db->query('SELECT deleted_at, deleted_by FROM students WHERE id = 3')->fetch(PDO::FETCH_ASSOC);
        self::assertNotEmpty($trashedRow['deleted_at']);
        self::assertSame(1, (int) $trashedRow['deleted_by']);
        // Repeating soft-delete on an already-trashed or missing member returns false
        self::assertFalse($consentService->softDeleteMember(3, 1));
        self::assertFalse($consentService->softDeleteMember(999, 1));
    }
}
