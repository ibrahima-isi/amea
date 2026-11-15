<?php

declare(strict_types=1);

/** Entirely synthetic rehearsal. Retains its private local source and backups. */
require __DIR__ . '/../bootstrap.php';

use Amea\Core\FileWriteGate;
use Amea\Service\FileLifecycleService;
use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\Copier;
use Amea\Storage\Migration\Cutover;
use Amea\Storage\Migration\Inventory;
use Amea\Storage\Migration\Manifest;
use Amea\Storage\Migration\RecoverySnapshot;
use Amea\Storage\Migration\ReferenceAudit;
use Amea\Storage\S3Storage;
use Amea\Storage\Storage;
use Amea\Storage\StorageFactory;
use Aws\S3\S3Client;

function rehearsalCheck(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . "\n";
}

function rehearsalPut(Storage $storage, string $key, string $bytes, int $age = 1600000000): void
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $bytes);
    rewind($stream);
    try {
        $storage->write($key, $stream, 'application/pdf', $age);
    } finally {
        fclose($stream);
    }
}

function rehearsalTargets(FileWriteGate $gate, PDO $db, Storage $backend, string $sessionRoot): array
{
    $reports = [];
    foreach (['public', 'staff', 'jobs'] as $role) {
        $reports[] = ['role' => $role, 'backend' => Manifest::backendIdentity($backend),
            'database' => ReferenceAudit::databaseIdentity($db), 'gate' => $gate->identity(),
            'code' => Cutover::codeIdentity(dirname(__DIR__, 2)), 'serializer' => 'php',
            'observed_at' => microtime(true), 'challenge' => $gate->status()['challenge'],
            'session_store' => $role === 'jobs' ? null : Cutover::sessionStoreIdentity($sessionRoot . '/' . $role)];
    }
    return $reports;
}

function rehearsalIdentity(PDO $db, Storage $source, Storage $destination, string $sessions): array
{
    return ['environment' => 'synthetic-storage05', 'database' => ReferenceAudit::databaseIdentity($db),
        'source' => Manifest::backendIdentity($source), 'destination' => Manifest::backendIdentity($destination),
        'sessions' => $sessions, 'serializer' => 'php'];
}

$endpoint = getenv('STORAGE_TEST_ENDPOINT') ?: '';
if (!preg_match('#^http://(127\.0\.0\.1|localhost|s3|minio|seaweedfs):[0-9]+/?$#D', $endpoint)) {
    throw new RuntimeException('Select the disposable loopback/container STORAGE_TEST_ENDPOINT explicitly.');
}
$root = sys_get_temp_dir() . '/amea-rehearsal05-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
$root = realpath($root);
foreach (['source', 'recovery', 'sessions', 'sessions/public', 'sessions/staff', 'restore', 'restore-sessions',
    'restore-sessions/public', 'restore-sessions/staff', 'restore-storage', 'evidence'] as $directory) {
    mkdir($root . '/' . $directory, 0700);
}
$client = new S3Client(['version' => '2006-03-01', 'region' => 'us-east-1', 'endpoint' => $endpoint,
    'use_path_style_endpoint' => true, 'credentials' => ['key' => 'storage-test', 'secret' => 'disposable-storage-secret'],
    'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
    'http' => ['connect_timeout' => 2, 'timeout' => 10]]);
$bucket = 'amea-rehearsal05-' . bin2hex(random_bytes(8));
$client->createBucket(['Bucket' => $bucket]);
$remote = new S3Storage($client, $bucket);
$source = new LocalStorage($root . '/source');
$localRecovery = new LocalStorage($root . '/recovery');
$databasePath = $root . '/current.sqlite';
if (getenv('MIGRATION_TEST_DB') === '1') {
    rehearsalCheck(getenv('DB_NAME') === 'amea_storage05_test', 'explicit disposable MariaDB source selection');
    $db = \Amea\Migration\MigrationRuntime::fromEnvironment()->connection()->getNativeConnection();
    rehearsalCheck((int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn() === 0, 'source database has no prior member data');
} else {
    makeTestDb()->exec('VACUUM INTO ' . (new PDO('sqlite::memory:'))->quote($databasePath));
    $db = new PDO('sqlite:' . $databasePath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
$gate = new FileWriteGate($root . '/source/storage/write-gate');
$identity = rehearsalIdentity($db, $source, $remote, $root . '/sessions');
$manifestPath = $root . '/evidence/forward.jsonl';
$inventory = new Inventory($source, $identity, $root . '/source', 2);
$pdf = "%PDF-1.4\nsynthetic member before cutover\n%%EOF\n";
$post = "%PDF-1.4\nnew submission after reopening\n%%EOF\n";
try {
    $gate->mutate(function () use ($source, $db, $root, $pdf) {
        rehearsalPut($source, 'uploads/students/member.pdf', $pdf);
        rehearsalPut($source, 'uploads/students/pending/open-before-pause.pdf', $pdf);
        rehearsalPut($source, 'uploads/students/pending/change.pdf', "%PDF-1.4\nold online bytes\n");
        $db->prepare('INSERT INTO students(last_name,first_name,gender,status,identity_document) VALUES(?,?,?,?,?)')
            ->execute(['Synthetic', 'Before', 'Féminin', 'ETUDIANT', 'uploads/students/member.pdf']);
        file_put_contents($root . '/sessions/public/sess_preexisting', 'pending_registration|' . serialize(['db_data' => ['identity_document' => 'uploads/students/pending/open-before-pause.pdf']]));
        file_put_contents($root . '/sessions/staff/sess_staff', 'user_id|i:1;');
    });
    $manifest = $inventory->scan();
    (new ReferenceAudit($db, $source, $identity, $root . '/sessions', 'php'))->audit($manifest);
    $manifest->save($manifestPath);
    $copier = new Copier($source, $remote, $identity, $inventory);
    $started = microtime(true);
    $copier->copy($manifest);
    $onlineSeconds = microtime(true) - $started;
    $changed = "%PDF-1.4\nchanged online bytes\n";
    // A legacy external source writer changes this key during online copying.
    file_put_contents($root . '/source/uploads/students/pending/change.pdf', $changed);
    // Remove its local metadata so the legacy file's actual new bytes are authoritative.
    foreach (glob($root . '/source/storage/object-storage/*.json') ?: [] as $sidecar) {
        if (basename($sidecar) === hash('sha256', 'uploads/students/pending/change.pdf') . '.json') {
            unlink($sidecar);
        }
    }
    rehearsalPut($source, 'uploads/students/cvs/added-online.pdf', "%PDF-1.4\nadded online\n");
    $cutover = new Cutover($gate, $db, $source, $remote, $identity, $manifestPath);
    $pauseStarted = microtime(true);
    $cutover->pause();
    try {
        $copier->copy($manifest);
        throw new RuntimeException('Expected changed destination conflict.');
    } catch (\Amea\Storage\StorageException) {
        rehearsalCheck($gate->status()['paused'], 'changed-source conflict retains source and closes admission');
    }
    $mapping = [['source_key' => 'uploads/students/pending/change.pdf',
        'destination_key' => 'uploads/students/pending/changed-reviewed.pdf', 'expected_sha256' => hash('sha256', $changed)]];
    $copier->copy($manifest, $mapping, hash('sha256', json_encode($mapping, JSON_THROW_ON_ERROR)));
    $owner = hash('sha256', 'local\n' . $root . '/source');
    $db->prepare('INSERT INTO file_operations(operation_id,backend,reason,keys_json) VALUES(?,?,?,?)')
        ->execute(['synthetic-owned-intent', $owner, 'replacement', '["uploads/students/member.pdf"]']);
    try {
        $cutover->finalCheck(rehearsalTargets($gate, $db, $remote, $root . '/sessions'));
        throw new RuntimeException('Pending intent bypassed cutover guard.');
    } catch (\Amea\Storage\StorageException) {
        rehearsalCheck($gate->status()['paused'], 'outstanding intent blocks backend ownership change');
    }
    $gate->exclusive(function () use ($db, $source, $owner) {
        (new FileLifecycleService($db, $source, $owner))->retryDeletions(100);
    });
    rehearsalCheck((int)$db->query('SELECT COUNT(*) FROM file_operations')->fetchColumn() === 0, 'source-owned intent drains without backend reassignment');
    $targets = rehearsalTargets($gate, $db, $remote, $root . '/sessions');
    $cutover->finalCheck($targets);
    $snapshot = new RecoverySnapshot($gate);
    $sourceBackup = $snapshot->capture($root . '/source-backup', $db, $source, $owner,
        ['public' => $root . '/sessions/public', 'staff' => $root . '/sessions/staff'], $root . '/source/storage',
        $manifestPath, ['environment' => 'synthetic-storage05', 'serializer' => 'php']);
    $cutover->finalCheck($targets);
    $cutover->resume($targets);
    $pauseSeconds = microtime(true) - $pauseStarted;
    rehearsalCheck(!$gate->status()['paused'], 'verified local-to-S3 cutover reopens');
    $gate->mutate(function () use ($remote, $db, $root, $post) {
        rehearsalPut($remote, 'uploads/students/new-after-cutover.pdf', $post, 1700000000);
        rehearsalPut($remote, 'uploads/students/cvs/replaced-after-cutover.pdf', $post, 1700000001);
        rehearsalPut($remote, 'uploads/students/pending/new-after-cutover.pdf', $post, 1700000002);
        $db->prepare('INSERT INTO students(last_name,first_name,gender,status,identity_document,cv_path) VALUES(?,?,?,?,?,?)')
            ->execute(['Synthetic', 'After', 'Masculin', 'ETUDIANT', 'uploads/students/new-after-cutover.pdf', 'uploads/students/cvs/replaced-after-cutover.pdf']);
        file_put_contents($root . '/sessions/public/sess_new', 'pending_registration|' . serialize(['db_data' => ['identity_document' => 'uploads/students/pending/new-after-cutover.pdf']]));
    });
    $reverseIdentity = rehearsalIdentity($db, $remote, $localRecovery, $root . '/sessions');
    $reversePath = $root . '/evidence/reverse.jsonl';
    $reverseInventory = new Inventory($remote, $reverseIdentity, null, 2);
    $reverse = $reverseInventory->scan();
    (new ReferenceAudit($db, $remote, $reverseIdentity, $root . '/sessions', 'php'))->audit($reverse);
    $reverse->save($reversePath);
    $reverseCopier = new Copier($remote, $localRecovery, $reverseIdentity, $reverseInventory);
    $rollback = new Cutover($gate, $db, $remote, $localRecovery, $reverseIdentity, $reversePath);
    $rollbackStarted = microtime(true);
    $rollback->pause();
    // Provider read failure after pause, using the actual endpoint identity.
    $outage = new class($client, $bucket) extends S3Storage {
        public function read(string $key): mixed { throw new \Amea\Storage\StorageException('Synthetic provider outage.'); }
    };
    try {
        (new Copier($outage, $localRecovery, $reverseIdentity, new Inventory($outage, $reverseIdentity)))->copy($reverse);
        throw new RuntimeException('Outage unexpectedly succeeded.');
    } catch (\Amea\Storage\StorageException) {
        rehearsalCheck((new FileWriteGate($root . '/source/storage/write-gate'))->status()['paused'], 'rollback outage survives operator restart with durable admission closed');
    }
    $remote->delete('uploads/students/new-after-cutover.pdf');
    try {
        $reverseCopier->copy($reverse);
        $rollback->finalCheck(rehearsalTargets($gate, $db, $localRecovery, $root . '/sessions'));
        throw new RuntimeException('Missing unique submission reopened writes.');
    } catch (\Amea\Storage\StorageException) {
        rehearsalCheck($gate->status()['paused'], 'unrecoverable unique post-cutover bytes keep recovery paused');
    }
    rehearsalPut($remote, 'uploads/students/new-after-cutover.pdf', $post, 1700000000);
    $reverseCopier->copy($reverse);
    $rollback->finalCheck(rehearsalTargets($gate, $db, $localRecovery, $root . '/sessions'));
    $s3Owner = hash('sha256', 's3\n' . $endpoint . '\n' . $bucket);
    $remoteBackup = $snapshot->capture($root . '/post-cutover-backup', $db, $remote, $s3Owner,
        ['public' => $root . '/sessions/public', 'staff' => $root . '/sessions/staff'], $root . '/source/storage',
        $reversePath, ['environment' => 'synthetic-storage05', 'serializer' => 'php']);
    $rollback->finalCheck(rehearsalTargets($gate, $db, $localRecovery, $root . '/sessions'));
    $rollback->resume(rehearsalTargets($gate, $db, $localRecovery, $root . '/sessions'));
    $rollbackSeconds = microtime(true) - $rollbackStarted;
    $stream = $localRecovery->read('uploads/students/new-after-cutover.pdf');
    rehearsalCheck(stream_get_contents($stream) === $post && (int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn() === 2,
        'current-state reverse-copy retains new member and replacement bytes without restoring an old DB');
    fclose($stream);
    rehearsalCheck($localRecovery->stat('uploads/students/pending/new-after-cutover.pdf')['createdAt'] === 1700000002,
        'new pending preview retains its current key and original age');
    if (getenv('MIGRATION_TEST_DB') === '1') {
        rehearsalCheck(getenv('RECOVERY_TEST_DB_NAME') === 'amea_storage05_restore', 'explicit separate MariaDB restore selection');
        $restoreDb = new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('RECOVERY_TEST_DB_NAME') . ';charset=utf8mb4',
            getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } else {
        $restoreDb = new PDO('sqlite:' . $root . '/restore.sqlite', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    $restoreStorage = new LocalStorage($root . '/restore');
    $restoreGate = new FileWriteGate($root . '/restore-storage/write-gate');
    $restoreGate->pause();
    $restoreSnapshot = new RecoverySnapshot($restoreGate);
    $restoreSessions = ['public' => $root . '/restore-sessions/public', 'staff' => $root . '/restore-sessions/staff'];
    $restoreStarted = microtime(true);
    $restoreSnapshot->restore($root . '/post-cutover-backup', $remoteBackup['sha256'], $restoreDb,
        $restoreStorage, hash('sha256', 'local\n' . $root . '/restore'), $restoreSessions,
        $root . '/restore-storage', ReferenceAudit::databaseIdentity($restoreDb));
    $restoreSnapshot->resumeRestored($root . '/post-cutover-backup', $remoteBackup['sha256'], $restoreDb,
        $restoreStorage, $restoreSessions, rehearsalTargets($restoreGate, $restoreDb, $restoreStorage, $root . '/restore-sessions'));
    rehearsalCheck((int)$restoreDb->query('SELECT COUNT(*) FROM students')->fetchColumn() === 2
        && file_get_contents($restoreSessions['public'] . '/sess_preexisting') === file_get_contents($root . '/sessions/public/sess_preexisting')
        && file_get_contents($restoreSessions['public'] . '/sess_new') === file_get_contents($root . '/sessions/public/sess_new'),
        'separate current S3 backup restore retains matching DB, pre-pause and new pending sessions');
    echo json_encode(['root' => $root, 'source_backup' => $sourceBackup, 'post_cutover_backup' => $remoteBackup,
        'online_copy_seconds' => $onlineSeconds, 'pause_seconds' => $pauseSeconds, 'rollback_seconds' => $rollbackSeconds,
        'isolated_restore_seconds' => microtime(true) - $restoreStarted,
        'role_reports' => 'Synthetic API reports. Baked-image reports qualified by tests/apps/release.py'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    foreach ($client->getPaginator('ListObjectsV2', ['Bucket' => $bucket]) as $page) {
        foreach ($page['Contents'] ?? [] as $object) {
            $client->deleteObject(['Bucket' => $bucket, 'Key' => $object['Key']]);
        }
    }
    $client->deleteBucket(['Bucket' => $bucket]);
}
