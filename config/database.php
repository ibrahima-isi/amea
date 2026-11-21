<?php
/**
 * Database bootstrap for historical migrations.
 */

setlocale(LC_ALL, 'fr_FR.UTF-8');
require_once __DIR__ . '/../vendor/autoload.php';

Amea\Config\Environment::load(dirname(__DIR__));

// Preserve the connection variable used by the historical scripts.
$conn = \Amea\Config\Database::fromEnv()->getConnection();
