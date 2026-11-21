<?php
/** Real MariaDB row contention at the public member lifecycle boundary. */
declare(strict_types=1);

use Amea\Repository\FileOperationRepository;
use Amea\Repository\StudentRepository;
use Amea\Service\FileLifecycleService;
use Amea\Storage\StorageFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
if (getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_storage02') { exit(2); }
function connect(): PDO
{
    return new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4', getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
function check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: {$message}\n";
}
$root = getenv('MEMBER_TEST_ROOT');
if (!$root || !str_starts_with(basename($root), 'amea-storage02-')) { exit(2); }
$db = connect();
$storage = StorageFactory::fromEnvironment($root);
$service = new FileLifecycleService($db, $storage, StorageFactory::identity($root));
if (($argv[1] ?? '') === 'worker') {
    $request = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
    $result = match ($request['action']) {
        'save' => $service->saveMember($request['data'], [], 9001),
        'restore' => $service->restoreMember(9001),
        'purge' => $service->purgeMember(9001),
    };
    echo json_encode($result);
    exit;
}
function start(array $request): array
{
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', json_encode($request, JSON_THROW_ON_ERROR)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    return [$process, $pipes];
}
function finish(array $run): string
{
    [$process, $pipes] = $run;
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException($error); }
    return $output;
}
function waitForBlocked(PDO $observer, int $count): void
{
    $deadline = microtime(true) + 10;
    do {
        $blocked = (int)$observer->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND INFO LIKE 'SELECT id FROM member_file_lock WHERE id = 1%' AND ID <> CONNECTION_ID()")->fetchColumn();
        if ($blocked >= $count) { return; }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Workers did not reach the shared member row lock.');
}
function put(object $storage, string $key): void
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, "%PDF-1.4\n" . $key . "\n%%EOF"); rewind($stream);
    $storage->write($key, $stream, 'application/pdf', 1234567890); fclose($stream);
}
$observer = connect();
$repo = new StudentRepository($db);
$db->exec("INSERT INTO students (id,last_name,first_name,gender,status,identity_document,cv_path) VALUES (9001,'Concurrent','Synthetic','Masculin','ETUDIANT','uploads/students/original.pdf','uploads/students/cvs/original.pdf')");
foreach (['uploads/students/original.pdf', 'uploads/students/cvs/original.pdf', 'uploads/students/pending/identity-new.pdf', 'uploads/students/pending/cvs/cv-new.pdf', 'uploads/students/pending/cvs/first.pdf', 'uploads/students/pending/cvs/second.pdf'] as $key) { put($storage, $key); }
$operations = new FileOperationRepository($db);
$db->beginTransaction(); $operations->lockMembers(); $operations->lockStudent(9001);
$identity = start(['action' => 'save', 'data' => ['identity_document' => 'uploads/students/pending/identity-new.pdf', 'cv_path' => null]]);
$cv = start(['action' => 'save', 'data' => ['identity_document' => null, 'cv_path' => 'uploads/students/pending/cvs/cv-new.pdf']]);
waitForBlocked($observer, 2); $db->commit(); finish($identity); finish($cv);
$member = $repo->findById(9001);
check($member->getIdentityDocument() === 'uploads/students/identity-new.pdf' && $member->getCvPath() === 'uploads/students/cvs/cv-new.pdf', 'Concurrent different-document edits preserve both current replacements');
$service->retryDeletions(100);
check($storage->stat($member->getIdentityDocument()) !== null && $storage->stat($member->getCvPath()) !== null, 'Cleanup retains both concurrently published objects');
$db->beginTransaction(); $operations->lockMembers(); $operations->lockStudent(9001);
$first = start(['action' => 'save', 'data' => ['cv_path' => 'uploads/students/pending/cvs/first.pdf']]);
$second = start(['action' => 'save', 'data' => ['cv_path' => 'uploads/students/pending/cvs/second.pdf']]);
waitForBlocked($observer, 2); $db->commit(); finish($first); finish($second);
$service->retryDeletions(100);
$member = $repo->findById(9001);
check(in_array($member->getCvPath(), ['uploads/students/cvs/first.pdf','uploads/students/cvs/second.pdf'], true) && $storage->stat($member->getCvPath()) !== null, 'Two concurrent replacements leave the final locked-row reference readable');
$repo->softDelete(9001);
$db->beginTransaction(); $operations->lockMembers(); $operations->lockStudent(9001);
$restore = start(['action' => 'restore']); waitForBlocked($observer, 1);
$purge = start(['action' => 'purge']); waitForBlocked($observer, 2); $db->commit();
check(finish($restore) === 'true' && finish($purge) === 'false', 'Purge rechecks the restored row after lock contention');
check($repo->findById(9001) !== null && $storage->stat($repo->findById(9001)->getCvPath()) !== null, 'Restore wins without partial irreversible deletion');
$repo->softDelete(9001); check($service->purgeMember(9001), 'Disposable concurrent member is cleaned through its lifecycle');
