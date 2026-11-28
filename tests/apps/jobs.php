<?php
// Real CLI worker competition, solely inside the disposable jobs image/storage.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal'
    || getenv('DB_HOST') !== 'mysql') exit(1);
require '/opt/amea/vendor/autoload.php';
$database = \Amea\Core\Container::db();
$quota = new \Amea\Repository\MailDailyUsageRepository($database);
// Simultaneous web/CLI reservations import the same legacy floor exactly once.
$workers = [];
for ($i = 0; $i < 12; $i++) {
    $process = proc_open([PHP_BINARY, '-r', <<<'PHP'
        require '/opt/amea/vendor/autoload.php';
        $quota = new Amea\Repository\MailDailyUsageRepository(Amea\Core\Container::db());
        echo (int) $quota->reserveSingleSlot('2000-01-01', 10, '2000-01-01 00:00:00', 7);
        PHP
    ], [1 => ['pipe', 'w']], $pipes);
    $workers[] = [$process, $pipes[1]];
}
$reserved = 0;
foreach ($workers as [$process, $output]) {
    $reserved += (int) stream_get_contents($output);
    fclose($output);
    if (proc_close($process) !== 0) throw new RuntimeException('Concurrent quota reservation failed');
}
if ($reserved !== 3 || $quota->getUsage('2000-01-01') !== 10) {
    throw new RuntimeException('Concurrent reservations miscounted the legacy quota');
}
echo "PASS jobs: concurrent reservations preserve legacy usage and the daily cap\n";
$usageDate = gmdate('Y-m-d');
$before = $quota->getUsage($usageDate);
$campaignRecipients = $database->prepare(
    "SELECT COUNT(*) FROM communication_recipients r JOIN communications c ON c.id = r.communication_id
    WHERE c.subject = 'Synthetic notice' AND r.status = ?"
);
$campaignRecipients->execute(['PENDING']);
$pending = (int) $campaignRecipients->fetchColumn();
if ($pending < 1) throw new RuntimeException('Expected recipients from the synthetic HTTP campaign');
$queue='/opt/amea/storage/mail-queue';
if (glob($queue.'/*.json')) throw new RuntimeException('Expected an empty synthetic queue');
foreach ([1,2,3] as $id) file_put_contents($queue.'/synthetic-'.$id.'.json',json_encode(['to'=>'synthetic'.$id.'@example.invalid','subject'=>'Synthetic','body'=>'Safe substitute']));
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
$logs = file_get_contents($firstLog);
$campaignRecipients->execute(['SENT']);
$expectedUsage = $before + 3 + $pending;
if (substr_count($logs, 'Delivered to') !== 3 || glob($queue . '/*.json')
    || (int) $campaignRecipients->fetchColumn() !== $pending
    || $quota->getUsage($usageDate) !== $expectedUsage
    || file_get_contents($secondLog) !== '') {
    throw new RuntimeException("Competing workers duplicated or lost mail.\n{$logs}" . file_get_contents($secondLog));
}
echo "PASS jobs: overlapping workers drain the file queue and HTTP campaign once against the shared quota\n";

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
