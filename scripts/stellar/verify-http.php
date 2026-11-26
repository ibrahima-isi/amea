<?php

declare(strict_types=1);

/**
 * Verifies live split public/staff HTTP route boundaries after a Stellar deploy
 * or rollback. Supports optional Basic Auth verification for private staging
 * without printing credentials.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(public-url|staff-url|basic-auth-env|timeout)=(.+)$/s', $argument, $match) || isset($options[$match[1]])) {
        fwrite(STDERR, "Usage: php scripts/stellar/verify-http.php --public-url=URL --staff-url=URL [--basic-auth-env=VAR] [--timeout=SECONDS]\n");
        exit(2);
    }
    $options[$match[1]] = $match[2];
}

foreach (['public-url', 'staff-url'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Missing required option --{$required}.\n");
        exit(2);
    }
}

$publicUrl = rtrim($options['public-url'], '/');
$staffUrl = rtrim($options['staff-url'], '/');

foreach (['--public-url' => $publicUrl, '--staff-url' => $staffUrl] as $flag => $url) {
    $scheme = parse_url($url, PHP_URL_SCHEME);
    $host = parse_url($url, PHP_URL_HOST);
    if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '') {
        fwrite(STDERR, "Option {$flag} must be a valid http:// or https:// URL.\n");
        exit(2);
    }
}

$timeout = isset($options['timeout']) ? (int) $options['timeout'] : 20;
if ($timeout < 1 || $timeout > 120) {
    fwrite(STDERR, "Option --timeout must be between 1 and 120 seconds.\n");
    exit(2);
}

$basicAuth = null;
if (isset($options['basic-auth-env'])) {
    $envName = $options['basic-auth-env'];
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $envName)) {
        fwrite(STDERR, "Invalid --basic-auth-env variable name.\n");
        exit(2);
    }
    $rawAuth = getenv($envName);
    if ($rawAuth === false || trim($rawAuth) === '') {
        fwrite(STDERR, "Environment variable {$envName} is required when --basic-auth-env is specified.\n");
        exit(2);
    }
    $candidate = trim($rawAuth);
    if (!str_contains($candidate, ':') || preg_match('/[\r\n\0]/', $candidate) === 1) {
        fwrite(STDERR, "Environment variable {$envName} must be in user:password format without control characters.\n");
        exit(2);
    }
    $basicAuth = $candidate;
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP curl extension is required for HTTP verification.\n");
    exit(1);
}

/**
 * @return array{status: int, headers: array<string, list<string>>, error: string}
 */
$probe = static function (string $url, string $method, ?string $auth) use ($timeout): array {
    $headers = [];
    $ch = curl_init($url);
    if ($ch === false) {
        return ['status' => 0, 'headers' => [], 'error' => 'curl_init failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERAGENT => 'AMEA-Deploy-Verifier/1.0',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            $len = strlen($line);
            $colon = strpos($line, ':');
            if ($colon !== false) {
                $name = strtolower(trim(substr($line, 0, $colon)));
                $value = trim(substr($line, $colon + 1));
                $headers[$name][] = $value;
            }
            return $len;
        },
    ]);
    if ($auth !== null) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $auth);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    }
    $ok = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = $ok === false ? curl_error($ch) : '';

    return ['status' => $status, 'headers' => $headers, 'error' => $error];
};

$passed = true;
$check = static function (bool $ok, string $label, string $error = '') use (&$passed): void {
    $suffix = (!$ok && $error !== '') ? " [curl: {$error}]" : '';
    fwrite(STDOUT, ($ok ? 'PASS ' : 'FAIL ') . $label . $suffix . PHP_EOL);
    $passed = $passed && $ok;
};

if ($basicAuth !== null) {
    $unauthPub = $probe($publicUrl . '/legal-notice.php', 'GET', null);
    $check($unauthPub['status'] === 401, "unauthenticated public GET /legal-notice.php returns 401 (got {$unauthPub['status']})", $unauthPub['error']);

    $unauthStaff = $probe($staffUrl . '/login.php', 'GET', null);
    $check($unauthStaff['status'] === 401, "unauthenticated staff GET /login.php returns 401 (got {$unauthStaff['status']})", $unauthStaff['error']);
}

$pubLegal = $probe($publicUrl . '/legal-notice.php', 'GET', $basicAuth);
$check($pubLegal['status'] === 200, "public GET /legal-notice.php returns 200 (got {$pubLegal['status']})", $pubLegal['error']);

$pubAsset = $probe($publicUrl . '/assets/js/theme.js', 'GET', $basicAuth);
$check($pubAsset['status'] === 200, "public GET /assets/js/theme.js returns 200 (got {$pubAsset['status']})", $pubAsset['error']);

foreach ([['GET', '/login.php'], ['POST', '/login.php'], ['GET', '/download.php']] as [$method, $path]) {
    $res = $probe($publicUrl . $path, $method, $basicAuth);
    $hasLocation = !empty($res['headers']['location']);
    $check(
        $res['status'] === 404 && !$hasLocation,
        "public {$method} {$path} returns local 404 without Location header (got {$res['status']}, location=" . ($hasLocation ? 'yes' : 'no') . ')',
        $res['error']
    );
}

$staffLogin = $probe($staffUrl . '/login.php', 'GET', $basicAuth);
$check($staffLogin['status'] === 200, "staff GET /login.php returns 200 (got {$staffLogin['status']})", $staffLogin['error']);

$staffPublic = $probe($staffUrl . '/register.php', 'GET', $basicAuth);
$check($staffPublic['status'] === 404, "staff GET /register.php returns 404 (got {$staffPublic['status']})", $staffPublic['error']);

exit($passed ? 0 : 1);
