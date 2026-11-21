<?php
// Test-only entry point: no connection to configured MySQL, no real email.
// Never install this router in an application deployment.
if (PHP_SAPI !== 'cli-server' || !str_starts_with(basename(dirname(__DIR__, 2)), 'amea-http-') || !is_file(__DIR__ . '/../../fixture.sqlite')) {
    http_response_code(404);
    exit;
}
// Serve only public static assets from the disposable copy; never private uploads.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$root = realpath(__DIR__ . '/../..');
$file = realpath($root . $path);
if (str_starts_with($path, '/assets/') && $file && str_starts_with($file, $root . '/assets/') && is_file($file)) {
    $types = ['json' => 'application/json', 'css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
        readfile($file);
        exit;
    }
}
$entryPoint = match (getenv('AMEA_HTTP_TEST_APPLICATION')) {
    'public' => '/apps/web/public/index.php',
    'staff' => '/apps/backoffice/public/index.php',
    default => null,
};
if ($entryPoint === null) {
    http_response_code(404);
    exit;
}

require $root . '/vendor/autoload.php';
$_ENV['APP_ENV'] = 'test';
$_ENV['MAIL_USER'] = '';
$_ENV['MAIL_PASS'] = '';
$_ENV['APP_URL'] = 'http://' . $_SERVER['HTTP_HOST'];
require __DIR__ . '/SqliteFixture.php';
$db = new SqliteFixture('sqlite:' . __DIR__ . '/../../fixture.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\Amea\Core\Container::setDb($db);
$storage = new \Amea\Tests\Support\FailingStorage(\Amea\Storage\StorageFactory::fromEnvironment($root));
$faultFile = $root . '/storage-fault.json';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_file($faultFile)) {
    $faults = json_decode(file_get_contents($faultFile), true, flags: JSON_THROW_ON_ERROR);
    unlink($faultFile);
    $storage->copiesUntilFailure = $faults['copiesUntilFailure'] ?? -1;
    $storage->failDelete = $faults['failDelete'] ?? false;
    $storage->failWrite = $faults['failWrite'] ?? false;
    $storage->failRead = $faults['failRead'] ?? false;
}
\Amea\Core\Container::setStorage($storage);

require $root . $entryPoint;
