<?php
namespace Amea\Service;

use Amea\Repository\StudentRepository;

class DocumentReconcileService
{
    public function __construct(
        private ?StudentRepository $repo,
        private string             $projectRoot,
        private ?\Amea\Storage\Storage $storage = null
    ) {}

    public function dbPathExists(string $dbPath): bool
    {
        if ($dbPath === '') {
            return false;
        }

        return $this->storage()->stat($this->normalizeRelativePath($dbPath)) !== null;
    }

    public function findAlternativePath(string $dbPath, array $searchDirs): ?string
    {
        if ($dbPath === '') {
            return null;
        }

        $filename = basename($dbPath);
        $normalizedDbPath = $this->normalizeRelativePath($dbPath);

        $matches = [];
        foreach ($searchDirs as $dir) {
            $relativePath = $this->normalizeRelativePath(
                $this->normalizeDirectory($dir) . '/' . $filename
            );

            if ($relativePath === $normalizedDbPath) {
                continue;
            }

            if ($this->dbPathExists($relativePath)) {
                $matches[$relativePath] = true;
            }
        }

        return count($matches) === 1 ? array_key_first($matches) : null;
    }

    /** @return string[] relative paths */
    public function scanUploadFiles(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            $prefix = $this->normalizeDirectory($dir) . '/';
            $cursor = null;
            do {
                $page = $this->storage()->listPage($prefix, $cursor, 1000);
                foreach ($page['objects'] as $object) {
                    $files[$object['key']] = true;
                }
                $cursor = $page['cursor'];
            } while ($cursor !== null);
        }
        $keys = array_keys($files);
        sort($keys, SORT_STRING);
        return $keys;
    }

    /** @return string[] files on disk not referenced in DB */
    public function findOrphanedFiles(array $uploadFiles, array $dbPaths): array
    {
        $references = array_map(fn ($path) => $this->normalizeRelativePath($path), array_filter($dbPaths));
        return array_values(array_filter($uploadFiles, fn ($path) => !in_array($this->normalizeRelativePath($path), $references, true)));
    }

    /**
     * @return array{status: string, found_at: string|null}
     */
    public function classifyDocument(?string $dbPath, array $searchDirs): array
    {
        if (empty($dbPath)) {
            return ['status' => 'null', 'found_at' => null];
        }
        if ($this->dbPathExists($dbPath)) {
            return ['status' => 'ok', 'found_at' => $dbPath];
        }
        $alt = $this->findAlternativePath($dbPath, $searchDirs);
        if ($alt !== null) {
            return ['status' => 'fixable', 'found_at' => $alt];
        }
        return ['status' => 'missing', 'found_at' => null];
    }

    private function storage(): \Amea\Storage\Storage
    {
        return $this->storage ??= \Amea\Storage\StorageFactory::fromEnvironment($this->projectRoot);
    }

    private function normalizeDirectory(string $dir): string
    {
        return trim($dir, '/');
    }

    private function normalizeRelativePath(string $path): string
    {
        return ltrim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }
}
