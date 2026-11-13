<?php
namespace Amea\Repository;

/** Durable member-file cleanup, sharing the caller's PDO transaction. */
class FileOperationRepository
{
    public function __construct(private \PDO $db) {}

    public function lockMembers(): void
    {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('Member file operations require a transaction.');
        }
        // All member reference publications, cleanup and restoration take this
        // row first. It also protects keys shared by different member rows.
        $suffix = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        if ($this->db->query('SELECT id FROM member_file_lock WHERE id = 1' . $suffix)->fetchColumn() === false) {
            throw new \RuntimeException('The member file lock migration is incomplete.');
        }
    }

    public function lockStudent(int $id): ?array
    {
        $suffix = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare('SELECT * FROM students WHERE id = ?' . $suffix);
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function enqueue(string $id, string $backend, string $reason, array $keys, ?array $student = null): void
    {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('Deletion intents require the caller transaction.');
        }
        $this->lockMembers();
        $existing = $this->find($id);
        $data = [
            $backend, $reason,
            json_encode(array_values(array_unique($keys)), JSON_THROW_ON_ERROR),
            $student['id'] ?? null, $student['identity_document'] ?? null,
            $student['cv_path'] ?? null, $student['deleted_at'] ?? null,
        ];
        if ($existing !== null) {
            $before = array_map(fn ($key) => $existing[$key], ['backend', 'reason', 'keys_json', 'student_id', 'expected_identity', 'expected_cv', 'expected_deleted_at']);
            if ($before != $data) {
                throw new \LogicException('A deletion operation cannot change its identity or objects.');
            }
            return;
        }
        $this->db->prepare(
            'INSERT INTO file_operations
                (operation_id, backend, reason, keys_json, student_id, expected_identity, expected_cv, expected_deleted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, ...$data]);
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM file_operations WHERE operation_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function pending(string $backend, int $limit, bool $includePurge = true): array
    {
        $filter = $includePurge ? '' : " AND reason NOT LIKE 'purge-%'";
        $stmt = $this->db->prepare('SELECT operation_id FROM file_operations WHERE backend = ?' . $filter . ' ORDER BY created_at, operation_id LIMIT ?');
        $stmt->bindValue(1, $backend);
        $stmt->bindValue(2, max(1, min(1000, $limit)), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function start(string $id): void
    {
        $this->db->prepare('UPDATE file_operations SET started = 1 WHERE operation_id = ?')->execute([$id]);
    }

    public function remove(string $id): void
    {
        $this->db->prepare('DELETE FROM file_operations WHERE operation_id = ?')->execute([$id]);
    }

    public function fail(string $id, \Throwable $error): void
    {
        $this->db->prepare('UPDATE file_operations SET attempts = attempts + 1, last_error = ? WHERE operation_id = ?')->execute([$error->getMessage(), $id]);
    }

    public function referenced(string $key, ?int $excludingStudent = null): bool
    {
        // Historical journal covers are retained even after their last reference is removed.
        if (str_starts_with($key, 'uploads/journal/covers/')) {
            return true;
        }
        foreach (['SELECT 1 FROM slider_images WHERE image_path = ? LIMIT 1',
            'SELECT 1 FROM journal_posts WHERE cover_path = ? LIMIT 1'] as $query) {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$key]);
            if ($stmt->fetchColumn()) { return true; }
        }
        $sql = 'SELECT 1 FROM students WHERE (identity_document = ? OR cv_path = ?)';
        $args = [$key, $key];
        if ($excludingStudent !== null) {
            $sql .= ' AND id <> ?';
            $args[] = $excludingStudent;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($args);
        return (bool)$stmt->fetchColumn();
    }
}
