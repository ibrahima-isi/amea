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
$publicAsset = $file && (
    (str_starts_with($path, '/assets/') && str_starts_with($file, $root . '/assets/')) ||
    (str_starts_with($path, '/uploads/slider/') && str_starts_with($file, $root . '/uploads/slider/'))
);
if ($publicAsset && is_file($file)) {
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
require $root . $entryPoint;
