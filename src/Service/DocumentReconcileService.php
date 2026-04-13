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
        $absolute = realpath(rtrim($this->projectRoot, '/') . '/' . ltrim($dbPath, '/'));
        return $absolute !== false && is_file($absolute);
    }

    public function findAlternativePath(string $dbPath, array $searchDirs): ?string
    {
        $filename = basename($dbPath);
        foreach ($searchDirs as $dir) {
            $candidate = rtrim($this->projectRoot, '/') . '/' . ltrim($dir, '/') . '/' . $filename;
            if (is_file($candidate)) {
                return ltrim($dir, '/') . '/' . $filename;
            }
        }
        return null;
    }

    /** @return string[] relative paths */
    public function scanUploadFiles(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            $absolute = rtrim($this->projectRoot, '/') . '/' . ltrim($dir, '/');
            if (!is_dir($absolute)) continue;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = ltrim($dir, '/') . '/' . $file->getFilename();
                }
            }
        }
        return $files;
    }

    /** @return string[] files on disk not referenced in DB */
    public function findOrphanedFiles(array $uploadFiles, array $dbPaths): array
    {
        $dbBasenames = array_map('basename', $dbPaths);
        return array_filter(
            $uploadFiles,
            fn($f) => !in_array(basename($f), $dbBasenames, true)
        );
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
}
