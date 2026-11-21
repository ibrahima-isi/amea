<?php

/** Piped only into owned disposable jobs containers. No deployment entry point. */
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') {
    exit(1);
}
require '/opt/amea/vendor/autoload.php';

use Amea\Core\FileWriteGate;
use Amea\Storage\Migration\Inventory;
use Amea\Storage\Migration\Manifest;
use Amea\Storage\Migration\ReferenceAudit;
use Amea\Storage\StorageFactory;

$mode = $argv[1] ?? '';
$root = '/opt/amea';
$db = \Amea\Migration\MigrationRuntime::fromEnvironment()->connection()->getNativeConnection();
$storage = StorageFactory::fromEnvironment($root);
$gate = FileWriteGate::application();
$sessions = ['public' => '/var/lib/amea/recovery-sessions/public', 'staff' => '/var/lib/amea/recovery-sessions/staff'];
$directory = '/var/lib/amea/recovery';
function recoveryCommand(string $operation, array $options): void
{
    putenv('RECOVERY_PUBLIC_SESSION_PATH=/var/lib/amea/recovery-sessions/public');
    putenv('RECOVERY_STAFF_SESSION_PATH=/var/lib/amea/recovery-sessions/staff');
    $arguments = array_map(static fn ($name, $value) => '--' . $name . '=' . $value, array_keys($options), array_values($options));
    $process = proc_open([PHP_BINARY, '/opt/amea/scripts/storage-backup.php', $operation, ...$arguments], [STDIN, STDOUT, STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Actual coordinated backup CLI failed in the owned fixture.');
    }
}
if (in_array($mode, ['create-restore-bucket', 'cleanup-restore-bucket'], true)) {
    $bucket = $argv[2] ?? '';
    if (!preg_match('/^amea-ui-restore-[a-f0-9]{32}$/D', $bucket)) {
        throw new RuntimeException('Only this harness dedicated restore bucket is accepted.');
    }
    $client = new \Aws\S3\S3Client(['version' => '2006-03-01', 'region' => getenv('S3_REGION'),
        'endpoint' => getenv('S3_ENDPOINT'), 'use_path_style_endpoint' => getenv('S3_PATH_STYLE') === 'true',
        'credentials' => ['key' => getenv('S3_ACCESS_KEY_ID'), 'secret' => getenv('S3_SECRET_ACCESS_KEY')],
        'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required']);
    if ($mode === 'create-restore-bucket') {
        $client->createBucket(['Bucket' => $bucket]);
    } else {
        foreach ($client->getPaginator('ListObjectsV2', ['Bucket' => $bucket]) as $page) {
            foreach ($page['Contents'] ?? [] as $object) {
                $client->deleteObject(['Bucket' => $bucket, 'Key' => $object['Key']]);
            }
        }
        $client->deleteBucket(['Bucket' => $bucket]);
    }
    exit;
}
if ($mode === 'seed') {
    $gate->mutate(function () use ($storage, $sessions) {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "%PDF-1.4\nSynthetic registration opened before recovery pause\n%%EOF\n");
        rewind($stream);
        $storage->write('uploads/students/pending/recovery-open.pdf', $stream, 'application/pdf', time());
        fclose($stream);
        session_save_path($sessions['public']);
        session_name('AMEA_PUBLIC');
        session_id('recoverypending');
        session_start();
        $_SESSION = ['last_activity' => time(), 'csrf_token' => bin2hex(random_bytes(32)), 'pending_registration' => [
            'input' => ['last_name' => 'RecoveryPending', 'first_name' => 'Synthetic'],
            'db_data' => ['last_name' => 'RecoveryPending', 'first_name' => 'Synthetic', 'gender' => 'Féminin',
                'status' => 'ETUDIANT', 'phone' => '779999998', 'email' => 'recovery-pending@example.invalid',
                'birth_date' => null, 'residence' => null, 'institution' => null, 'study_field' => null,
                'study_level' => null, 'arrival_year' => null, 'housing_type' => null,
                'housing_details' => null, 'post_training_project' => null,
                'identity_document' => 'uploads/students/pending/recovery-open.pdf', 'cv_path' => null,
                'consent_privacy' => 1, 'nationalities' => '["Guinée"]'], 'valid_pays_ids' => []]];
        session_write_close();
    });
    exit;
}
if ($mode === 'capture') {
    $gate->pause(['purpose' => 'synthetic-coordinated-release-recovery']);
    mkdir($directory . '/evidence', 0700);
    $identity = ['environment' => 'isolated-release', 'database' => ReferenceAudit::databaseIdentity($db),
        'source' => Manifest::backendIdentity($storage), 'destination' => hash('sha256', 'separate snapshot namespace'),
        'sessions' => '/var/lib/amea/recovery-sessions', 'serializer' => 'php'];
    $manifest = (new Inventory($storage, $identity, $storage instanceof \Amea\Storage\LocalStorage ? $root : null))->scan();
    $manifest->save($directory . '/evidence/recovery.jsonl');
    $gate->exclusive(function () use ($manifest, $db, $storage, $identity) {
        $manifest->locked(function ($manifest) use ($db, $storage, $identity) {
            if ((new ReferenceAudit($db, $storage, $identity, $identity['sessions'], 'php'))->audit($manifest) !== []) {
                throw new RuntimeException('Required synthetic references remain unresolved.');
            }
            $manifest->checkpoint();
        });
    });
    recoveryCommand('capture', ['database' => ReferenceAudit::databaseIdentity($db), 'backend' => Manifest::backendIdentity($storage),
        'snapshot' => $directory . '/snapshot', 'manifest' => $directory . '/evidence/recovery.jsonl']);
    exit;
}
$digest = hash_file('sha256', $directory . '/snapshot/snapshot.json');
if ($mode === 'restore') {
    $gate->pause();
    recoveryCommand('restore', ['database' => ReferenceAudit::databaseIdentity($db), 'isolated-database' => ReferenceAudit::databaseIdentity($db),
        'backend' => Manifest::backendIdentity($storage), 'snapshot' => $directory . '/snapshot', 'review-snapshot' => $digest]);
    exit;
}
if ($mode === 'resume') {
    $targets = json_decode(base64_decode($argv[2], true), true, 64, JSON_THROW_ON_ERROR);
    $path = $directory . '/evidence/actual-targets.json';
    file_put_contents($path, json_encode($targets, JSON_THROW_ON_ERROR));
    chmod($path, 0600);
    recoveryCommand('resume-restored', ['database' => ReferenceAudit::databaseIdentity($db), 'isolated-database' => ReferenceAudit::databaseIdentity($db),
        'backend' => Manifest::backendIdentity($storage), 'snapshot' => $directory . '/snapshot', 'review-snapshot' => $digest, 'targets' => $path]);
    exit;
}
throw new RuntimeException('Unknown synthetic recovery phase.');
