<?php

declare(strict_types=1);

namespace Amea\Repository;

use PDO;

/**
 * Pure SQL persistence boundary for member consent status and deletion requests.
 */
class ConsentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Single aggregate query for Consent & RGPD overview cards.
     *
     * @return array{total_active: int, consented: int, not_consented: int, reminders_sent: int, deletion_requests: int}
     */
    public function getStats(): array
    {
        $row = $this->pdo->query(
            'SELECT
                COUNT(*) AS total_active,
                SUM(CASE WHEN consent_privacy = 1 THEN 1 ELSE 0 END) AS consented,
                SUM(CASE WHEN consent_privacy = 0 OR consent_privacy IS NULL THEN 1 ELSE 0 END) AS not_consented,
                SUM(CASE WHEN cgu_reminder_sent_at IS NOT NULL THEN 1 ELSE 0 END) AS reminders_sent,
                SUM(CASE WHEN deletion_requested_at IS NOT NULL THEN 1 ELSE 0 END) AS deletion_requests
             FROM students
             WHERE deleted_at IS NULL'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_active' => (int) ($row['total_active'] ?? 0),
            'consented' => (int) ($row['consented'] ?? 0),
            'not_consented' => (int) ($row['not_consented'] ?? 0),
            'reminders_sent' => (int) ($row['reminders_sent'] ?? 0),
            'deletion_requests' => (int) ($row['deletion_requests'] ?? 0),
        ];
    }

    /**
     * Paginated list of active members awaiting CGU consent.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function findPendingConsentPaginated(int $page, int $perPage, string $search = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ['deleted_at IS NULL', '(consent_privacy = 0 OR consent_privacy IS NULL)'];
        $params = [];

        $search = trim($search);
        if ($search !== '') {
            $conditions[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        $where = ' WHERE ' . implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM students{$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->pdo->prepare(
            "SELECT id, first_name, last_name, email, registration_date, cgu_reminder_sent_at, consent_refused_at
             FROM students{$where}
             ORDER BY last_name ASC, first_name ASC
             LIMIT ? OFFSET ?"
        );
        $i = 1;
        foreach ($params as $p) {
            $dataStmt->bindValue($i++, $p, PDO::PARAM_STR);
        }
        $dataStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($i, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Server-side AJAX search for manual member selection (reachable active members only).
     * Uses the same strict SQL email validity predicate as bulk communications.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function searchSelectableMembers(string $query, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ['deleted_at IS NULL', CommunicationRepository::validEmailPredicate($this->pdo)];
        $params = [];

        $query = trim($query);
        if ($query !== '') {
            $conditions[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like);
        }

        $where = ' WHERE ' . implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM students{$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->pdo->prepare(
            "SELECT id, first_name, last_name, TRIM(email) AS email, consent_privacy, cgu_reminder_sent_at
             FROM students{$where}
             ORDER BY last_name ASC, first_name ASC
             LIMIT ? OFFSET ?"
        );
        $i = 1;
        foreach ($params as $p) {
            $dataStmt->bindValue($i++, $p, PDO::PARAM_STR);
        }
        $dataStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($i, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Active (non-trashed) members who refused terms and requested data deletion.
     *
     * @return list<array<string, mixed>>
     */
    public function findDeletionRequests(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, first_name, last_name, email, deletion_requested_at, consent_refused_at
             FROM students
             WHERE deleted_at IS NULL AND deletion_requested_at IS NOT NULL
             ORDER BY deletion_requested_at DESC, id DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Eligible active members with valid emails for a consent reminder scope.
     *
     * @param list<int> $selectedIds
     * @return list<array{id: int, first_name: string, last_name: string, email: string}>
     */
    public function findReminderTargets(string $scope, array $selectedIds = []): array
    {
        $conditions = ['deleted_at IS NULL', CommunicationRepository::validEmailPredicate($this->pdo)];
        $params = [];

        if ($scope === 'selected') {
            $cleanIds = array_values(array_unique(array_filter(
                array_map('intval', $selectedIds),
                static fn (int $id): bool => $id > 0
            )));
            if ($cleanIds === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $conditions[] = "id IN ({$placeholders})";
            $params = $cleanIds;
        } elseif ($scope !== 'all') {
            $conditions[] = '(consent_privacy = 0 OR consent_privacy IS NULL)';
        }

        $sql = 'SELECT id, first_name, last_name, TRIM(email) AS email FROM students WHERE '
            . implode(' AND ', $conditions) . ' ORDER BY id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $targets = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $targets[] = [
                'id' => (int) $row['id'],
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }

        return $targets;
    }

    public function findConsentLinkState(int $studentId, string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cgu_token FROM students WHERE id = ? AND deleted_at IS NULL AND HEX(TRIM(email)) = HEX(?)'
        );
        $stmt->execute([$studentId, $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function markReminderSent(int $studentId, string $sentAt, string $email, string $token, ?string $previousToken): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE students SET cgu_reminder_sent_at = ?,
                 cgu_token = CASE WHEN HEX(TRIM(email)) = HEX(?)
                     AND (cgu_token = ? OR (cgu_token IS NULL AND ? IS NULL)) THEN ? ELSE cgu_token END
             WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$sentAt, $email, $previousToken, $previousToken, $token, $studentId]);
    }

    public function recordAcceptance(int $studentId, string $token): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE students
             SET consent_privacy = 1, consent_refused_at = NULL, deletion_requested_at = NULL, cgu_token = NULL
             WHERE id = ? AND deleted_at IS NULL AND cgu_token = ?'
        );
        $stmt->execute([$studentId, $token]);
        return $stmt->rowCount() > 0;
    }

    public function recordRefusal(int $studentId, string $token, string $refusedAt): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE students
             SET consent_privacy = 0, consent_refused_at = ?
             WHERE id = ? AND deleted_at IS NULL AND cgu_token = ?'
        );
        $stmt->execute([$refusedAt, $studentId, $token]);
        return $stmt->rowCount() > 0;
    }

    public function recordDeletionRequest(int $studentId, string $token, string $requestedAt): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE students
             SET deletion_requested_at = ?, cgu_token = NULL
             WHERE id = ? AND deleted_at IS NULL AND cgu_token = ?'
        );
        $stmt->execute([$requestedAt, $studentId, $token]);
        return $stmt->rowCount() > 0;
    }
}
