<?php

declare(strict_types=1);

namespace Amea\Repository;

use PDO;
use Throwable;

/**
 * Persistence boundary for the unified daily outbound email quota table.
 */
class MailDailyUsageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function assertQuotaSchemaAvailable(): void
    {
        $this->pdo->query('SELECT 1 FROM mail_daily_usage LIMIT 0');
    }

    public function getUsage(string $date): int
    {
        $stmt = $this->pdo->prepare('SELECT sent_count FROM mail_daily_usage WHERE usage_date = ?');
        $stmt->execute([$date]);
        $val = $stmt->fetchColumn();
        return $val === false ? 0 : (int) $val;
    }

    /**
     * Atomically reserves a single delivery slot for $date if below $dailyLimit.
     */
    public function reserveSingleSlot(string $date, int $dailyLimit, string $now, int $legacyUsage): bool
    {
        if ($dailyLimit <= 0) {
            return false;
        }

        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->importLegacyUsage($date, $legacyUsage, $now);
            $update = $this->pdo->prepare(
                'UPDATE mail_daily_usage SET sent_count = sent_count + 1, updated_at = ?
                 WHERE usage_date = ? AND sent_count < ?'
            );
            $update->execute([$now, $date, $dailyLimit]);
            $reserved = $update->rowCount() === 1;
            if ($ownTransaction) {
                $this->pdo->commit();
            }
            return $reserved;
        } catch (Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function importLegacyUsage(string $date, int $legacyUsage, string $now): void
    {
        // A duplicate-key update takes the row's write lock immediately on MariaDB.
        $upsert = $this->isMysql()
            ? 'ON DUPLICATE KEY UPDATE sent_count = GREATEST(sent_count, VALUES(sent_count))'
            : 'ON CONFLICT(usage_date) DO UPDATE SET sent_count = MAX(sent_count, excluded.sent_count)';
        $this->pdo->prepare(
            "INSERT INTO mail_daily_usage (usage_date, sent_count, updated_at) VALUES (?, ?, ?) {$upsert}"
        )->execute([$date, $legacyUsage, $now]);
    }

    public function releaseSingleSlot(string $date, string $now): void
    {
        $this->pdo->prepare(
            'UPDATE mail_daily_usage
             SET sent_count = CASE WHEN sent_count > 0 THEN sent_count - 1 ELSE 0 END,
                 updated_at = ?
             WHERE usage_date = ?'
        )->execute([$now, $date]);
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}
