<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../vendor/autoload.php';

try {
    $operation = $argv[1] ?? '';
    if (!in_array($operation, ['capture', 'restore', 'resume-restored'], true)) {
        throw new InvalidArgumentException('Use capture, restore or resume-restored with explicit target selections.');
    }
    $options = [];
    foreach (array_slice($argv, 2) as $argument) {
        if (!preg_match('/^--(snapshot|database|backend|manifest|review-snapshot|isolated-database|targets)=(.+)$/s', $argument, $match)
            || isset($options[$match[1]])) {
            throw new InvalidArgumentException('Use unique documented --name=value selections.');
        }
        $options[$match[1]] = $match[2];
    }
    foreach (['snapshot', 'database', 'backend'] as $required) {
        if (empty($options[$required])) {
            throw new InvalidArgumentException('Select the snapshot, actual database and actual backend identities explicitly.');
        }
    }
    $root = dirname(__DIR__);
    $db = \Amea\Migration\MigrationRuntime::fromEnvironment()->connection()->getNativeConnection();
    $backend = \Amea\Storage\StorageFactory::fromEnvironment($root);
    $databaseIdentity = \Amea\Storage\Migration\ReferenceAudit::databaseIdentity($db);
    $backendIdentity = \Amea\Storage\Migration\Manifest::backendIdentity($backend);
    if ($options['database'] !== $databaseIdentity || $options['backend'] !== $backendIdentity) {
        throw new InvalidArgumentException('Selected database or backend differs from the actual target.');
    }
    $sessions = ['public' => (string)getenv('RECOVERY_PUBLIC_SESSION_PATH'), 'staff' => (string)getenv('RECOVERY_STAFF_SESSION_PATH')];
    $persistent = getenv('RECOVERY_STORAGE_PATH') ?: $root . '/storage';
    $applicationIdentity = \Amea\Storage\StorageFactory::identity($root);
    $snapshot = new \Amea\Storage\Migration\RecoverySnapshot(\Amea\Core\FileWriteGate::application());
    if ($operation === 'capture') {
        if (empty($options['manifest'])) {
            throw new InvalidArgumentException('Capture requires the finalized --manifest selection.');
        }
        $proof = $snapshot->capture($options['snapshot'], $db, $backend, $applicationIdentity, $sessions, $persistent,
            $options['manifest'], ['backend' => $backendIdentity, 'database' => $databaseIdentity,
                'code' => \Amea\Storage\Migration\Cutover::codeIdentity($root),
                'serializer' => ini_get('session.serialize_handler')]);
    } else {
        if (($options['isolated-database'] ?? '') !== $databaseIdentity || empty($options['review-snapshot'])) {
            throw new InvalidArgumentException('Restore requires explicit --isolated-database and --review-snapshot selections.');
        }
        if ($operation === 'restore') {
            $proof = $snapshot->restore($options['snapshot'], $options['review-snapshot'], $db, $backend, $applicationIdentity,
                $sessions, $persistent, $databaseIdentity);
        } else {
            if (empty($options['targets'])) {
                throw new InvalidArgumentException('Reopening requires fresh reports from all three actual targets.');
            }
            \Amea\Storage\Migration\Manifest::assertPrivatePath($options['targets']);
            $targets = json_decode(file_get_contents($options['targets']), true, 64, JSON_THROW_ON_ERROR);
            $snapshot->resumeRestored($options['snapshot'], $options['review-snapshot'], $db, $backend, $sessions, $targets);
            $proof = ['state' => 'reopened-isolated-restore'];
        }
    }
    echo json_encode($proof, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
} catch (Throwable) {
    fwrite(STDERR, "Coordinated recovery failed. Keep writers paused and retain the private snapshot and target marker.\n");
    exit(1);
}
