<?php

declare(strict_types=1);

namespace Amea\Service;

use Amea\Core\Runtime;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\MailDailyUsageRepository;

/**
 * Coordinates the unified daily outbound mail quota shared between live web
 * dispatch chunks and the scheduled CLI mail worker.
 */
class MailQuotaService
{
    private int $dailyLimit;

    public function __construct(
        private MailDailyUsageRepository $usageRepo,
        private ?CommunicationRepository $commRepo = null,
        ?int $dailyLimit = null,
        private ?string $queueDir = null
    ) {
        $this->dailyLimit = max(1, $dailyLimit ?? (int) Runtime::mailSetting('MAIL_DAILY_LIMIT', '300'));
    }

    public function queueDirectory(): string
    {
        return $this->queueDir ?? Runtime::mailQueueDir(dirname(__DIR__, 2));
    }

    public function getDailyLimit(): int
    {
        return $this->dailyLimit;
    }

    public function getUsageForDate(?string $date = null): int
    {
        $date ??= gmdate('Y-m-d');
        $legacy = $this->legacyUsage($date);
        if ($legacy > 0) {
            $this->usageRepo->importLegacyUsage($date, $legacy, gmdate('Y-m-d H:i:s'));
        }
        return $this->usageRepo->getUsage($date);
    }

    public function isLimitReached(?string $date = null): bool
    {
        return $this->getUsageForDate($date) >= $this->dailyLimit;
    }

    private function legacyUsage(string $date): int
    {
        return (new LegacyMailCounter($this->queueDirectory(), $date))->read();
    }

    public function tryReserveOneSlot(?string $date = null, ?string $now = null): bool
    {
        $date ??= gmdate('Y-m-d');
        return $this->usageRepo->reserveSingleSlot(
            $date,
            $this->dailyLimit,
            $now ?? gmdate('Y-m-d H:i:s'),
            $this->legacyUsage($date)
        );
    }

    public function releaseOneSlot(?string $date = null, ?string $now = null): void
    {
        $this->usageRepo->releaseSingleSlot(
            $date ?? gmdate('Y-m-d'),
            $now ?? gmdate('Y-m-d H:i:s')
        );
    }

    public function pausePendingCommunicationsForLimit(): void
    {
        $this->commRepo?->markActiveCommunicationsPausedForLimit();
    }
}
