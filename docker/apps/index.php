<?php
/** Only web entry point in both images. App identity is baked into the target. */
$appIdentity = trim(file_get_contents('/opt/amea-apps/identity'));
if (!in_array($appIdentity, ['public', 'staff'], true)) {
    http_response_code(503);
    exit('Application indisponible.');
}
define('AMEA_APP', $appIdentity);
chdir('/opt/amea');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$routes = require '/opt/amea-apps/routes.php';
$path = $path === '/index.php' ? '/' : $path;
if (!in_array($path, $routes[$appIdentity], true)) {
    http_response_code(404);
    exit('Page introuvable.');
}
$postRoutes = ['/register.php', '/registration-details.php', '/kyc-correction.php', '/accept-cgu.php', '/login.php', '/forgot-password.php', '/reset-password.php', '/students.php', '/edit-student.php', '/kyc-detail.php', '/trash.php', '/manage-slider.php', '/communications.php', '/export.php', '/settings.php', '/users.php', '/add-user.php', '/edit-user.php', '/profile.php', '/upgrade-levels.php', '/confirm-upgrade.php'];
$allowedMethods = $path === '/confirm-upgrade.php' ? ['POST'] : (in_array($path, $postRoutes, true) ? ['GET', 'POST'] : ['GET']);
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
