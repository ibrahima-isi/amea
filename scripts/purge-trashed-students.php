<?php

declare(strict_types=1);

use Amea\Command\PurgeTrashedStudentsCommand;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

exit((new PurgeTrashedStudentsCommand())->run(dirname(__DIR__)));
