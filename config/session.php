<?php
/**
 * config/session.php — backward-compat bootstrap
 * Instantiates Session and starts it; exposes $session for controllers that use it.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$session = new \Amea\Core\Session();
$session->start();
