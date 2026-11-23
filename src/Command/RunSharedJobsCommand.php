<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Config\Environment;
use Amea\Core\Runtime;

/** Coordinates the single designated split-app job owner. */
final class RunSharedJobsCommand
{
    public function run(string $projectRoot, string $job): int
    {
        // Only the backup-acknowledged trash worker retries permanent purges.
        $scripts = [
            'mail' => 'process-mail-queue.php',
            'pending' => 'purge-pending-uploads.php',
            'trash' => 'purge-trashed-students.php',
        ];
        if (!isset($scripts[$job])) {
            fwrite(STDERR, "Usage: php scripts/run-shared-jobs.php mail|pending|trash\n");
            return 1;
        }

        // Web runtimes must never run the designated job owner's scripts.
        $identity = Runtime::appIdentity();
        if ($identity !== null && $identity !== 'jobs') {
            return 1;
        }
        if ($job === 'trash' && getenv('AMEA_TRASH_BACKUP_CONFIRMED') !== '1') {
            fwrite(STDERR, "Trash purge requires a verified backup and AMEA_TRASH_BACKUP_CONFIRMED=1.\n");
            return 1;
        }

        if (Runtime::configFilePath() !== null) {
            Environment::load($projectRoot);
        }

        $lock = fopen(Runtime::jobsLockPath($projectRoot), 'c');
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
