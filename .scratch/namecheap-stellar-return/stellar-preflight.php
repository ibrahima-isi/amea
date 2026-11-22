<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$endpoint = $argv[1] ?? '';
if (!preg_match('~\Ahttps://[a-f0-9]{32}(?:\.[a-z0-9-]+)?\.r2\.cloudflarestorage\.com/?\z~D', $endpoint)) {
    fwrite(STDERR, "Usage: php stellar-preflight.php https://<account-id>.r2.cloudflarestorage.com\n");
    exit(2);
}

$passed = true;
$report = static function (bool $ok, string $label) use (&$passed): void {
    echo ($ok ? 'PASS ' : 'FAIL '), $label, PHP_EOL;
    $passed = $passed && $ok;
};

$report(version_compare(PHP_VERSION, '8.5.0', '>='), 'PHP CLI ' . PHP_VERSION . ' (target 8.5)');
foreach (['curl', 'fileinfo', 'gd', 'iconv', 'mbstring', 'openssl', 'pdo_mysql', 'simplexml'] as $extension) {
    $report(extension_loaded($extension), 'extension ' . $extension);
}
foreach (['flock', 'proc_open'] as $function) {
    $report(function_exists($function), 'function ' . $function);
}

if (extension_loaded('curl')) {
    $request = curl_init($endpoint);
    curl_setopt_array($request, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    $report(curl_exec($request) !== false && curl_getinfo($request, CURLINFO_RESPONSE_CODE) > 0,
        'outbound HTTPS and TLS to R2 (no bucket access)');
}

$smtp = @stream_socket_client('tcp://smtp-relay.brevo.com:587', timeout: 5);
$report(is_resource($smtp), 'outbound TCP to Brevo on port 587 (no mail sent)');
if (is_resource($smtp)) {
    fclose($smtp);
}

exit($passed ? 0 : 1);
