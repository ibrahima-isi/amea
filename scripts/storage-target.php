<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = is_dir('/opt/amea/src') ? '/opt/amea' : dirname(__DIR__);
require $root . '/vendor/autoload.php';

try {
    $options = \Amea\Storage\Migration\TargetAttestation::parseCliOptions($argv);
    $report = \Amea\Storage\Migration\TargetAttestation::report($root, $options);
    echo json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} catch (InvalidArgumentException) {
    fwrite(STDERR, "Usage: php scripts/storage-target.php [--role=public|staff|jobs --config=FILE --state-dir=DIR [--docroot=DIR|--job-wrapper=FILE]]\n");
    exit(2);
} catch (Throwable) {
    fwrite(STDERR, "Unable to attest the selected paused application target.\n");
    exit(1);
}
