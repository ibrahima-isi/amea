<?php
// Real CLI worker competition, solely inside the disposable jobs image/storage.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal'
    || getenv('DB_HOST') !== 'mysql') exit(1);
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
$database = \Amea\Core\Container::db();
$uploadDir = 'uploads/students/job-trash-fixture';
mkdir('/opt/amea/' . $uploadDir, 0700, true);
$insert = $database->prepare(<<<'SQL'
    INSERT INTO students (last_name, first_name, gender, status, email, deleted_at, identity_document, cv_path)
    VALUES ('Synthetic', 'Job fixture', 'Masculin', 'ETUDIANT', ?, ?, ?, ?)
    SQL);
$members = [];
foreach (['expired' => '2001-01-01 00:00:00', 'recent' => date('Y-m-d H:i:s'), 'active' => null] as $kind => $deletedAt) {
    $document = "{$uploadDir}/{$kind}-identity.pdf";
    $cv = "{$uploadDir}/{$kind}-cv.pdf";
    file_put_contents('/opt/amea/' . $document, 'Synthetic document');
    file_put_contents('/opt/amea/' . $cv, 'Synthetic CV');
    $insert->execute(["job-trash-{$kind}@example.invalid", $deletedAt, $document, $cv]);
    $members[$kind] = [(int) $database->lastInsertId(), $document, $cv];
}

$runTrash = static function (string $backupConfirmed): array {
    $process = proc_open(
        [PHP_BINARY, 'scripts/run-shared-jobs.php', 'trash'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        '/opt/amea',
        array_replace(getenv(), ['AMEA_TRASH_BACKUP_CONFIRMED' => $backupConfirmed, 'TRASH_RETENTION_DAYS' => '30'])
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start trash job');
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($process), $output];
};

$checkMember = $database->prepare('SELECT 1 FROM students WHERE id = ?');
$assertPresence = static function (array $member, bool $expected) use ($checkMember): void {
    [$id, $document, $cv] = $member;
    $checkMember->execute([$id]);
    if (($checkMember->fetchColumn() !== false) !== $expected
        || is_file('/opt/amea/' . $document) !== $expected
        || is_file('/opt/amea/' . $cv) !== $expected) {
        throw new RuntimeException("Unexpected row or document state for synthetic member #{$id}");
    }
};

[$status, $output] = $runTrash('0');
if ($status !== 1 || !str_contains($output, 'Trash purge requires a verified backup')) {
    throw new RuntimeException("Trash job must refuse to run without backup confirmation.\n{$output}");
}
$assertPresence($members['expired'], true);

[$status, $output] = $runTrash('1');
if ($status !== 0 || !str_contains($output, 'Purged student #' . $members['expired'][0] . ' ')) {
    throw new RuntimeException("Trash job did not purge the expired synthetic member.\n{$output}");
}
foreach ($members as $kind => $member) {
    $assertPresence($member, $kind !== 'expired');
}
echo "PASS jobs: backup confirmation gates trash purge, which deletes only expired rows and their documents\n";
