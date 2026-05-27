<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions/utility-functions.php';

// Load .env into $_ENV
$_envFile = __DIR__ . '/../.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_contains($_line, '=') && !str_starts_with(ltrim($_line), '#')) {
            [$_k, $_v] = explode('=', $_line, 2);
            $_ENV[trim($_k)] = trim($_v);
        }
    }
    unset($_envFile, $_line, $_k, $_v);
}

use Amea\Core\Router;

$router = new Router();

// Define routes
$router->add('GET', '/', 'HomeController@index');
$router->add('GET', '/login.php', 'AuthController@login');
$router->add('POST', '/login.php', 'AuthController@login');
$router->add('GET', '/logout.php', 'AuthController@logout');

// KYC Registration Workflow
$router->add('GET', '/register.php', 'RegistrationController@showForm');
$router->add('POST', '/register.php', 'RegistrationController@register');
$router->add('GET', '/registration-details.php', 'RegistrationController@review');
$router->add('POST', '/registration-details.php', 'RegistrationController@confirm');

// Admin KYC Dashboard
$router->add('GET', '/kyc-list.php', 'KYCController@index');
$router->add('GET', '/kyc-detail.php', 'KYCController@review');
$router->add('POST', '/kyc-detail.php', 'KYCController@decide');

// KYC Correction Loop
$router->add('GET', '/kyc-correction.php', 'CorrectionController@edit');
$router->add('POST', '/kyc-correction.php', 'CorrectionController@update');

// Add more routes as we migrate...

return $router;
