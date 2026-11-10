<?php

require_once __DIR__ . '/../vendor/autoload.php';

$session = new Amea\Core\Session();
$session->startRequest(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'login.php');
