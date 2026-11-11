<?php

declare(strict_types=1);

use Amea\Command\EnrollMigrationsCommand;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';
// Keep argument and database selection diagnostics available before dependencies are installed.
require is_file($autoload) ? $autoload : $projectRoot . '/src/Command/EnrollMigrationsCommand.php';

exit((new EnrollMigrationsCommand())->run($argc ?? 0, $autoload));
