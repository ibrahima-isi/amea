<?php

declare(strict_types=1);

use Amea\Command\MigrateDatabaseCommand;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

exit((new MigrateDatabaseCommand())->run($argv ?? []));
