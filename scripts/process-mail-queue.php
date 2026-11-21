<?php

declare(strict_types=1);

use Amea\Command\ProcessMailQueueCommand;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

exit((new ProcessMailQueueCommand())->run(dirname(__DIR__)));
