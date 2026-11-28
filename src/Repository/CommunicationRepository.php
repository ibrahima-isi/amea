<?php

declare(strict_types=1);

namespace Amea\Repository;

use Amea\Model\CommunicationStatus;
use Amea\Model\CommunicationType;
use PDO;
use Throwable;

/**
 * Pure SQL persistence boundary for member communications and their recipients.
 */
class CommunicationRepository
{
    private string $validEmailSql;

    public function __construct(private PDO $pdo)
    {
        $this->validEmailSql = self::validEmailPredicate($pdo);
    }

    public static function validEmailPredicate(PDO $pdo): string
    {
        // ASCII dot-atom mailbox and DNS labels. Both engines evaluate the same PCRE.
        $atom = "[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+";
        $label = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';
        $topLabel = '[A-Za-z](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';
        $pattern = "(?-i)^{$atom}(?:[.]{$atom})*@(?:{$label}[.])+{$topLabel}\\z";
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $regexp = static fn (string $regex, ?string $value): int =>
                preg_match(chr(1) . $regex . chr(1), $value ?? '');
            if ($pdo instanceof \Pdo\Sqlite) {
                $pdo->createFunction('regexp', $regexp, 2);
            } else {
                $pdo->sqliteCreateFunction('regexp', $regexp, 2);
            }
        }
        return "LENGTH(TRIM(email)) <= 254 AND INSTR(TRIM(email), '@') BETWEEN 2 AND 65 AND TRIM(email) REGEXP "
            . $pdo->quote($pattern);
    }

    public function assertDeliverySchemaAvailable(): void
    {
        $this->pdo->query('SELECT 1 FROM communications LIMIT 0');
        $this->pdo->query('SELECT 1 FROM communication_recipients LIMIT 0');
    }

    public function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function lockCommunication(int $id): void
    {
        $sql = $this->isMysql()
            ? 'SELECT id FROM communications WHERE id = ? FOR UPDATE'
            : 'UPDATE communications SET id = id WHERE id = ?';
        $this->pdo->prepare($sql)->execute([$id]);
    }

    /**
     * Single aggregate SQL query for communications overview stats.
     *
     * @return array{active_total: int, reachable_with_email: int, missing_email: int}
     */
    public function getCommunicationStats(): array
    {
        $sql = 'SELECT
                    COUNT(*) AS active_total,
                    SUM(CASE WHEN ' . $this->validEmailSql . ' THEN 1 ELSE 0 END) AS reachable_with_email
                FROM students
                WHERE deleted_at IS NULL';
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
        $activeTotal = (int) ($row['active_total'] ?? 0);
        $reachable = (int) ($row['reachable_with_email'] ?? 0);

        return [
            'active_total' => $activeTotal,
            'reachable_with_email' => $reachable,
            'missing_email' => max(0, $activeTotal - $reachable),
        ];
    }

    /**
     * Single SQL COUNT(*) of active eligible members matching a resolved query spec.
     *
     * @param array{status_values: list<string>, institution: string, consent: string, kyc_status: string} $querySpec
     */
    public function countAudience(array $querySpec): int
    {
        [$where, $params] = $this->buildAudienceWhere($querySpec);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM students{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lightweight SQL recipient projection (zero Student object hydration).
     *
     * @param array{status_values: list<string>, institution: string, consent: string, kyc_status: string} $querySpec
     * @return list<array{id: int, first_name: string, last_name: string, email: string}>
     */
    public function findAudienceRecipients(array $querySpec): array
    {
        [$where, $params] = $this->buildAudienceWhere($querySpec);
        $stmt = $this->pdo->prepare(
            "SELECT id, first_name, last_name, TRIM(email) AS email FROM students{$where} ORDER BY id ASC"
        );
        $stmt->execute($params);

        $recipients = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recipients[] = [
                'id' => (int) $row['id'],
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }
        return $recipients;
    }

    /**
     * Persists a communication and its recipient queue atomically.
     *
     * @param array<string, mixed> $filters
     * @param list<array{id: ?int, first_name: string, last_name: string, email: string}> $recipients
     */
    public function createCommunication(
        CommunicationType $type,
        string $subject,
        string $body,
        array $filters,
        array $recipients,
        ?int $createdBy,
        string $now
    ): int {
        $total = count($recipients);
        $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO communications
                    (type, subject, body, filters_json, status, total_recipients, sent_count, failed_count, cancelled_count, created_by, created_at, completed_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, NULL)'
            );
            $stmt->execute([
                $type->value,
                $subject,
                $body,
                $filtersJson,
                CommunicationStatus::QUEUED->value,
                $total,
                $createdBy,
                $now,
            ]);
            $communicationId = (int) $this->pdo->lastInsertId();

            foreach (array_chunk($recipients, 100) as $chunk) {
                $placeholders = [];
                $values = [];
                foreach ($chunk as $recipient) {
                    $placeholders[] = '(?, ?, ?, ?, ?, ?, 0, ?)';
                    $values[] = $communicationId;
                    $values[] = $recipient['id'];
                    $values[] = $recipient['email'];
                    $values[] = $recipient['first_name'];
                    $values[] = $recipient['last_name'];
                    $values[] = 'PENDING';
                    $values[] = $now;
                }
                $insertSql = 'INSERT INTO communication_recipients
                    (communication_id, student_id, email, first_name, last_name, status, attempts, next_attempt_at)
                    VALUES ' . implode(', ', $placeholders);
                $this->pdo->prepare($insertSql)->execute($values);
            }

            $this->pdo->commit();
            return $communicationId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    u.first_name AS author_first_name,
                    u.last_name AS author_last_name,
                    COALESCE(rc.pending_count, 0) AS pending_count,
                    COALESCE(rc.sending_count, 0) AS sending_count
             FROM communications c
             LEFT JOIN users u ON u.id = c.created_by
             LEFT JOIN (
                 SELECT communication_id,
                        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count,
                        SUM(CASE WHEN status = 'SENDING' THEN 1 ELSE 0 END) AS sending_count
                 FROM communication_recipients
                 WHERE communication_id = ?
                 GROUP BY communication_id
             ) rc ON rc.communication_id = c.id
             WHERE c.id = ?"
        );
        $stmt->execute([$id, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->mapCommunicationRow($row) : null;
    }

    /**
     * Fetches paginated communication history in a single aggregated query (no N+1).
     *
     * @return list<array<string, mixed>>
     */
    public function findRecent(CommunicationType $type, int $limit = 15, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    u.first_name AS author_first_name,
                    u.last_name AS author_last_name,
                    COALESCE(rc.pending_count, 0) AS pending_count,
                    COALESCE(rc.sending_count, 0) AS sending_count
             FROM communications c
             LEFT JOIN users u ON u.id = c.created_by
             LEFT JOIN (
                 SELECT communication_id,
                        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count,
                        SUM(CASE WHEN status = 'SENDING' THEN 1 ELSE 0 END) AS sending_count
                 FROM communication_recipients
                 GROUP BY communication_id
             ) rc ON rc.communication_id = c.id
             WHERE c.type = ?
             ORDER BY c.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $type->value, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->mapCommunicationRow(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countByType(CommunicationType $type): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM communications WHERE type = ?');
        $stmt->execute([$type->value]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Batch-loads failed recipients for a set of communication IDs in one query.
     *
     * @param list<int> $communicationIds
     * @return array<int, list<array{id: int, student_id: ?int, email: string, first_name: string, last_name: string, attempts: int, last_error: ?string}>>
     */
    public function findFailedRecipientsByCommunicationIds(array $communicationIds, int $limitPerCommunication = 25, int $offset = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $communicationIds), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT communication_id, id, student_id, email, first_name, last_name, attempts, last_error FROM (
                 SELECT communication_id, id, student_id, email, first_name, last_name, attempts, last_error,
                        ROW_NUMBER() OVER (PARTITION BY communication_id ORDER BY id) AS failure_index
                 FROM communication_recipients
                 WHERE communication_id IN ({$placeholders}) AND status = 'FAILED'
             ) AS failures
             WHERE failure_index > ? AND failure_index <= ?
             ORDER BY communication_id ASC, id ASC"
        );
        foreach ([...$ids, $offset, $offset + $limitPerCommunication] as $index => $value) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cid = (int) $row['communication_id'];
            $grouped[$cid][] = [
                'id' => (int) $row['id'],
                'student_id' => $row['student_id'] !== null ? (int) $row['student_id'] : null,
                'email' => (string) $row['email'],
                'first_name' => (string) $row['first_name'],
                'last_name' => (string) $row['last_name'],
                'attempts' => (int) $row['attempts'],
                'last_error' => $row['last_error'] !== null ? (string) $row['last_error'] : null,
            ];
        }

        return $grouped;
    }

    /** Finalizes unsent recipients that can no longer be dispatched after cancellation. */
    public function settleAbandonedCancellations(?int $communicationId, string $staleCutoff, string $now): void
    {
        $this->transaction(function () use ($communicationId, $staleCutoff, $now): void {
            $abandoned = "(status = 'PENDING' OR (status = 'SENDING' AND claimed_at <= ?))";
            $filter = $communicationId === null ? '' : ' AND communication_id = ?';
            $find = $this->pdo->prepare(
                "SELECT DISTINCT communication_id FROM communication_recipients
                 WHERE {$abandoned}{$filter}
                   AND communication_id IN (SELECT id FROM communications WHERE status = 'CANCELLED')"
            );
            $find->execute($communicationId === null ? [$staleCutoff] : [$staleCutoff, $communicationId]);
            $cancel = $this->pdo->prepare(
                "UPDATE communication_recipients SET status = 'CANCELLED', claimed_at = NULL
                 WHERE communication_id = ? AND {$abandoned}"
            );
            $summarize = $this->pdo->prepare(
                "UPDATE communications
                 SET cancelled_count = (SELECT COUNT(*) FROM communication_recipients WHERE communication_id = ? AND status = 'CANCELLED'),
                     completed_at = CASE WHEN NOT EXISTS (
                         SELECT 1 FROM communication_recipients WHERE communication_id = ? AND status IN ('PENDING', 'SENDING')
                     ) THEN COALESCE(completed_at, ?) ELSE completed_at END
                 WHERE id = ?"
            );
            foreach ($find->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $this->lockCommunication((int) $id);
                if (!$this->isCommunicationCancelled((int) $id, forUpdate: true)) {
                    continue;
                }
                $cancel->execute([$id, $staleCutoff]);
                $summarize->execute([$id, $id, $now, $id]);
            }
        });
    }

    /**
     * Atomically claims up to $limit due recipients (reclaiming stale SENDING claims first).
     *
     * @return list<array<string, mixed>>
     */
    public function claimDueRecipients(?int $communicationId, int $limit, string $now, string $staleCutoff): array
    {
        $limit = max(1, $limit);
        $isMysql = $this->isMysql();

        $this->pdo->beginTransaction();
        try {
            $filter = $communicationId === null ? '' : ' AND communication_id = ?';
            $reclaim = $this->pdo->prepare(
                "UPDATE communication_recipients
                 SET status = 'PENDING', claimed_at = NULL
                 WHERE status = 'SENDING' AND claimed_at <= ?{$filter}
                   AND communication_id IN (SELECT id FROM communications WHERE status <> 'CANCELLED')"
            );
            $reclaim->execute($communicationId === null ? [$staleCutoff] : [$staleCutoff, $communicationId]);

            $where = "r.status = 'PENDING' AND r.next_attempt_at <= ? AND c.status != 'CANCELLED'";
            $params = [$now];
            if ($communicationId !== null) {
                $where .= ' AND r.communication_id = ?';
                $params[] = $communicationId;
            }

            $lockSql = $isMysql ? ' FOR UPDATE' : '';
            $selectSql = "SELECT r.id, r.communication_id, r.student_id, r.email, r.first_name, r.last_name, r.attempts,
                                 c.type AS communication_type, c.subject, c.body
                          FROM communication_recipients r
                          INNER JOIN communications c ON c.id = r.communication_id
                          WHERE {$where}
                          ORDER BY r.id ASC
                          LIMIT {$limit}{$lockSql}";

            $selectStmt = $this->pdo->prepare($selectSql);
            $selectStmt->execute($params);
            $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rows === []) {
                $this->pdo->commit();
                return [];
            }

            $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $claimStmt = $this->pdo->prepare(
                "UPDATE communication_recipients
                 SET status = 'SENDING', claimed_at = ?
                 WHERE id IN ({$placeholders})"
            );
            $claimStmt->execute([$now, ...$ids]);

            $commIds = array_values(array_unique(array_map(
                static fn (array $r): int => (int) $r['communication_id'],
                $rows
            )));
            $commPlaceholders = implode(',', array_fill(0, count($commIds), '?'));
            $startStmt = $this->pdo->prepare(
                "UPDATE communications
                 SET status = 'SENDING', started_at = COALESCE(started_at, ?)
                 WHERE id IN ({$commPlaceholders}) AND status IN ('QUEUED', 'PAUSED_LIMIT')"
            );
            $startStmt->execute([$now, ...$commIds]);

            $this->pdo->commit();
            return $rows;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Returns unattempted claims to the queue or finishes their cancellation.
     *
     * @param list<array<string, mixed>> $recipients
     */
    public function releaseClaimedRecipients(array $recipients, string $claimedAt, string $now): void
    {
        $byCommunication = [];
        foreach ($recipients as $recipient) {
            $byCommunication[(int) $recipient['communication_id']][] = (int) $recipient['id'];
        }
        foreach ($byCommunication as $communicationId => $ids) {
            $this->transaction(function () use ($communicationId, $ids, $claimedAt, $now): void {
                $this->lockCommunication($communicationId);
                $cancelled = $this->isCommunicationCancelled($communicationId);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->pdo->prepare(
                    "UPDATE communication_recipients SET status = ?, claimed_at = NULL
                     WHERE id IN ({$placeholders}) AND status = 'SENDING' AND claimed_at = ?"
                );
                $stmt->execute([$cancelled ? 'CANCELLED' : 'PENDING', ...$ids, $claimedAt]);
                if ($cancelled && $stmt->rowCount() > 0) {
                    $this->recordTerminalOutcome($communicationId, 0, 0, $stmt->rowCount(), $now);
                }
            });
        }
    }

    public function isCommunicationCancelled(int $communicationId, bool $forUpdate = false): bool
    {
        $sql = 'SELECT status FROM communications WHERE id = ?';
        $stmt = $this->pdo->prepare($sql . ($forUpdate && $this->isMysql() ? ' FOR UPDATE' : ''));
        $stmt->execute([$communicationId]);
        return $stmt->fetchColumn() === CommunicationStatus::CANCELLED->value;
    }

    public function hasUnsettledRecipients(int $communicationId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM communication_recipients WHERE communication_id = ? AND status IN ('PENDING', 'SENDING') LIMIT 1");
        $stmt->execute([$communicationId]);
        return $stmt->fetchColumn() !== false;
    }

    /** Records a terminal outcome while the parent and recipient are locked. */
    public function recordTerminalOutcome(int $communicationId, int $sent, int $failed, int $cancelled, string $now): void
    {
        $open = $this->pdo->prepare(
            "SELECT 1 FROM communication_recipients
             WHERE communication_id = ? AND status IN ('PENDING', 'SENDING') LIMIT 1"
        );
        $open->execute([$communicationId]);
        if ($open->fetchColumn() !== false) {
            $this->pdo->prepare(
                'UPDATE communications SET sent_count = sent_count + ?, failed_count = failed_count + ?,
                     cancelled_count = cancelled_count + ? WHERE id = ?'
            )->execute([$sent, $failed, $cancelled, $communicationId]);
            return;
        }
        $this->pdo->prepare(
            "UPDATE communications SET
                 status = CASE
                     WHEN status = 'CANCELLED' THEN 'CANCELLED'
                     WHEN failed_count + ? > 0 THEN 'COMPLETED_WITH_ERRORS'
                     WHEN cancelled_count + ? > 0 THEN 'CANCELLED'
                     ELSE 'COMPLETED'
                 END,
                 sent_count = sent_count + ?, failed_count = failed_count + ?,
                 cancelled_count = cancelled_count + ?, completed_at = COALESCE(completed_at, ?)
             WHERE id = ?"
        )->execute([$failed, $cancelled, $sent, $failed, $cancelled, $now, $communicationId]);
    }

    public function markRecipientSent(int $recipientId, string $claimedAt, string $sentAt): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE communication_recipients
             SET status = 'SENT', sent_at = ?, claimed_at = NULL, last_error = NULL
             WHERE id = ? AND status = 'SENDING' AND claimed_at = ?"
        );
        $stmt->execute([$sentAt, $recipientId, $claimedAt]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Recipient claim lost.');
        }
    }

    public function markRecipientCancelled(int $recipientId, string $claimedAt, int $attempts, ?string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE communication_recipients
             SET status = 'CANCELLED', attempts = ?, claimed_at = NULL, last_error = ?
             WHERE id = ? AND status = 'SENDING' AND claimed_at = ?"
        );
        $stmt->execute([$attempts, $error === null ? null : $this->truncateError($error), $recipientId, $claimedAt]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Recipient claim lost.');
        }
    }

    public function markRecipientRetry(int $recipientId, string $claimedAt, int $attempts, string $nextAttemptAt, string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE communication_recipients
             SET status = 'PENDING', attempts = ?, next_attempt_at = ?, claimed_at = NULL, last_error = ?
             WHERE id = ? AND status = 'SENDING' AND claimed_at = ?"
        );
        $stmt->execute([$attempts, $nextAttemptAt, $this->truncateError($error), $recipientId, $claimedAt]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Recipient claim lost.');
        }
    }

    public function markRecipientFailed(int $recipientId, string $claimedAt, int $attempts, string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE communication_recipients
             SET status = 'FAILED', attempts = ?, claimed_at = NULL, last_error = ?
             WHERE id = ? AND status = 'SENDING' AND claimed_at = ?"
        );
        $stmt->execute([$attempts, $this->truncateError($error), $recipientId, $claimedAt]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Recipient claim lost.');
        }
    }

    /**
     * @return array{sent: int, failed: int, cancelled: int, pending: int, sending: int}
     */
    public function getRecipientStatusCounts(int $communicationId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) AS sent_count,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'SENDING' THEN 1 ELSE 0 END) AS sending_count
             FROM communication_recipients
             WHERE communication_id = ?"
        );
        $stmt->execute([$communicationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'sent' => (int) ($row['sent_count'] ?? 0),
            'failed' => (int) ($row['failed_count'] ?? 0),
            'cancelled' => (int) ($row['cancelled_count'] ?? 0),
            'pending' => (int) ($row['pending_count'] ?? 0),
            'sending' => (int) ($row['sending_count'] ?? 0),
        ];
    }

    public function updateCommunicationSummary(
        int $communicationId,
        CommunicationStatus $status,
        int $sent,
        int $failed,
        int $cancelled,
        ?string $completedAt
    ): void {
        if ($completedAt === null) {
            $stmt = $this->pdo->prepare(
                'UPDATE communications
                 SET status = ?, sent_count = ?, failed_count = ?, cancelled_count = ?, completed_at = NULL
                 WHERE id = ?'
            );
            $stmt->execute([$status->value, $sent, $failed, $cancelled, $communicationId]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE communications
                 SET status = ?, sent_count = ?, failed_count = ?, cancelled_count = ?, completed_at = COALESCE(completed_at, ?)
                 WHERE id = ?'
            );
            $stmt->execute([$status->value, $sent, $failed, $cancelled, $completedAt, $communicationId]);
        }
    }

    public function markActiveCommunicationsPausedForLimit(): void
    {
        $this->pdo->exec(
            "UPDATE communications
             SET status = 'PAUSED_LIMIT', completed_at = NULL
             WHERE status IN ('QUEUED', 'SENDING')
               AND id IN (SELECT DISTINCT communication_id FROM communication_recipients WHERE status = 'PENDING')"
        );
    }

    public function resetFailedRecipientsToPending(int $communicationId, string $now): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE communication_recipients
             SET status = 'PENDING', attempts = 0, next_attempt_at = ?, claimed_at = NULL, last_error = NULL
             WHERE communication_id = ? AND status = 'FAILED'"
        );
        $stmt->execute([$now, $communicationId]);
        return $stmt->rowCount();
    }

    public function reopenCommunication(int $communicationId): void
    {
        $this->pdo->prepare("UPDATE communications SET status = 'QUEUED', completed_at = NULL WHERE id = ?")
            ->execute([$communicationId]);
    }

    public function markPendingRecipientsCancelled(int $communicationId): int
    {
        $this->pdo->prepare(
            "UPDATE communications SET status = 'CANCELLED'
             WHERE id = ? AND EXISTS (
                 SELECT 1 FROM communication_recipients WHERE communication_id = ? AND status IN ('PENDING', 'SENDING')
             )"
        )->execute([$communicationId, $communicationId]);
        $stmt = $this->pdo->prepare(
            "UPDATE communication_recipients SET status = 'CANCELLED', claimed_at = NULL
             WHERE communication_id = ? AND status = 'PENDING'"
        );
        $stmt->execute([$communicationId]);
        return $stmt->rowCount();
    }

    public function countDueRecipients(int $communicationId, string $now): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM communication_recipients
             WHERE communication_id = ? AND status = 'PENDING' AND next_attempt_at <= ?"
        );
        $stmt->execute([$communicationId, $now]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapCommunicationRow(array $row): array
    {
        $authorName = trim(((string) ($row['author_first_name'] ?? '')) . ' ' . ((string) ($row['author_last_name'] ?? '')));
        $decodedFilters = json_decode((string) ($row['filters_json'] ?? '{}'), true);

        return [
            'id' => (int) $row['id'],
            'type' => (string) $row['type'],
            'subject' => (string) $row['subject'],
            'body' => (string) $row['body'],
            'filters' => is_array($decodedFilters) ? $decodedFilters : [],
            'status' => (string) $row['status'],
            'total_recipients' => (int) $row['total_recipients'],
            'sent_count' => (int) $row['sent_count'],
            'failed_count' => (int) $row['failed_count'],
            'cancelled_count' => (int) $row['cancelled_count'],
            'pending_count' => (int) ($row['pending_count'] ?? 0),
            'sending_count' => (int) ($row['sending_count'] ?? 0),
            'created_by' => $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'author_name' => $authorName !== '' ? $authorName : '—',
            'created_at' => (string) $row['created_at'],
            'started_at' => $row['started_at'] !== null ? (string) $row['started_at'] : null,
            'completed_at' => $row['completed_at'] !== null ? (string) $row['completed_at'] : null,
        ];
    }

    /**
     * @param array{status_values: list<string>, institution: string, consent: string, kyc_status: string} $querySpec
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildAudienceWhere(array $querySpec): array
    {
        $conditions = [
            'deleted_at IS NULL',
            $this->validEmailSql,
        ];
        $params = [];

        if ($querySpec['status_values'] !== []) {
            $placeholders = implode(',', array_fill(0, count($querySpec['status_values']), '?'));
            $conditions[] = "status IN ({$placeholders})";
            array_push($params, ...$querySpec['status_values']);
        }

        if ($querySpec['institution'] !== '') {
            $conditions[] = 'institution = ?';
            $params[] = $querySpec['institution'];
        }

        if ($querySpec['consent'] === 'consented') {
            $conditions[] = 'consent_privacy = 1';
        } elseif ($querySpec['consent'] === 'not_consented') {
            $conditions[] = '(consent_privacy = 0 OR consent_privacy IS NULL)';
        }

        if ($querySpec['kyc_status'] === 'exclude_rejected') {
            $conditions[] = "kyc_status != 'REJECTED'";
        } elseif ($querySpec['kyc_status'] !== 'all') {
            $conditions[] = 'kyc_status = ?';
            $params[] = $querySpec['kyc_status'];
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    private function truncateError(string $error): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $error) ?? $error);
        if (strlen($clean) > 500) {
            return substr($clean, 0, 497) . '...';
        }
        return $clean;
    }
}
