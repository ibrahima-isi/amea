<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Config\Environment;
use Amea\Core\Container;
use Amea\Core\FileUploader;
use Amea\Repository\StudentRepository;
use Throwable;

final class PurgeTrashedStudentsCommand
{
    public function run(string $projectRoot): int
    {
        Environment::load($projectRoot);

        $retentionDays = max(1, (int)($_ENV['TRASH_RETENTION_DAYS'] ?? getenv('TRASH_RETENTION_DAYS') ?: 30));

        try {
            $pdo        = Container::db();
            $repo       = new StudentRepository($pdo);
            $uploader   = new FileUploader($projectRoot);
        } catch (Throwable $e) {
            JobLog::write('FATAL: cannot initialise database: ' . $e->getMessage());
            return 1;
        }

        $purgeable = $repo->findPurgeable($retentionDays);

        if ($purgeable === []) {
            JobLog::write("Nothing to purge (retention {$retentionDays} days).");
            return 0;
        }

        $deleted = 0;
        $failed  = 0;

        foreach ($purgeable as $student) {
            $id = $student->getId();
            try {
                if ($student->getIdentityDocument()) {
                    $uploader->safeDelete($student->getIdentityDocument());
                }
                if ($student->getCvPath()) {
                    $uploader->safeDelete($student->getCvPath());
                }
                if ($repo->forceDelete($id)) {
                    $deleted++;
                    JobLog::write("Purged student #{$id} ({$student->getEmail()}), trashed at {$student->getDeletedAt()}.");
                } else {
                    $failed++;
                    JobLog::write("WARN: could not purge student #{$id} (no longer trashed?).");
                }
            } catch (Throwable $e) {
                $failed++;
                JobLog::write("ERROR purging student #{$id}: " . $e->getMessage());
            }
        }

        JobLog::write("Purge complete: deleted={$deleted} failed={$failed} retention={$retentionDays}d.");
        return 0;
    }
}
