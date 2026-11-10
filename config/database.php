<?php
/**
 * config/database.php — backward-compat bootstrap
 * Loads .env, creates the Database singleton, exposes $conn for legacy controllers.
 */

setlocale(LC_ALL, 'fr_FR.UTF-8');
require_once __DIR__ . '/../vendor/autoload.php';

Amea\Config\Environment::load(dirname(__DIR__));

// Create PDO via singleton; expose as $conn for existing controllers
$conn = \Amea\Config\Database::fromEnv()->getConnection();
