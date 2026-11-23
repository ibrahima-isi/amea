<?php

declare(strict_types=1);

/**
 * Validates a staged Namecheap Stellar release and private runtime configuration
 * before promoting document roots.
 *
 * Never prints secret values. Fails closed on missing platform capabilities,
 * active cutover write-gate pause, misconfigured runtime paths, sandbox bucket
 * selection in production, docroot leakage, or broken route boundaries.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(release-root|config|state-dir|public-docroot|staff-docroot)=(.+)$/s', $argument, $match) || isset($options[$match[1]])) {
        fwrite(STDERR, "Usage: php scripts/stellar/verify-release.php --release-root=DIR --config=FILE --state-dir=DIR [--public-docroot=DIR] [--staff-docroot=DIR]\n");
        exit(2);
    }
    $options[$match[1]] = $match[2];
}

foreach (['release-root', 'config', 'state-dir'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Missing required option --{$required}.\n");
        exit(2);
    }
}

$releaseRoot = realpath($options['release-root']);
$configFile = realpath($options['config']);
$stateDir = realpath($options['state-dir']);

if ($releaseRoot === false || !is_file($releaseRoot . '/vendor/autoload.php')) {
    fwrite(STDERR, "FAIL release root is missing vendor/autoload.php\n");
    exit(1);
}

require $releaseRoot . '/vendor/autoload.php';

$passed = true;
$check = static function (bool $ok, string $label) use (&$passed): void {
    fwrite(STDOUT, ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL);
    $passed = $passed && $ok;
};

// 1. Platform requirements
$check(version_compare(PHP_VERSION, '8.5.0', '>='), 'PHP version ' . PHP_VERSION . ' (>= 8.5)');
foreach (['curl', 'fileinfo', 'gd', 'iconv', 'mbstring', 'openssl', 'pdo_mysql', 'simplexml'] as $ext) {
    $check(extension_loaded($ext), 'PHP extension ' . $ext);
}
foreach (['flock', 'proc_open'] as $fn) {
    $check(function_exists($fn), 'PHP function ' . $fn);
}

// 2. Private runtime paths outside both document roots
$check($configFile !== false && is_file($configFile) && !is_link($options['config']), 'private runtime config file exists and is not a symlink');
if ($configFile !== false) {
    $check((fileperms($configFile) & 0077) === 0, 'private runtime config file has 0600 permissions');
}
$check($stateDir !== false && is_dir($stateDir) && !is_link($options['state-dir']), 'private state directory exists and is not a symlink');

$docroots = [];
foreach (['public-docroot' => 'public', 'staff-docroot' => 'staff'] as $flag => $role) {
    if (!isset($options[$flag])) {
        continue;
    }
    $resolved = realpath($options[$flag]);
    $check($resolved !== false && is_dir($resolved), "{$role} document root exists");
    if ($resolved !== false) {
        $docroots[$role] = $resolved;
        if ($configFile !== false) {
            $check(!str_starts_with($configFile, $resolved . '/'), "runtime config is outside {$role} document root");
        }
        if ($stateDir !== false) {
            $check(!str_starts_with($stateDir, $resolved . '/'), "state directory is outside {$role} document root");
        }
        $check(!str_starts_with($releaseRoot, $resolved . '/'), "release root is outside {$role} document root");

        foreach (['.env', 'runtime.env', 'src', 'vendor', 'config', 'scripts', 'storage', 'uploads', 'logs', 'migrations', 'composer.json', 'composer.lock'] as $forbidden) {
            $check(!file_exists($resolved . '/' . $forbidden) && !is_link($resolved . '/' . $forbidden), "{$role} document root excludes {$forbidden}");
        }
    }
}
if (isset($docroots['public'], $docroots['staff'])) {
    $check($docroots['public'] !== $docroots['staff'], 'public and staff document roots are distinct');
}

if (!$passed || $configFile === false || $stateDir === false) {
    exit(1);
}

// 3. Load config in clean isolated process probes for public, staff, and jobs
$probeCode = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
$role = $argv[2];
$release = $argv[3];
$config = $argv[4];
$state = $argv[5];
define('AMEA_APP', $role);
define('AMEA_CONFIG_FILE', $config);
define('AMEA_STATE_DIR', $state);
$loaded = \Amea\Core\Runtime::loadEnv($release, $config);
$portRaw = \Amea\Core\Runtime::env('DB_PORT');
\Amea\Config\Database::parsePort($portRaw);
$storageRoot = \Amea\Core\Runtime::storageProjectRoot($release);
\Amea\Storage\StorageFactory::fromEnvironment($storageRoot);
$driver = \Amea\Core\Runtime::env('STORAGE_DRIVER', 'local');
$envName = strtolower(trim(\Amea\Core\Runtime::env('APP_ENV', 'production')));
$r2Bucket = trim(\Amea\Core\Runtime::env('R2_BUCKET_NAME'));
$s3Bucket = trim(\Amea\Core\Runtime::env('S3_BUCKET'));
$activeBucket = $s3Bucket !== '' ? $s3Bucket : $r2Bucket;
echo json_encode([
    'loaded' => $loaded,
    'role' => $role,
    'app_env' => $envName,
    'has_db_host' => trim(\Amea\Core\Runtime::env('DB_HOST')) !== '',
    'has_db_name' => trim(\Amea\Core\Runtime::env('DB_NAME')) !== '',
    'has_db_user' => trim(\Amea\Core\Runtime::env('DB_USER')) !== '',
    'has_db_pass' => \Amea\Core\Runtime::env('DB_PASS') !== '',
    'has_mail_user' => trim(\Amea\Core\Runtime::env('MAIL_USER')) !== '',
    'has_mail_pass' => trim(\Amea\Core\Runtime::env('MAIL_PASS')) !== '',
    'app_url' => \Amea\Core\Runtime::env('APP_URL'),
    'public_origin' => \Amea\Core\StaffNavigation::publicOrigin(),
    'staff_app_url' => \Amea\Core\Runtime::env('STAFF_APP_URL'),
    'session_path' => \Amea\Core\Runtime::sessionSavePath($role),
    'twig_cache' => \Amea\Core\Runtime::twigCacheDir($release, $role),
    'write_gate' => \Amea\Core\Runtime::writeGateDir($release),
    'mail_queue' => \Amea\Core\Runtime::mailQueueDir($release),
    'jobs_lock' => \Amea\Core\Runtime::jobsLockPath($release),
    'storage_driver' => $driver,
    'sandbox_bucket_in_prod' => in_array($envName, ['prod', 'production'], true) && $driver === 's3' && $activeBucket === 'amea-sandbox',
    'gate_paused' => \Amea\Core\FileWriteGate::application()->status()['paused'],
], JSON_THROW_ON_ERROR);
PHP;

$cleanEnv = array_intersect_key($_SERVER + getenv(), array_flip(['PATH', 'HOME', 'TMPDIR', 'TMP', 'TEMP', 'PHP_INI_SCAN_DIR', 'LANG', 'LC_ALL']));
$reports = [];

foreach (['public', 'staff', 'jobs'] as $role) {
    $process = proc_open(
        [PHP_BINARY, '-r', $probeCode, $releaseRoot, $role, $releaseRoot, $configFile, $stateDir],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $releaseRoot,
        $cleanEnv
    );
    $stdout = is_resource($process) ? (string) stream_get_contents($pipes[1]) : '';
    $stderr = is_resource($process) ? (string) stream_get_contents($pipes[2]) : '';
    if (is_resource($process)) {
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    } else {
        $exitCode = 1;
    }

    $decoded = $exitCode === 0 ? json_decode($stdout, true) : null;
    $check($exitCode === 0 && is_array($decoded), "{$role} runtime loads private configuration cleanly");
    if (!is_array($decoded)) {
        if ($stderr !== '') {
            fwrite(STDERR, "  ({$role} error: " . trim($stderr) . ")\n");
        }
        continue;
    }
    $reports[$role] = $decoded;
    $check($decoded['loaded'] === $configFile, "{$role} loaded the exact private config file");
    $check($decoded['has_db_host'] && $decoded['has_db_name'] && $decoded['has_db_user'] && $decoded['has_db_pass'], "{$role} resolved DB_HOST/DB_NAME/DB_USER/DB_PASS");
    $check($decoded['has_mail_user'] && $decoded['has_mail_pass'], "{$role} resolved MAIL_USER/MAIL_PASS");
    $check(!$decoded['sandbox_bucket_in_prod'], "{$role} does not select amea-sandbox in production");
    $check(!$decoded['gate_paused'], "{$role} write gate is not paused for a cutover");
}

if (isset($reports['public'], $reports['staff'], $reports['jobs'])) {
    $check(
        $reports['public']['app_url'] !== '' && $reports['public']['public_origin'] !== null,
        'public entry resolved valid HTTPS PUBLIC_APP_URL and APP_URL'
    );
    $check(
        $reports['staff']['app_url'] !== '' && str_starts_with($reports['staff']['staff_app_url'], 'https://'),
        'staff entry resolved valid HTTPS STAFF_APP_URL and APP_URL'
    );
    $check(
        $reports['public']['session_path'] !== null
        && $reports['staff']['session_path'] !== null
        && $reports['public']['session_path'] !== $reports['staff']['session_path'],
        'public and staff use distinct private session directories'
    );
    $check(
        $reports['public']['twig_cache'] !== $reports['staff']['twig_cache'],
        'public and staff use separate Twig cache directories'
    );
    $check(
        $reports['public']['write_gate'] === $reports['staff']['write_gate']
        && $reports['staff']['write_gate'] === $reports['jobs']['write_gate'],
        'public, staff, and jobs share one durable write gate'
    );
    $check(
        $reports['public']['mail_queue'] === $reports['staff']['mail_queue']
        && $reports['staff']['mail_queue'] === $reports['jobs']['mail_queue'],
        'public, staff, and jobs share one durable mail queue'
    );
    $check(
        $reports['public']['jobs_lock'] === $reports['jobs']['jobs_lock'],
        'shared jobs lock lives in persistent private state'
    );
}

// 4. Route allowlist boundary verification
$check(
    !\Amea\Core\Router::supports('public', '/login.php', 'GET')
    && !\Amea\Core\Router::supports('public', '/dashboard.php', 'GET')
    && !\Amea\Core\Router::supports('public', '/download.php', 'GET'),
    'public router denies staff routes'
);
$check(
    !\Amea\Core\Router::supports('staff', '/register.php', 'GET')
    && !\Amea\Core\Router::supports('staff', '/journal.php', 'GET'),
    'staff router denies public-only routes'
);

exit($passed ? 0 : 1);
