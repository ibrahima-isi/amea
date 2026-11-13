<?php
namespace Amea\Service;

use Amea\Storage\Storage;
use Amea\Repository\FileOperationRepository;
use Amea\Repository\StudentRepository;
use Amea\Storage\StorageException;

/** Coordinates verified member objects with their database references. */
class FileLifecycleService
{
    public function __construct(private \PDO $db, private Storage $storage, private string $backend) {}

    /** Copy every document before publishing any reference. Sources survive retries and rollback. */
    public function prepareFinalization(array $stagedToFinal): array
    {
        foreach ($stagedToFinal as $source => $destination) {
            $this->storage->copy($source, $destination);
            $before = $this->fingerprint($source);
            $after = $this->fingerprint($destination);
            if ($before !== $after) {
                throw new StorageException('The finalized document differs from its staged source.');
            }
        }
        return $stagedToFinal;
    }

    /** Null document fields mean no replacement, resolved from the locked row. */
    public function saveMember(array $data, array $countries, ?int $id = null, ?string $correctionToken = null): int
    {
        $mapping = [];
        foreach (['identity_document' => 'uploads/students', 'cv_path' => 'uploads/students/cvs'] as $field => $directory) {
            if (!empty($data[$field])) {
                $source = $data[$field];
                if (!str_starts_with($source, 'uploads/students/pending/')) {
                    throw new StorageException('Replacement documents must be staged.');
                }
                $mapping[$source] = $directory . '/' . basename($source);
            }
        }
        $this->prepareFinalization($mapping);
        foreach (['identity_document', 'cv_path'] as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $mapping[$data[$field]];
            }
        }
        $operations = new FileOperationRepository($this->db);
        $repo = new StudentRepository($this->db);
        $this->db->beginTransaction();
        try {
            $operations->lockMembers();
            $current = $id === null ? null : $operations->lockStudent($id);
            if ($id !== null && (!$current || $current['deleted_at'] !== null)) {
                throw new \RuntimeException('Le membre est indisponible.');
            }
            if ($correctionToken !== null && ($current['review_token'] !== $correctionToken || $current['kyc_status'] !== 'NEEDS_CLARIFICATION')) {
                throw new \RuntimeException('Lien de correction invalide ou expiré.');
            }
            foreach (['identity_document', 'cv_path'] as $field) {
                $data[$field] ??= $current[$field] ?? null;
            }
            if ($id === null) {
                $id = $repo->save($data);
                $repo->update($id, ['is_locked' => 1]);
            } else {
                $repo->update($id, $data);
            }
            foreach (['institution' => 'ensureInstitutionExists', 'study_field' => 'ensureStudyFieldExists', 'study_level' => 'ensureStudyLevelExists'] as $field => $method) {
                if (!empty($data[$field])) {
                    $repo->$method($data[$field]);
                }
            }
            if ($countries !== []) {
                $repo->replaceStudentCountries($id, $countries);
            }
            $keys = array_keys($mapping);
            foreach (['identity_document', 'cv_path'] as $field) {
                if (!empty($current[$field]) && $current[$field] !== $data[$field]) {
                    $keys[] = $current[$field];
                }
            }
            if ($keys !== []) {
                $operations->enqueue(bin2hex(random_bytes(16)), $this->backend, 'replacement', $keys, $current ?? ['id' => $id]);
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Sources and verified unreferenced destinations remain recoverable.
            throw $error;
        }
    }

    public function scheduleDeletion(string $operationId, array $keys, string $reason): void
    {
        (new FileOperationRepository($this->db))->enqueue($operationId, $this->backend, $reason, $keys);
    }

    public function retryDeletions(int $limit, bool $includePurge = true): int
    {
        if ($this->db->inTransaction()) {
            throw new \LogicException('Cleanup must follow the reference commit.');
        }
        if ($limit < 1) {
            return 0;
        }
        $operations = new FileOperationRepository($this->db);
        $completed = 0;
        foreach ($operations->pending($this->backend, $limit, $includePurge) as $id) {
            $completed += (int)$this->retryDeletion($id);
        }
        return $completed;
    }

    public function hasPendingDeletions(bool $includePurge = true): bool
    {
        return (new FileOperationRepository($this->db))->pending($this->backend, 1, $includePurge) !== [];
    }

    private function retryDeletion(string $id): bool
    {
        $operations = new FileOperationRepository($this->db);
        try {
            $this->db->beginTransaction();
            $operations->lockMembers();
            $operation = $operations->find($id);
            if ($operation === null || $operation['backend'] !== $this->backend) {
                $this->db->commit();
                return false;
            }
            $purge = str_starts_with($operation['reason'], 'purge-');
            $studentId = $purge ? (int)$operation['student_id'] : null;
            if ($purge) {
                $current = $operations->lockStudent($studentId);
                if ($current !== null && ($current['deleted_at'] === null
                    || $current['deleted_at'] !== $operation['expected_deleted_at']
                    || $current['identity_document'] !== $operation['expected_identity']
                    || $current['cv_path'] !== $operation['expected_cv'] || !(int)$operation['started'])) {
                    throw new \RuntimeException('Purge eligibility changed.');
                }
            }
            $failures = [];
            foreach (json_decode($operation['keys_json'], true, flags: JSON_THROW_ON_ERROR) as $key) {
                try {
                    if (str_starts_with($operation['reason'], 'pending-expiry-')) {
                        $metadata = $this->storage->stat($key);
                        if ($metadata !== null && $metadata['createdAt'] !== (int)substr($operation['reason'], strlen('pending-expiry-'))) {
                            continue;
                        }
                    }
                    if (!$operations->referenced($key, $studentId)) {
                        $this->storage->delete($key);
                    }
                } catch (StorageException $error) {
                    $failures[$key] = $error->getMessage();
                }
            }
            if ($failures !== []) {
                throw new StorageException(json_encode($failures, JSON_THROW_ON_ERROR));
            }
            if ($purge) {
                (new StudentRepository($this->db))->forceDelete($studentId);
            }
            $operations->remove($id);
            $this->db->commit();
            return true;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $operations->fail($id, $error);
            return false;
        }
    }

    /** Null retention is the existing explicit manual permanent-delete action. */
    public function purgeMember(int $id, ?int $retentionDays = null): bool
    {
        $operations = new FileOperationRepository($this->db);
        $operationId = 'purge-' . $id;
        $this->db->beginTransaction();
        try {
            $operations->lockMembers();
            $current = $operations->lockStudent($id);
            if (!$current || $current['deleted_at'] === null) {
                $this->db->commit();
                return false;
            }
            $existing = $operations->find($operationId);
            if ($existing === null) {
                if ($retentionDays !== null && $current['deleted_at'] >= date('Y-m-d H:i:s', time() - max(1, $retentionDays) * 86400)) {
                    $this->db->commit();
                    return false;
                }
                $keys = array_values(array_filter([$current['identity_document'], $current['cv_path']]));
                $operations->enqueue($operationId, $this->backend, $retentionDays === null ? 'purge-manual' : 'purge-retention-' . $retentionDays, $keys, $current);
            } elseif ($existing['backend'] !== $this->backend) {
                throw new \RuntimeException('Purge belongs to another storage backend.');
            }
            // Commit the irreversible boundary before touching any object. A crash
            // cannot reopen restoration after the first successful deletion.
            $operations->start($operationId);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
        $this->retryDeletion($operationId);
        return $operations->find($operationId) === null;
    }

    public function restoreMember(int $id): bool
    {
        $operations = new FileOperationRepository($this->db);
        $this->db->beginTransaction();
        try {
            $operations->lockMembers();
            $current = $operations->lockStudent($id);
            if (!$current || $current['deleted_at'] === null || $operations->find('purge-' . $id) !== null) {
                $this->db->commit();
                return false;
            }
            $restored = (new StudentRepository($this->db))->restore($id);
            $this->db->commit();
            return $restored;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function fingerprint(string $key): array
    {
        $metadata = $this->storage->stat($key);
        if ($metadata === null) {
            throw new StorageException('The document is missing.');
        }
        $stream = $this->storage->read($key);
        try {
            $hash = hash_init('sha256');
            $size = hash_update_stream($hash, $stream);
            if ($size !== $metadata['size'] || !feof($stream)) {
                throw new StorageException('The document stream is incomplete.');
            }
            $digest = hash_final($hash);
            if ($metadata['sha256'] !== null && $metadata['sha256'] !== $digest) {
                throw new StorageException('The document checksum is invalid.');
            }
            return ['size' => $size, 'sha256' => $digest];
        } finally {
            fclose($stream);
        }
    }
}
