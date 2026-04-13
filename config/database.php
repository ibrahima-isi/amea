<?php
/**
 * config/database.php — backward-compat bootstrap
 * Loads .env, creates the Database singleton, exposes $conn for legacy controllers.
 */

setlocale(LC_ALL, 'fr_FR.UTF-8');
require_once __DIR__ . '/../vendor/autoload.php';

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

// Create PDO via singleton; expose as $conn for existing controllers
$conn = \Amea\Config\Database::fromEnv()->getConnection();
