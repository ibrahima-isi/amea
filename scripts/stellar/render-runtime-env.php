<?php

declare(strict_types=1);

/**
 * Renders a private 0600 runtime.env file for Namecheap Stellar from allowlisted
 * process environment variables. Never echoes secret values.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(output|app-env)=(.+)$/s', $argument, $match) || isset($options[$match[1]])) {
        fwrite(STDERR, "Usage: php scripts/stellar/render-runtime-env.php --output=FILE [--app-env=production|staging|test]\n");
        exit(2);
    }
    $options[$match[1]] = $match[2];
}

if (empty($options['output'])) {
    fwrite(STDERR, "Option --output is required.\n");
    exit(2);
}

$allowedKeys = [
    'APP_ENV',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'PUBLIC_APP_URL',
    'STAFF_APP_URL',
    'MAIL_USER',
    'MAIL_PASS',
    'MAIL_ASYNC',
    'STORAGE_DRIVER',
    'S3_ENDPOINT',
    'S3_REGION',
    'S3_BUCKET',
    'S3_ACCESS_KEY_ID',
    'S3_SECRET_ACCESS_KEY',
    'S3_PATH_STYLE',
    'R2_ACCOUNT_ID',
    'R2_ENDPOINT',
    'R2_BUCKET_NAME',
    'R2_ACCESS_KEY_ID',
    'R2_SECRET_ACCESS_KEY',
    'CLOUDFLARE_ZONE_ID',
    'CLOUDFLARE_API_TOKEN',
    'CLOUDFLARE_CACHE_ENABLED',
    'CLOUDFLARE_CACHE_PURGE_TIMEOUT',
];

$requiredKeys = [
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'PUBLIC_APP_URL',
    'STAFF_APP_URL',
    'MAIL_USER',
    'MAIL_PASS',
];

$readEnv = static function (string $key): string {
    $val = getenv($key);
    if ($val !== false) {
        return (string) $val;
    }
    if (isset($_ENV[$key])) {
        return (string) $_ENV[$key];
    }
    return '';
};

$resolved = [];
foreach ($allowedKeys as $key) {
    $value = $readEnv($key);
    if ($value === '') {
        continue;
    }
    if (preg_match('/[\r\n\0]/', $value) === 1) {
        fwrite(STDERR, "Environment variable {$key} contains forbidden control characters.\n");
        exit(1);
    }
    $resolved[$key] = trim($value);
}

$rawAppEnv = $options['app-env'] ?? ($resolved['APP_ENV'] ?? 'production');
$appEnv = strtolower(trim($rawAppEnv));
if (!in_array($appEnv, ['production', 'staging', 'test'], true)) {
    $source = isset($options['app-env']) ? 'Option --app-env' : 'APP_ENV';
    fwrite(STDERR, "{$source} must be production, staging, or test.\n");
    exit(isset($options['app-env']) ? 2 : 1);
}
$resolved['APP_ENV'] = $appEnv;

foreach ($requiredKeys as $required) {
    if (!isset($resolved[$required]) || $resolved[$required] === '') {
        fwrite(STDERR, "Missing required runtime environment variable: {$required}\n");
        exit(1);
    }
}

foreach (['PUBLIC_APP_URL', 'STAFF_APP_URL'] as $urlKey) {
    if (!str_starts_with($resolved[$urlKey], 'https://')) {
        fwrite(STDERR, "{$urlKey} must use https://.\n");
        exit(1);
    }
}

$driver = strtolower($resolved['STORAGE_DRIVER'] ?? 'local');
$activeBucket = ($resolved['S3_BUCKET'] ?? '') !== ''
    ? $resolved['S3_BUCKET']
    : ($resolved['R2_BUCKET_NAME'] ?? '');

if ($resolved['APP_ENV'] === 'production' && $driver === 's3' && $activeBucket === 'amea-sandbox') {
    fwrite(STDERR, "Refusing to render production runtime config with sandbox bucket amea-sandbox.\n");
    exit(1);
}

$outputPath = $options['output'];
$outputDir = dirname($outputPath);
if (!is_dir($outputDir) && !mkdir($outputDir, 0700, true)) {
    fwrite(STDERR, "Unable to create output directory.\n");
    exit(1);
}

$lines = [];
foreach ($allowedKeys as $key) {
    if (isset($resolved[$key])) {
        $lines[] = $key . '=' . $resolved[$key];
    }
}

$tmpPath = $outputPath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
$prevUmask = umask(0077);
$written = file_put_contents($tmpPath, implode("\n", $lines) . "\n");
umask($prevUmask);
if ($written === false || !chmod($tmpPath, 0600) || !rename($tmpPath, $outputPath)) {
    @unlink($tmpPath);
    fwrite(STDERR, "Unable to write private runtime configuration file.\n");
    exit(1);
}

echo json_encode([
    'output' => realpath($outputPath) ?: $outputPath,
    'app_env' => $resolved['APP_ENV'],
    'key_count' => count($resolved),
    'keys' => array_keys($resolved),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);
