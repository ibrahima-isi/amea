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
if ($appIdentity === 'public' && $path !== '/' && in_array($path, $routes['staff'], true)) {
    $staffOrigin = \Amea\Core\StaffNavigation::origin();
    if ($staffOrigin === null) {
        http_response_code(503);
        exit('Administration temporairement indisponible.');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array('GET', \Amea\Core\StaffNavigation::methods($path), true)) {
        $target = \Amea\Core\StaffNavigation::legacyTarget($path, (string)($_SERVER['QUERY_STRING'] ?? ''));
        header('Location: ' . $staffOrigin . ($target ?? $path), true, 302);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(409);
        header('Content-Type: text/html; charset=UTF-8');
        $login = htmlspecialchars($staffOrigin . '/login.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="fr"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Formulaire déplacé</title><main><h1>Ce formulaire a changé d’adresse</h1><p>Votre action n’a pas été exécutée. Connectez-vous à l’administration, puis ouvrez un nouveau formulaire pour la reprendre.</p><a href="' . $login . '">Se connecter à l’administration</a></main></html>';
        exit;
    }
    http_response_code(405);
    header('Allow: ' . implode(', ', array_unique(array_merge(array_intersect(['GET'], \Amea\Core\StaffNavigation::methods($path)), ['POST']))));
    exit('Méthode non autorisée.');
}
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
