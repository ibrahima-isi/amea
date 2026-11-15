<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = is_dir('/opt/amea/src') ? '/opt/amea' : dirname(__DIR__);
require $root . '/vendor/autoload.php';

try {
    $role = trim((string)@file_get_contents('/opt/amea-apps/identity'));
    if (!in_array($role, ['public', 'staff', 'jobs'], true)) {
        throw new RuntimeException('Run this report inside the selected public, staff or jobs image.');
    }
    $gate = \Amea\Core\FileWriteGate::application();
    $state = $gate->status();
    if (empty($state['paused']) || empty($state['drained'])) {
        throw new RuntimeException('Target reports require the persistent completed pause.');
    }
    $db = \Amea\Core\Container::db();
    $backend = \Amea\Storage\StorageFactory::fromEnvironment($root);
    echo json_encode(['role' => $role, 'backend' => \Amea\Storage\Migration\Manifest::backendIdentity($backend),
        'application_backend' => \Amea\Storage\StorageFactory::identity($root),
        'database' => \Amea\Storage\Migration\ReferenceAudit::databaseIdentity($db),
        'gate' => $gate->identity(), 'code' => \Amea\Storage\Migration\Cutover::codeIdentity($root),
        'serializer' => ini_get('session.serialize_handler'), 'session_path' => '/var/lib/amea/sessions',
        'session_store' => $role === 'jobs' ? null : \Amea\Storage\Migration\Cutover::sessionStoreIdentity('/var/lib/amea/sessions'),
        'challenge' => $state['challenge'], 'observed_at' => microtime(true)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Unable to attest the selected paused application target.\n");
    exit(1);
}
