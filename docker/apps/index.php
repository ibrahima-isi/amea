<?php
/** Only web entry point in both images. App identity is baked into the target. */
$appIdentity = trim(file_get_contents('/opt/amea-apps/identity'));
if (!in_array($appIdentity, ['public', 'staff'], true)) {
    http_response_code(503);
    exit('Application indisponible.');
}
define('AMEA_APP', $appIdentity);
chdir('/opt/amea');
require_once '/opt/amea/vendor/autoload.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$routes = require '/opt/amea-apps/routes.php';
$path = $path === '/index.php' ? '/' : $path;
define('AMEA_STAFF_PATHS', $routes['staff']);
if (!in_array($path, $routes[$appIdentity], true)) {
    http_response_code(404);
    exit('Page introuvable.');
}
$allowedMethods = \Amea\Core\StaffNavigation::methods($path);
if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods, true)) {
    http_response_code(405);
    header('Allow: ' . implode(', ', $allowedMethods));
    exit('Méthode non autorisée.');
}
if ($appIdentity === 'staff' && $path === '/') {
    header('Location: /login.php');
    exit;
}
$router = require '/opt/amea/src/bootstrap.php';
require '/opt/amea/config/session.php';
$router->dispatch();
