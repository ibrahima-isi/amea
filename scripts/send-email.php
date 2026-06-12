<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit(1);
}

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';

if (is_file($projectRoot . '/.env')) {
    foreach (file($projectRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_contains($line, '=') && !str_starts_with(ltrim($line), '#')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

function mailEnv(string $key): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false ? '' : (string)$value;
}

$queueDir = realpath($projectRoot . '/storage/mail-queue');
$queueFile = isset($argv[1]) ? realpath($argv[1]) : false;

if ($queueDir === false || $queueFile === false || !str_starts_with($queueFile, $queueDir . DIRECTORY_SEPARATOR)) {
    exit(1);
}

$payload = json_decode((string)file_get_contents($queueFile), true);
if (!is_array($payload)) {
    exit(1);
}

$service = new \Amea\Service\EmailService(
    mailEnv('MAIL_USER'),
    mailEnv('MAIL_PASS'),
    'noreply@aeesgs.org',
    'AEESGS Platform',
    null,
    $projectRoot
);

$sent = $service->send(
    (string)($payload['to'] ?? ''),
    (string)($payload['subject'] ?? ''),
    (string)($payload['body'] ?? ''),
    isset($payload['replyTo']) ? (string)$payload['replyTo'] : null
);

if ($sent) {
    unlink($queueFile);
    exit(0);
}

exit(1);
