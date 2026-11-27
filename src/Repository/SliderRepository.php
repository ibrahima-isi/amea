<?php

namespace Amea\Repository;

use Amea\Cache\AppCache;
use Amea\Core\Container;
use PDO;

class SliderRepository
{
    private const CACHE_TTL = 300;

    public function __construct(
        private PDO $conn,
        private ?AppCache $cache = null
    ) {}

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

    public function findByPath(string $path): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM slider_images WHERE image_path = ? ORDER BY id LIMIT 1');
        $stmt->execute([$path]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
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
        if (!$this->conn->inTransaction()) {
            $this->resolveCache()->invalidateNamespace(AppCache::NS_SLIDER);
        }
        return (int)$this->conn->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->conn->prepare("DELETE FROM slider_images WHERE id = ?");
        $stmt->execute([$id]);
        if (!$this->conn->inTransaction()) {
            $this->resolveCache()->invalidateNamespace(AppCache::NS_SLIDER);
        }
    }

    public function getActiveImages(): array
    {
        return $this->resolveCache()->remember(AppCache::NS_SLIDER, 'active_images', self::CACHE_TTL, function (): array {
            $stmt = $this->conn->query("SELECT * FROM slider_images ORDER BY display_order ASC, id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    private function resolveCache(): AppCache
    {
        return $this->cache ?? Container::cacheForConnection($this->conn);
    }
}
