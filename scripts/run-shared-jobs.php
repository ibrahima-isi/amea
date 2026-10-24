<?php
/** Single designated split-app job owner. Legacy cron commands remain supported. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$job = $argv[1] ?? '';
$jobs = ['mail' => 'process-mail-queue.php', 'pending' => 'purge-pending-uploads.php', 'trash' => 'purge-trashed-students.php'];
if (!isset($jobs[$job])) {
    fwrite(STDERR, "Usage: php scripts/run-shared-jobs.php mail|pending|trash\n");
    exit(1);
}
// Web images do not contain scripts. Refuse accidental use under a web identity.
$identity = '/opt/amea-apps/identity';
if (is_file($identity) && trim((string)file_get_contents($identity)) !== 'jobs') exit(1);
if ($job === 'trash' && getenv('AMEA_TRASH_BACKUP_CONFIRMED') !== '1') {
    fwrite(STDERR, "Trash purge requires a verified backup and AMEA_TRASH_BACKUP_CONFIRMED=1.\n");
    exit(1);
}
$lock = fopen($root . '/storage/.shared-jobs.lock', 'c');
if ($lock === false) exit(1);
if (!flock($lock, LOCK_EX | LOCK_NB)) exit(0);
$process = proc_open([PHP_BINARY, $root . '/scripts/' . $jobs[$job]], [STDIN, STDOUT, STDERR], $pipes, $root);
$status = is_resource($process) ? proc_close($process) : 1;
flock($lock, LOCK_UN);
fclose($lock);
exit($status);
