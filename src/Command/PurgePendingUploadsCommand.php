<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Config\Environment;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PurgePendingUploadsCommand
{
    public function run(string $projectRoot): int
    {
        Environment::load($projectRoot);

        $pendingDir = $projectRoot . '/uploads/students/pending';
        if (!is_dir($pendingDir)) {
            JobLog::write('No pending-uploads directory; nothing to do.');
            return 0;
        }

        $ttlHours = max(1, (int)($_ENV['PENDING_UPLOAD_TTL_HOURS'] ?? getenv('PENDING_UPLOAD_TTL_HOURS') ?: 24));
        $cutoff   = time() - ($ttlHours * 3600);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pendingDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $deleted = 0;
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getMTime() < $cutoff) {
                if (@unlink($file->getPathname())) {
                    $deleted++;
                    JobLog::write('Removed stale staged file: ' . $file->getFilename());
                }
            }
        }

        JobLog::write("Pending-uploads sweep complete: removed={$deleted} (TTL {$ttlHours}h).");
        return 0;
    }
}
