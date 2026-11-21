<?php
namespace Amea\Service;

use Amea\Repository\FileOperationRepository;
use Amea\Storage\Storage;

/** Expire staged objects using their original creation metadata. */
final class PendingUploadCleanupService
{
    public function __construct(private \PDO $db, private Storage $storage, private FileLifecycleService $lifecycle) {}

    public function sweep(int $ttlHours = 24, ?int $now = null): int
    {
        $cutoff = ($now ?? time()) - max(1, $ttlHours) * 3600;
        $cursor = null;
        do {
            $page = $this->storage->listPage('uploads/students/pending/', $cursor, 1000);
            $this->db->beginTransaction();
            try {
                (new FileOperationRepository($this->db))->lockMembers();
                foreach ($page['objects'] as $object) {
                    // Recheck under the publication/cleanup lock after listing.
                    $metadata = $this->storage->stat($object['key']);
                    if ($metadata !== null && $metadata['createdAt'] < $cutoff) {
                        $id = 'pending-' . hash('sha256', $object['key'] . ':' . $metadata['createdAt']);
                        $this->lifecycle->scheduleDeletion($id, [$object['key']], 'pending-expiry-' . $metadata['createdAt']);
                    }
                }
                $this->db->commit();
            } catch (\Throwable $error) {
                if ($this->db->inTransaction()) { $this->db->rollBack(); }
                throw $error;
            }
            $cursor = $page['cursor'];
        } while ($cursor !== null);
        // The hourly path never continues a permanent member purge.
        return $this->lifecycle->retryDeletions(1000, false);
    }
}
