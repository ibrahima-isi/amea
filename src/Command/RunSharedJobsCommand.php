<?php

declare(strict_types=1);

namespace Amea\Command;

/** Coordinates the single designated split-app job owner. */
final class RunSharedJobsCommand
{
    public function run(string $projectRoot, string $job): int
    {
        $scripts = [
            'mail' => 'process-mail-queue.php',
            'pending' => 'purge-pending-uploads.php',
            'trash' => 'purge-trashed-students.php',
        ];
        if (!isset($scripts[$job])) {
            fwrite(STDERR, "Usage: php scripts/run-shared-jobs.php mail|pending|trash\n");
            return 1;
        }

        // Web images do not contain scripts. Refuse accidental use under a web identity.
        $identity = '/opt/amea-apps/identity';
        if (is_file($identity) && trim((string) file_get_contents($identity)) !== 'jobs') {
            return 1;
        }
        if ($job === 'trash' && getenv('AMEA_TRASH_BACKUP_CONFIRMED') !== '1') {
            fwrite(STDERR, "Trash purge requires a verified backup and AMEA_TRASH_BACKUP_CONFIRMED=1.\n");
            return 1;
        }

        $lock = fopen($projectRoot . '/storage/.shared-jobs.lock', 'c');
        if ($lock === false) {
            return 1;
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            return 0;
        }

        $process = proc_open(
            [PHP_BINARY, $projectRoot . '/scripts/' . $scripts[$job]],
            [STDIN, STDOUT, STDERR],
            $pipes,
            $projectRoot
        );
        $status = is_resource($process) ? proc_close($process) : 1;
        flock($lock, LOCK_UN);
        fclose($lock);

        return $status;
    }
}
