<?php

require_once __DIR__ . '/../vendor/autoload.php';

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

// Add more routes as we migrate...

return $router;
