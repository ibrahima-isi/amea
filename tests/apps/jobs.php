<?php
// Real CLI worker competition, solely inside the disposable jobs image/storage.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') exit(1);
$queue='/opt/amea/storage/mail-queue';
if (glob($queue.'/*.json')) throw new RuntimeException('Expected an empty synthetic queue');
foreach ([1,2,3] as $id) file_put_contents($queue.'/synthetic-'.$id.'.json',json_encode(['to'=>'synthetic'.$id.'@example.invalid','subject'=>'Synthetic','body'=>'Safe substitute']));
$counter=$queue.'/.sent-'.date('Ymd');
$before=is_file($counter)?(int)file_get_contents($counter):0;
putenv('MAIL_SEND_PAUSE_MS=700');
$start=function($name) {
    $out='/tmp/amea-job-'.$name.'.log';
    $process=proc_open([PHP_BINARY,'scripts/run-shared-jobs.php','mail'],[0=>['file','/dev/null','r'],1=>['file',$out,'w'],2=>['file',$out,'a']],$pipes,'/opt/amea');
    return [$process,$out];
};
[$first,$firstLog]=$start('first');
// Wait for first delivery: the owner lock is held across the remaining messages.
for($i=0;$i<100 && !str_contains((string)@file_get_contents($firstLog),'Delivered to');$i++) usleep(20000);
if(!str_contains((string)file_get_contents($firstLog),'Delivered to')) throw new RuntimeException('First worker did not begin');
[$second,$secondLog]=$start('second');
if(proc_close($second)!==0 || proc_close($first)!==0) throw new RuntimeException('Worker failure');
$logs=file_get_contents($firstLog).file_get_contents($secondLog);
if(substr_count($logs,'Delivered to')!==3 || glob($queue.'/*.json') || (int)file_get_contents($counter)!==$before+3 || file_get_contents($secondLog)!=='') throw new RuntimeException('Competing worker duplicated or lost work');
echo "PASS jobs: overlapping owners process three synthetic messages once; safe EmailService substitute, no SMTP\n";

require '/opt/amea/vendor/autoload.php';
$db = \Amea\Core\Container::db();
$storage = \Amea\Core\Container::storage();
$lifecycle = \Amea\Core\Container::fileLifecycle();
$put = function (string $key, int $createdAt) use ($storage): void {
    $stream = fopen('php://temp', 'w+b'); fwrite($stream, "%PDF-1.4\nSynthetic cleanup\n%%EOF"); rewind($stream);
    $storage->write($key, $stream, 'application/pdf', $createdAt); fclose($stream);
};
$old = 'uploads/students/pending/nested/jobs-old.pdf';
$recent = 'uploads/students/pending/nested/jobs-recent.pdf';
$put($old, time() - 25 * 3600); $put($recent, time() - 2 * 3600);
$run = function (string $job, bool $backup = false): int {
    putenv('AMEA_TRASH_BACKUP_CONFIRMED=' . ($backup ? '1' : '0'));
    $process = proc_open([PHP_BINARY, 'scripts/run-shared-jobs.php', $job], [STDIN, STDOUT, STDERR], $pipes, '/opt/amea');
    return proc_close($process);
};
if ($run('pending') !== 0 || $storage->stat($old) !== null || $storage->stat($recent) === null) throw new RuntimeException('Default pending cleanup ignored original staging age');
putenv('PENDING_UPLOAD_TTL_HOURS=1');
if ($run('pending') !== 0 || $storage->stat($recent) !== null) throw new RuntimeException('Configured pending retention was ignored');
putenv('PENDING_UPLOAD_TTL_HOURS');
echo "PASS jobs: nested pending objects use original metadata and configured retention\n";

// Seed the exact durable boundary left by an interrupted permanent purge.
$purgeKey = 'uploads/students/jobs-interrupted-purge.pdf';
$put($purgeKey, time() - 40 * 86400);
$db->prepare('INSERT INTO students (last_name, first_name, gender, status, housing_type, cv_path) VALUES (?, ?, ?, ?, ?, ?)')->execute(['SyntheticCleanup', 'Fixture', 'Masculin', 'ETUDIANT', 'Famille', $purgeKey]);
$id = (int)$db->lastInsertId();
$db->prepare('UPDATE students SET deleted_at = ? WHERE id = ?')->execute(['2020-01-01 00:00:00', $id]);
$db->beginTransaction();
$operations = new \Amea\Repository\FileOperationRepository($db);
$operations->lockMembers();
$student = $operations->lockStudent($id);
$operations->enqueue('purge-' . $id, \Amea\Storage\StorageFactory::identity('/opt/amea'), 'purge-retention-30', [$purgeKey], $student);
$operations->start('purge-' . $id);
$db->commit();
if ($run('pending') !== 0 || $storage->stat($purgeKey) === null || $operations->find('purge-' . $id) === null) throw new RuntimeException('Hourly worker bypassed the purge backup gate');
if ($run('trash') !== 1 || $storage->stat($purgeKey) === null) throw new RuntimeException('Trash worker bypassed backup acknowledgement');
if ($run('trash', true) !== 0 || $storage->stat($purgeKey) !== null || $operations->find('purge-' . $id) !== null) throw new RuntimeException('Acknowledged trash worker did not recover interrupted purge');
echo "PASS jobs: scheduled purge continuation requires backup acknowledgement on every path\n";
