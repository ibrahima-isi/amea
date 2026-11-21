<?php

declare(strict_types=1);

use Amea\Command\PurgePendingUploadsCommand;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

exit((new PurgePendingUploadsCommand())->run(dirname(__DIR__)));
