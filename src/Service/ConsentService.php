<?php

declare(strict_types=1);

namespace Amea\Service;

use Amea\Core\StaffNavigation;
use Amea\Model\Student;
use Amea\Repository\ConsentRepository;
use Amea\Repository\SettingRepository;
use Amea\Repository\StudentRepository;
use RuntimeException;

class ConsentService
{
    public function __construct(
        private ConsentRepository $consentRepo,
        private ?CommunicationService $commService,
        private StudentRepository $studentRepo,
        private ?SettingRepository $settingRepo = null,
        private ?EmailService $emailService = null
    ) {
    }

    /**
     * @return array{total_active: int, consented: int, not_consented: int, reminders_sent: int, deletion_requests: int}
     */
    public function getStats(): array
    {
        return $this->consentRepo->getStats();
    }

    public function isPublicOriginConfigured(): bool
    {
        return StaffNavigation::publicOrigin() !== null;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function getPendingConsentPage(int $page, int $perPage = 15, string $search = ''): array
    {
        return $this->consentRepo->findPendingConsentPaginated($page, $perPage, $search);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function searchSelectableMembers(string $query, int $page = 1, int $perPage = 20): array
    {
        return $this->consentRepo->searchSelectableMembers($query, $page, $perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDeletionRequests(): array
    {
        return $this->consentRepo->findDeletionRequests();
    }

    /**
     * Queues a CGU consent reminder after validating PUBLIC_APP_URL.
     *
     * @param list<int> $selectedIds
     * @return array<string, mixed>
     */
    public function queueConsentReminder(string $scope, array $selectedIds, ?int $createdBy): array
    {
        if ($this->commService === null) {
            throw new RuntimeException('Le service de communication est indisponible.');
        }

        $publicOrigin = StaffNavigation::publicOrigin();
        if ($publicOrigin === null) {
            throw new RuntimeException(
                'Impossible de générer les liens CGU : la variable PUBLIC_APP_URL (HTTPS) n’est pas configurée.'
            );
        }

        if (!in_array($scope, ['not_consented', 'all', 'selected'], true)) {
            $scope = 'not_consented';
        }

        $targets = $this->consentRepo->findReminderTargets($scope, $selectedIds);

        return $this->commService->queueConsentReminderCommunication(
            $targets,
            ['scope' => $scope, 'selected_count' => $scope === 'selected' ? count($targets) : null],
            $createdBy
        );
    }

    /**
     * Prepares a fresh link without invalidating the last delivered link.
     *
     * @param array<string, mixed> $recipient
     * @return array{url: string, token: string, previous_token: ?string}|null
     */
    public function prepareConsentLink(array $recipient): ?array
    {
        $origin = StaffNavigation::publicOrigin();
        if ($origin === null || empty($recipient['student_id'])) {
            return null;
        }
        $studentId = (int) $recipient['student_id'];
        $state = $this->consentRepo->findConsentLinkState($studentId, (string) $recipient['email']);
        if ($state === null) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        return ['url' => $origin . '/accept-cgu.php?token=' . $token, 'token' => $token, 'previous_token' => $state['cgu_token']];
    }

    /** @param array{url: string, token: string, previous_token: ?string} $link */
    public function markConsentDelivered(array $recipient, array $link, string $sentAt): void
    {
        $this->consentRepo->markReminderSent(
            (int) $recipient['student_id'], $sentAt, (string) $recipient['email'], $link['token'], $link['previous_token']
        );
    }

    /**
     * Dispatches one live chunk of a CGU consent reminder.
     *
     * @return array<string, mixed>
     */
    public function dispatchChunk(int $communicationId, int $chunkSize = CommunicationService::DEFAULT_CHUNK_SIZE): array
    {
        if ($this->commService === null) {
            throw new RuntimeException('Le service de communication est indisponible.');
        }

        return $this->commService->dispatchChunk(
            $communicationId,
            $chunkSize,
            $this->prepareConsentLink(...),
            $this->markConsentDelivered(...)
        );
    }

    public function softDeleteMember(int $studentId, int $staffUserId): bool
    {
        return $this->studentRepo->softDelete($studentId, $staffUserId, requireDeletionRequest: true);
    }

    // ─── Public consent token lifecycle (AcceptTermsController) ───────────────

    public function findMemberByCguToken(string $token): ?Student
    {
        return $this->studentRepo->findByCguToken(trim($token));
    }

    public function handleMemberAcceptance(Student $student): bool
    {
        return $this->consentRepo->recordAcceptance($student->getId(), (string) $student->getCguToken());
    }

    public function handleMemberRefusal(Student $student, ?string $now = null): bool
    {
        return $this->consentRepo->recordRefusal($student->getId(), (string) $student->getCguToken(), $now ?? gmdate('Y-m-d H:i:s'));
    }

    public function handleMemberDeletionRequest(Student $student, ?string $now = null): bool
    {
        if (!$this->consentRepo->recordDeletionRequest($student->getId(), (string) $student->getCguToken(), $now ?? gmdate('Y-m-d H:i:s'))) {
            return false;
        }

        if ($this->emailService === null) {
            return true;
        }

        $adminEmail = $this->settingRepo !== null
            ? trim($this->settingRepo->getValue('contact_email', 'contact@aeesgs.org'))
            : 'contact@aeesgs.org';
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $adminEmail = 'contact@aeesgs.org';
        }

        $body = '<p>Le membre <strong>'
            . htmlspecialchars($student->getFullName(), ENT_QUOTES, 'UTF-8')
            . '</strong> (email&nbsp;: <strong>'
            . htmlspecialchars($student->getEmail(), ENT_QUOTES, 'UTF-8')
            . '</strong>, ID&nbsp;: #' . $student->getId() . ')'
            . ' a refusé les CGU et demande la suppression de ses données personnelles.</p>';

        $this->emailService->send(
            $adminEmail,
            'AEESGS – Demande de suppression de données (#' . $student->getId() . ')',
            $body
        );
        return true;
    }
}
