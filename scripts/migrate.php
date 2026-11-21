<?php

declare(strict_types=1);

use Amea\Command\MigrateDatabaseCommand;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';
// Keep argument diagnostics available before dependencies are installed.
require is_file($autoload) ? $autoload : $projectRoot . '/src/Command/MigrateDatabaseCommand.php';

exit((new MigrateDatabaseCommand())->run($argv ?? [], $autoload));
