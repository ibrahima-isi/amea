<?php

declare(strict_types=1);

use Amea\Command\RunSharedJobsCommand;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

exit((new RunSharedJobsCommand())->run(dirname(__DIR__), $argv[1] ?? ''));
