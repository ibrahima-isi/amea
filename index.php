<?php

/**
 * Front Controller.
 * File: index.php
 */

$router = require_once 'src/bootstrap.php';
$router->dispatch();
