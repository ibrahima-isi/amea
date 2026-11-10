<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions/utility-functions.php';

Amea\Config\Environment::load(dirname(__DIR__));

return new Amea\Core\Router();
