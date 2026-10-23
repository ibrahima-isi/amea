<?php
// Test-only entry point: no connection to configured MySQL, no real email.
// Never install this router in an application deployment.
if (PHP_SAPI !== 'cli-server' || !str_starts_with(basename(dirname(__DIR__, 2)), 'amea-http-') || !is_file(__DIR__ . '/../../fixture.sqlite')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../functions/utility-functions.php';
$_ENV['APP_ENV'] = 'test';
$_ENV['MAIL_USER'] = '';
$_ENV['MAIL_PASS'] = '';
$_ENV['APP_URL'] = 'http://' . $_SERVER['HTTP_HOST'];
require __DIR__ . '/SqliteFixture.php';
$db = new SqliteFixture('sqlite:' . __DIR__ . '/../../fixture.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\Amea\Core\Container::setDb($db);
// Loopback HTTP cannot send Secure cookies. Start a test session first;
// production Session still applies expiry/authentication, never changed here.
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();
$router = require __DIR__ . '/../../src/bootstrap.php';
$router->dispatch();
