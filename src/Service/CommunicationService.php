<?php

declare(strict_types=1);

namespace Amea\Service;

use Closure;

use Amea\Core\Runtime;
use Amea\Core\View;
use Amea\Model\CommunicationStatus;
use Amea\Model\CommunicationType;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\SettingRepository;

class CommunicationService
{
    public const DEFAULT_CHUNK_SIZE = 25;
    public const STALE_CLAIM_SECONDS = 300;

    public const STATUS_GROUPS = [
        'ELEVE' => ['ELEVE', 'PUPIL'],
        'ETUDIANT' => ['ETUDIANT', 'STUDENT'],
        'STAGIAIRE' => ['STAGIAIRE', 'TRAINEE'],
        'Diplômé(e)' => ['Diplômé(e)', 'Diplômé', 'GRADUATE'],
    ];

    public const VALID_KYC_STATUSES = [
        'PENDING_CONFIRMATION',
        'UNDER_REVIEW',
        'APPROVED',
        'NEEDS_CLARIFICATION',
        'REJECTED',
    ];

    private int $maxAttempts;
    private int $pauseMs;
    private Closure $clock;

    public function __construct(
        private CommunicationRepository $commRepo,
        private MailQuotaService $quotaService,
        private SettingRepository $settingRepo,
        private EmailService $emailService,
        private View $view,
        ?int $maxAttempts = null,
        ?int $pauseMs = null,
        ?Closure $clock = null
    ) {
        $this->maxAttempts = max(1, $maxAttempts ?? (int) Runtime::mailSetting('MAIL_MAX_ATTEMPTS', '5'));
        $this->pauseMs = max(0, $pauseMs ?? (int) Runtime::mailSetting('MAIL_SEND_PAUSE_MS', '50'));
        $this->clock = $clock ?? time(...);
    }

    public static function retryBackoffSeconds(int $attempts): int
    {
        return min(60 * (2 ** max(1, $attempts)), 3600);
    }

    /**
     * Normalizes raw audience filter input into canonical keys.
     *
     * @param array<string, mixed> $raw
     * @return array{status: string, institution: string, consent: string, kyc_status: string}
     */
    public function normalizeFilters(array $raw): array
    {
        $status = trim((string) ($raw['status'] ?? ''));
        if (!array_key_exists($status, self::STATUS_GROUPS)) {
            $status = '';
        }

        $institution = trim((string) ($raw['institution'] ?? ''));

        $consent = trim((string) ($raw['consent'] ?? ''));
        if (!in_array($consent, ['consented', 'not_consented'], true)) {
            $consent = '';
        }

        $kycStatus = trim((string) ($raw['kyc_status'] ?? 'exclude_rejected'));
        if ($kycStatus === '') {
            $kycStatus = 'exclude_rejected';
        }
        if ($kycStatus !== 'exclude_rejected'
            && $kycStatus !== 'all'
            && !in_array($kycStatus, self::VALID_KYC_STATUSES, true)) {
            $kycStatus = 'exclude_rejected';
        }

        return [
            'status' => $status,
            'institution' => $institution,
            'consent' => $consent,
            'kyc_status' => $kycStatus,
        ];
    }

    public function getReplyToAddress(): string
    {
        $configured = trim($this->settingRepo->getValue('contact_email', 'contact@aeesgs.org'));
        return filter_var($configured, FILTER_VALIDATE_EMAIL) !== false
            ? $configured
            : 'contact@aeesgs.org';
    }

    /**
     * @return array{active_total: int, reachable_with_email: int, missing_email: int, daily_sent: int, daily_limit: int}
     */
    public function getDashboardStats(?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');
        $stats = $this->commRepo->getCommunicationStats();
        $stats['daily_sent'] = $this->quotaService->getUsageForDate($today);
        $stats['daily_limit'] = $this->quotaService->getDailyLimit();
        return $stats;
    }

    /**
     * @param array<string, mixed> $rawFilters
     */
    public function countAudience(array $rawFilters): int
    {
        return $this->commRepo->countAudience($this->buildQuerySpec($this->normalizeFilters($rawFilters)));
    }

    /**
     * Renders a branded AEESGS bulk communication email with safe escaping and
     * {{prenom}} / {{nom}} personalization.
     */
    public function renderBulkEmail(string $subject, string $body, string $firstName, string $lastName): string
    {
        $escapedBody = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
        $escapedFirst = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $escapedLast = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');

        $personalized = strtr($escapedBody, [
            '{{prenom}}' => $escapedFirst,
            '{{nom}}' => $escapedLast,
        ]);

        $contentHtml = nl2br($personalized, false);

        return $this->view->render('emails/bulk-communication.html', [
            'subject' => $subject,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'content' => $contentHtml,
        ]);
    }

    public function renderConsentReminderEmail(string $firstName, string $lastName, string $acceptUrl): string
    {
        return $this->view->render('emails/cgu-reminder.html', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'accept_url' => $acceptUrl,
        ]);
    }

    /**
     * Persists a bulk member communication and its recipient rows without sending SMTP yet.
     * Returns an unpersisted zero-recipient result when no eligible recipients match.
     *
     * @param array<string, mixed> $rawFilters
     * @return array<string, mixed>
     */
    public function queueBulkCommunication(
        string $subject,
        string $body,
        array $rawFilters,
        ?int $createdBy,
        ?int $confirmedCount = null
    ): array {
        $filters = $this->normalizeFilters($rawFilters);
        $recipients = $this->commRepo->findAudienceRecipients($this->buildQuerySpec($filters));
        if ($confirmedCount !== null && count($recipients) !== $confirmedCount) {
            return ['id' => 0, 'status' => 'AUDIENCE_CHANGED', 'total_recipients' => count($recipients)];
        }
        if ($recipients === []) {
            return [
                'id' => 0,
                'status' => 'EMPTY',
                'total_recipients' => 0,
            ];
        }

        $now = gmdate('Y-m-d H:i:s');
        $id = $this->commRepo->createCommunication(
            CommunicationType::BULK,
            $subject,
            $body,
            $filters,
            $recipients,
            $createdBy,
            $now
        );

        return $this->commRepo->findById($id) ?? [
            'id' => $id,
            'status' => CommunicationStatus::QUEUED->value,
            'total_recipients' => count($recipients),
        ];
    }

    /**
     * Queues a CGU consent reminder communication for explicit target members.
     *
     * @param list<array{id: ?int, first_name: string, last_name: string, email: string}> $recipients
     * @param array<string, mixed> $metaFilters
     * @return array<string, mixed>
     */
    public function queueConsentReminderCommunication(
        array $recipients,
        array $metaFilters,
        ?int $createdBy
    ): array {
        if ($recipients === []) {
            return [
                'id' => 0,
                'status' => 'EMPTY',
                'total_recipients' => 0,
            ];
        }

        $now = gmdate('Y-m-d H:i:s');
        $subject = 'AEESGS – Acceptation des CGU et politique de confidentialité';

        $id = $this->commRepo->createCommunication(
            CommunicationType::CGU_REMINDER,
            $subject,
            '',
            $metaFilters,
            $recipients,
            $createdBy,
            $now
        );

        return $this->commRepo->findById($id) ?? [
            'id' => $id,
            'status' => CommunicationStatus::QUEUED->value,
            'total_recipients' => count($recipients),
        ];
    }

    /**
     * Sends a single personalized preview email to the logged-in staff user without
     * creating any rows in communications or communication_recipients.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function sendSelfTest(
        string $toEmail,
        string $firstName,
        string $lastName,
        string $subject,
        string $body
    ): array {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'error' => 'Votre compte administrateur ne dispose pas d’une adresse email valide.'];
        }

        $html = $this->renderBulkEmail('[Test] ' . $subject, $body, $firstName, $lastName);
        return $this->emailService->sendWithResult(
            $toEmail,
            '[Test] ' . $subject,
            $html,
            $this->getReplyToAddress()
        );
    }

    /**
     * Dispatches up to $chunkSize due recipients over one persistent SMTP session,
     * reserving 1 daily quota slot BEFORE each send and persisting each recipient's
     * SENT / retry / FAILED transition IMMEDIATELY after each send so a mid-chunk
     * acknowledged deliveries are skipped during stale-claim recovery.
     *
     * @param ?callable(array<string, mixed>): ?array{url: string, token: string, previous_token: ?string} $prepareConsentLink
     * @param ?callable(array<string, mixed>, array{url: string, token: string, previous_token: ?string}, string): void $onConsentDelivered
     * @return array<string, mixed>
     */
    public function dispatchChunk(
        ?int $communicationId = null,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?callable $prepareConsentLink = null,
        ?callable $onConsentDelivered = null
    ): array {
        // Both workers share the persistent queue directory, including across application containers.
        $directory = $this->quotaService->queueDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create dispatch lock directory.');
        }
        $lock = fopen($directory . '/.dispatch.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Cannot open dispatch lock.');
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB, $busy)) {
                if (!$busy) {
                    throw new \RuntimeException('Cannot lock dispatch.');
                }
                $summary = $communicationId === null ? null : $this->commRepo->findById($communicationId);
                return $this->buildDispatchResponse($summary, 0, 0, 0, 0, gmdate('Y-m-d'), false, true);
            }
            return $this->dispatchLockedChunk($communicationId, $chunkSize, $prepareConsentLink, $onConsentDelivered);
        } finally {
            fclose($lock);
        }
    }

    private function dispatchLockedChunk(
        ?int $communicationId = null,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?callable $prepareConsentLink = null,
        ?callable $onConsentDelivered = null
    ): array {
        $chunkSize = max(1, $chunkSize);
        $nowTs = ($this->clock)();
        $now = gmdate('Y-m-d H:i:s', $nowTs);
        $today = gmdate('Y-m-d', $nowTs);
        $staleCutoff = gmdate('Y-m-d H:i:s', $nowTs - self::STALE_CLAIM_SECONDS);

        $this->commRepo->settleAbandonedCancellations($communicationId, $staleCutoff, $now);

        if ($this->quotaService->isLimitReached($today)) {
            $summary = $communicationId !== null ? $this->reconcileCommunication($communicationId, true, $now) : null;
            $this->quotaService->pausePendingCommunicationsForLimit();
            return $this->buildDispatchResponse($summary, 0, 0, 0, 0, $today, true, false);
        }

        $claimed = $this->commRepo->claimDueRecipients($communicationId, $chunkSize, $now, $staleCutoff);
        if ($claimed === []) {
            $summary = $communicationId !== null ? $this->reconcileCommunication($communicationId, false, $now) : null;
            return $this->buildDispatchResponse($summary, 0, 0, 0, 0, $today, false, false);
        }

        $replyTo = $this->getReplyToAddress();
        $processed = 0;
        $delivered = 0;
        $retried = 0;
        $failed = 0;
        $limitDate = null;
        $touchedCommunications = [];
        $totalClaimed = count($claimed);

        $this->emailService->runInBatchSession(
            function (callable $sendOne) use (
                $claimed,
                $totalClaimed,
                $now,
                $replyTo,
                $prepareConsentLink,
                $onConsentDelivered,
                &$processed,
                &$delivered,
                &$retried,
                &$failed,
                &$limitDate,
                &$touchedCommunications
            ): void {
                foreach ($claimed as $index => $recipient) {
                    $cid = (int) $recipient['communication_id'];
                    $touchedCommunications[$cid] = true;
                    $recipient['claimed_at'] = $now;
                    $rendered = $this->renderRecipientBody($recipient, $prepareConsentLink);
                    $attemptTime = ($this->clock)();
                    $attemptDate = gmdate('Y-m-d', $attemptTime);
                    $attemptNow = gmdate('Y-m-d H:i:s', $attemptTime);
                    $reservationDate = $rendered['ok'] ? $attemptDate : null;
                    $admitted = $this->admitRecipient($recipient, $reservationDate, $attemptNow);
                    if ($admitted === null) {
                        continue;
                    }
                    if (!$admitted) {
                        $this->commRepo->releaseClaimedRecipients(array_slice($claimed, $index), $now, $attemptNow);
                        $limitDate = $attemptDate;
                        break;
                    }

                    $processed++;
                    $outcome = $rendered['ok']
                        ? $sendOne((string) $recipient['email'], (string) $recipient['subject'], (string) $rendered['html'], $replyTo)
                        : ['ok' => false, 'error' => $rendered['error']];

                    $statusDelta = $this->persistRecipientOutcome($recipient, $outcome, $reservationDate, $rendered['consent_link'], $onConsentDelivered);
                    $delivered += $statusDelta['delivered'];
                    $retried += $statusDelta['retried'];
                    $failed += $statusDelta['failed'];

                    if ($this->pauseMs > 0 && ($index + 1) < $totalClaimed) {
                        usleep($this->pauseMs * 1000);
                    }
                }
            }
        );

        $reconcileTime = ($this->clock)();
        $today = gmdate('Y-m-d', $reconcileTime);
        $limitReached = $limitDate === $today || $this->quotaService->isLimitReached($today);
        $reconcileNow = gmdate('Y-m-d H:i:s', $reconcileTime);
        $primarySummary = null;

        foreach (array_keys($touchedCommunications) as $cid) {
            $reconciled = $this->reconcileCommunication($cid, $limitReached, $reconcileNow);
            if ($communicationId === $cid) {
                $primarySummary = $reconciled;
            }
        }
        if ($communicationId !== null && $primarySummary === null) {
            $primarySummary = $this->reconcileCommunication($communicationId, $limitReached, $reconcileNow);
        }
        if ($limitReached) {
            $this->quotaService->pausePendingCommunicationsForLimit();
        }

        $hasMoreDue = $communicationId !== null
            && !$limitReached
            && $this->commRepo->countDueRecipients($communicationId, $reconcileNow) > 0;

        $limitReachedFlag = $limitReached && (($primarySummary['pending_count'] ?? 0) > 0 || $communicationId === null);

        return $this->buildDispatchResponse(
            $primarySummary,
            $processed,
            $delivered,
            $retried,
            $failed,
            $today,
            $limitReachedFlag,
            $hasMoreDue
        );
    }

    /**
     * Reconciles a communication's counts and derives its aggregate lifecycle status.
     *
     * @return array<string, mixed>
     */
    public function reconcileCommunication(int $communicationId, bool $dailyLimitReached = false, ?string $now = null): array
    {
        return $this->commRepo->transaction(function () use ($communicationId, $dailyLimitReached, $now): array {
            $this->commRepo->lockCommunication($communicationId);
            return $this->reconcileLockedCommunication($communicationId, $dailyLimitReached, $now);
        });
    }

    private function reconcileLockedCommunication(int $communicationId, bool $dailyLimitReached, ?string $now): array
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $counts = $this->commRepo->getRecipientStatusCounts($communicationId);
        $isCancelled = $this->commRepo->isCommunicationCancelled($communicationId);

        $sent = $counts['sent'];
        $failed = $counts['failed'];
        $cancelled = $counts['cancelled'];
        $pending = $counts['pending'];
        $sending = $counts['sending'];

        $completedAt = null;
        if ($pending === 0 && $sending === 0) {
            if ($failed > 0 && !$isCancelled) {
                $status = CommunicationStatus::COMPLETED_WITH_ERRORS;
            } elseif ($cancelled > 0 || $isCancelled) {
                $status = CommunicationStatus::CANCELLED;
            } else {
                $status = CommunicationStatus::COMPLETED;
            }
            $completedAt = $now;
        } elseif ($isCancelled) {
            $status = CommunicationStatus::CANCELLED;
        } elseif ($dailyLimitReached) {
            $status = CommunicationStatus::PAUSED_LIMIT;
        } elseif ($sending > 0 || $sent > 0 || $failed > 0) {
            $status = CommunicationStatus::SENDING;
        } else {
            $status = CommunicationStatus::QUEUED;
        }

        $this->commRepo->updateCommunicationSummary($communicationId, $status, $sent, $failed, $cancelled, $completedAt);

        return $this->commRepo->findById($communicationId) ?? [
            'id' => $communicationId,
            'status' => $status->value,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'cancelled_count' => $cancelled,
            'pending_count' => $pending,
            'sending_count' => $sending,
        ];
    }

    public function getCommunication(int $id, ?CommunicationType $expectedType = null): ?array
    {
        $communication = $this->commRepo->findById($id);
        if ($communication === null) {
            return null;
        }
        if ($expectedType !== null && $communication['type'] !== $expectedType->value) {
            return null;
        }
        return $communication;
    }

    /**
     * Fetches paginated history with batch-loaded failure details attached (2 SQL queries total).
     *
     * @return list<array<string, mixed>>
     */
    public function getRecentHistoryWithFailures(
        CommunicationType $type = CommunicationType::BULK,
        int $limit = 15,
        int $offset = 0
    ): array {
        $history = $this->commRepo->findRecent($type, $limit, $offset);
        $idsWithFailures = array_values(array_map(
            static fn (array $row): int => (int) $row['id'],
            array_filter($history, static fn (array $row): bool => (int) ($row['failed_count'] ?? 0) > 0)
        ));
        $failuresByComm = $this->commRepo->findFailedRecipientsByCommunicationIds($idsWithFailures, 25);

        foreach ($history as &$item) {
            $item['failures'] = $failuresByComm[(int) $item['id']] ?? [];
        }
        unset($item);

        return $history;
    }

    public function countHistory(CommunicationType $type = CommunicationType::BULK): int
    {
        return $this->commRepo->countByType($type);
    }

    /**
     * @return list<array{id: int, student_id: ?int, email: string, first_name: string, last_name: string, attempts: int, last_error: ?string}>
     */
    public function getFailedRecipients(int $communicationId, int $limit = 50, int $offset = 0): array
    {
        $grouped = $this->commRepo->findFailedRecipientsByCommunicationIds([$communicationId], $limit, $offset);
        return $grouped[$communicationId] ?? [];
    }

    public function retryFailed(int $communicationId): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return $this->commRepo->transaction(function () use ($communicationId, $now): int {
            $this->commRepo->lockCommunication($communicationId);
            if ($this->commRepo->isCommunicationCancelled($communicationId) && $this->commRepo->hasUnsettledRecipients($communicationId)) {
                return 0;
            }
            $count = $this->commRepo->resetFailedRecipientsToPending($communicationId, $now);
            if ($count > 0) {
                $this->commRepo->reopenCommunication($communicationId);
                $this->reconcileLockedCommunication($communicationId, false, $now);
            }
            return $count;
        });
    }

    public function cancelRemaining(int $communicationId): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return $this->commRepo->transaction(function () use ($communicationId, $now): int {
            $this->commRepo->lockCommunication($communicationId);
            $count = $this->commRepo->markPendingRecipientsCancelled($communicationId);
            $this->reconcileLockedCommunication($communicationId, false, $now);
            return $count;
        });
    }

    /**
     * @param array{status: string, institution: string, consent: string, kyc_status: string} $filters
     * @return array{status_values: list<string>, institution: string, consent: string, kyc_status: string}
     */
    private function buildQuerySpec(array $filters): array
    {
        return [
            'status_values' => $filters['status'] !== '' ? self::STATUS_GROUPS[$filters['status']] : [],
            'institution' => $filters['institution'],
            'consent' => $filters['consent'],
            'kyc_status' => $filters['kyc_status'],
        ];
    }

    /**
     * Orders admission to SMTP against cancellation. Null means cancelled, false means quota exhausted.
     *
     * @param array<string, mixed> $recipient
     */
    private function admitRecipient(array $recipient, ?string $reservationDate, string $now): ?bool
    {
        return $this->commRepo->transaction(function () use ($recipient, $reservationDate, $now): ?bool {
            $id = (int) $recipient['communication_id'];
            $this->commRepo->lockCommunication($id);
            if ($this->commRepo->isCommunicationCancelled($id)) {
                $this->commRepo->markRecipientCancelled(
                    (int) $recipient['id'], $recipient['claimed_at'], (int) $recipient['attempts'], null
                );
                $this->commRepo->recordTerminalOutcome($id, 0, 0, 1, $now);
                return null;
            }
            return $reservationDate === null || $this->quotaService->tryReserveOneSlot($reservationDate, $now);
        });
    }

    /**
     * @param array<string, mixed> $recipient
     * @param ?callable(array<string, mixed>): ?array{url: string, token: string, previous_token: ?string} $prepareConsentLink
     * @return array{ok: bool, html: ?string, error: ?string, consent_link: ?array{url: string, token: string, previous_token: ?string}}
     */
    private function renderRecipientBody(array $recipient, ?callable $prepareConsentLink): array
    {
        if ($recipient['communication_type'] === CommunicationType::CGU_REMINDER->value) {
            $link = $prepareConsentLink !== null ? $prepareConsentLink($recipient) : null;
            if ($link === null) {
                return [
                    'ok' => false,
                    'html' => null,
                    'consent_link' => null,
                    'error' => 'Lien public de consentement indisponible (PUBLIC_APP_URL manquant ou adhérent introuvable).',
                ];
            }
            return [
                'ok' => true,
                'html' => $this->renderConsentReminderEmail(
                    (string) $recipient['first_name'],
                    (string) $recipient['last_name'],
                    $link['url']
                ),
                'consent_link' => $link,
                'error' => null,
            ];
        }

        return [
            'ok' => true,
            'html' => $this->renderBulkEmail(
                (string) $recipient['subject'],
                (string) $recipient['body'],
                (string) $recipient['first_name'],
                (string) $recipient['last_name']
            ),
            'error' => null,
            'consent_link' => null,
        ];
    }

    /**
     * @param array<string, mixed> $recipient
     * @param array{ok: bool, error: ?string} $outcome
     * @param ?array{url: string, token: string, previous_token: ?string} $consentLink
     * @param ?callable(array<string, mixed>, array{url: string, token: string, previous_token: ?string}, string): void $onConsentDelivered
     * @return array{delivered: int, retried: int, failed: int}
     */
    private function persistRecipientOutcome(
        array $recipient,
        array $outcome,
        ?string $reservationDate,
        ?array $consentLink,
        ?callable $onConsentDelivered
    ): array {
        $rid = (int) $recipient['id'];
        $cid = (int) $recipient['communication_id'];
        $finishTs = ($this->clock)();
        $finishAt = gmdate('Y-m-d H:i:s', $finishTs);

        if ($outcome['ok']) {
            $this->commRepo->transaction(function () use ($recipient, $rid, $cid, $finishAt, $consentLink, $onConsentDelivered): void {
                $this->commRepo->lockCommunication($cid);
                $this->commRepo->markRecipientSent($rid, $recipient['claimed_at'], $finishAt);
                if ($consentLink !== null && $onConsentDelivered !== null) {
                    $onConsentDelivered($recipient, $consentLink, $finishAt);
                }
                $this->commRepo->recordTerminalOutcome($cid, 1, 0, 0, $finishAt);
            });
            return ['delivered' => 1, 'retried' => 0, 'failed' => 0];
        }

        $attempts = (int) $recipient['attempts'] + 1;
        $error = (string) ($outcome['error'] ?? 'Erreur SMTP');

        return $this->commRepo->transaction(function () use ($cid, $rid, $recipient, $attempts, $error, $finishTs, $finishAt, $reservationDate): array {
            $this->commRepo->lockCommunication($cid);
            $retried = 0;
            $failed = 0;
            if ($this->commRepo->isCommunicationCancelled($cid)) {
                $this->commRepo->markRecipientCancelled($rid, $recipient['claimed_at'], $attempts, $error);
                $this->commRepo->recordTerminalOutcome($cid, 0, 0, 1, $finishAt);
            } elseif ($attempts >= $this->maxAttempts) {
                $this->commRepo->markRecipientFailed($rid, $recipient['claimed_at'], $attempts, $error);
                $this->commRepo->recordTerminalOutcome($cid, 0, 1, 0, $finishAt);
                $failed = 1;
            } else {
                $nextAttemptAt = gmdate('Y-m-d H:i:s', $finishTs + self::retryBackoffSeconds($attempts));
                $this->commRepo->markRecipientRetry($rid, $recipient['claimed_at'], $attempts, $nextAttemptAt, $error);
                $retried = 1;
            }
            if ($reservationDate !== null) {
                $this->quotaService->releaseOneSlot($reservationDate, $finishAt);
            }
            return ['delivered' => 0, 'retried' => $retried, 'failed' => $failed];
        });
    }

    /**
     * @param ?array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function buildDispatchResponse(
        ?array $summary,
        int $processed,
        int $delivered,
        int $retried,
        int $failed,
        string $today,
        bool $dailyLimitReached,
        bool $hasMoreDue
    ): array {
        return [
            'communication' => $summary,
            'processed' => $processed,
            'delivered' => $delivered,
            'retried' => $retried,
            'failed' => $failed,
            'daily_sent' => $this->quotaService->getUsageForDate($today),
            'daily_limit' => $this->quotaService->getDailyLimit(),
            'daily_limit_reached' => $dailyLimitReached,
            'has_more_due' => $hasMoreDue,
            'done' => $summary === null || (($summary['pending_count'] ?? 0) === 0 && ($summary['sending_count'] ?? 0) === 0),
        ];
    }
}
