<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Config\Environment;
use Amea\Core\Container;
use Amea\Core\FileWriteGate;
use Amea\Repository\StudentRepository;
use Throwable;

final class PurgeTrashedStudentsCommand
{
    public function run(string $projectRoot): int
    {
        Environment::load($projectRoot);
        FileWriteGate::admitProcess();

        if (($_ENV['AMEA_TRASH_BACKUP_CONFIRMED'] ?? getenv('AMEA_TRASH_BACKUP_CONFIRMED')) !== '1') {
            JobLog::write('Trash purge requires a verified backup and AMEA_TRASH_BACKUP_CONFIRMED=1.');
            return 1;
        }

        $retentionDays = max(1, (int)($_ENV['TRASH_RETENTION_DAYS'] ?? getenv('TRASH_RETENTION_DAYS') ?: 30));

        try {
            $pdo = Container::db();
            $repo = new StudentRepository($pdo);
            $lifecycle = Container::fileLifecycle();
        } catch (Throwable $e) {
            JobLog::write('FATAL: cannot initialise database: ' . $e->getMessage());
            return 1;
        }

        $lifecycle->retryDeletions(1000, true);
        $purgeable = $repo->findPurgeable($retentionDays);

        if ($purgeable === []) {
            JobLog::write("Nothing to purge (retention {$retentionDays} days).");
            return $lifecycle->hasPendingDeletions() ? 1 : 0;
        }

        $deleted = 0;
        $failed  = 0;

        foreach ($purgeable as $student) {
            $id = $student->getId();
            try {
                if ($lifecycle->purgeMember($id, $retentionDays)) {
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
        return ($failed > 0 || $lifecycle->hasPendingDeletions()) ? 1 : 0;
    }
}
