#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

\Amea\Core\Runtime::loadEnv($projectRoot);

$command = new \Amea\Command\CloudflareCacheCommand($projectRoot);
$result = $command->run($argv);

fwrite($result['exit'] === 0 ? STDOUT : STDERR, $result['output']);
exit($result['exit']);
