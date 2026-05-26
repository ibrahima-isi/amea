<?php
namespace Amea\Service;

use Amea\Repository\StudentRepository;

class DocumentReconcileService
{
    public function __construct(
        private ?StudentRepository $repo,
        private string             $projectRoot
    ) {}

    public function dbPathExists(string $dbPath): bool
    {
        if ($dbPath === '') {
            return false;
        }

        return is_file($this->absolutePath($dbPath));
    }

    public function findAlternativePath(string $dbPath, array $searchDirs): ?string
    {
        if ($dbPath === '') {
            return null;
        }

        $filename = basename($dbPath);
        $normalizedDbPath = $this->normalizeRelativePath($dbPath);

        foreach ($searchDirs as $dir) {
            $relativePath = $this->normalizeRelativePath(
                $this->normalizeDirectory($dir) . '/' . $filename
            );

            if ($relativePath === $normalizedDbPath) {
                continue;
            }

            if (is_file($this->absolutePath($relativePath))) {
                return $relativePath;
            }
        }

        return null;
    }

    /** @return string[] relative paths */
    public function scanUploadFiles(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            $normalizedDir = $this->normalizeDirectory($dir);
            $absolute = $this->absolutePath($normalizedDir);

            if (!is_dir($absolute)) {
                continue;
            }

            foreach (scandir($absolute) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $relativePath = $normalizedDir . '/' . $entry;
                if (is_file($this->absolutePath($relativePath))) {
                    $files[] = $relativePath;
                }
            }
        }

        return $files;
    }

    /** @return string[] files on disk not referenced in DB */
    public function findOrphanedFiles(array $uploadFiles, array $dbPaths): array
    {
        $dbBasenames = array_map('basename', array_filter($dbPaths, fn($p) => !empty($p)));
        return array_values(array_filter(
            $uploadFiles,
            fn($f) => !in_array(basename($f), $dbBasenames, true)
        ));
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

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->projectRoot, '/') . '/' . $this->normalizeRelativePath($relativePath);
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
