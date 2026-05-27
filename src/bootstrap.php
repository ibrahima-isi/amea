<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Amea\Core\Router;

$router = new Router();

// Define routes
$router->add('GET',  '/',               'HomeController@index');
$router->add('GET',  '/login.php',      'AuthController@login');
$router->add('POST', '/login.php',      'AuthController@login');
$router->add('GET',  '/logout.php',     'AuthController@logout');

// Add more routes as we migrate...

return $router;
