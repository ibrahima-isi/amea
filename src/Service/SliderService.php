<?php
namespace Amea\Service;

use Amea\Repository\FileOperationRepository;
use Amea\Repository\SliderRepository;
use Amea\Storage\Storage;

/** Publish verified carousel references and commit durable removal intents. */
final class SliderService
{
    private SliderRepository $images;

    public function __construct(private \PDO $db, private Storage $storage, private FileLifecycleService $lifecycle)
    {
        $this->images = new SliderRepository($db);
    }

    public function publish(string $key, string $caption, int $displayOrder): int
    {
        $this->db->beginTransaction();
        try {
            (new FileOperationRepository($this->db))->lockMembers();
            $image = (new MemberDocumentService($this->storage))->open($key, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
            fclose($image['stream']);
            $id = $this->images->save(['image_path' => $key, 'caption' => $caption, 'display_order' => $displayOrder]);
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            // Unreferenced verified objects remain available for reconciliation.
            throw $error;
        }
    }

    public function remove(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            (new FileOperationRepository($this->db))->lockMembers();
            $image = $this->images->findById($id);
            if ($image === null) {
                $this->db->commit();
                return false;
            }
            $this->images->delete($id);
            $this->lifecycle->scheduleDeletion(bin2hex(random_bytes(16)), [$image['image_path']], 'slider-removal');
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $error;
        }
        $this->lifecycle->retryDeletions(100);
        return true;
    }
}
