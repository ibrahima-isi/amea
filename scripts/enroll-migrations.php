<?php

declare(strict_types=1);

use Amea\Migration\BaselineEnrollment;
use Amea\Migration\MigrationRuntime;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$arguments = getopt('', ['database:', 'writers-stopped', 'evidence:'], $firstPositional);
$database = $arguments['database'] ?? null;
$evidence = $arguments['evidence'] ?? null;
if ($argc !== 4 || $firstPositional !== $argc || !is_string($database) || $database === ''
    || !is_string($evidence) || $evidence === '' || ($arguments['writers-stopped'] ?? null) !== false) {
    fwrite(STDERR, "Usage: php scripts/enroll-migrations.php --database=NAME --writers-stopped --evidence=FILE\n");
    exit(2);
}

if ($database !== getenv('DB_NAME')) {
    fwrite(STDERR, "Enrollment failed: --database does not match DB_NAME. Select the intended database before retrying.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

try {
    $runtime = MigrationRuntime::fromEnvironment();
    $enrolled = (new BaselineEnrollment($runtime))->enroll($database, $evidence);
    fwrite(STDOUT, $enrolled ? "Recorded 2 baseline versions without replaying historical migrations.\n" : "Baseline already enrolled; no versions changed.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Enrollment failed: ' . $error->getMessage() . "\n");
    exit(1);
}
