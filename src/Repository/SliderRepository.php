<?php

namespace Amea\Repository;

use PDO;

class SliderRepository
{
    public function __construct(private PDO $conn) {}

    public function findAll(): array
    {
        return $this->conn->query("SELECT * FROM slider_images ORDER BY display_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM slider_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO slider_images (image_path, caption, display_order) VALUES (:image_path, :caption, :display_order)"
        );
        $stmt->execute([
            ':image_path'    => $data['image_path'],
            ':caption'       => $data['caption'] ?? null,
            ':display_order' => $data['display_order'] ?? 0,
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->conn->prepare("DELETE FROM slider_images WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function getActiveImages(): array
    {
        $stmt = $this->conn->query("SELECT * FROM slider_images ORDER BY display_order ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
