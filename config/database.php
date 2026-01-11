<?php
/**
 * Database connection configuration.
 * File: config/database.php
 */

// Force locale to UTF-8 for string handling
setlocale(LC_ALL, 'fr_FR.UTF-8');

require_once  __DIR__ . "/../functions/utility-functions.php";

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';
if(file_exists($envFile)){
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line){
        if(str_contains($line, '=') && !str_starts_with($line, '#')){
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim(($key))] = trim($value);
        }
    }
}

// Database connection information
define('DB_HOST', env('DB_HOST'));     // Database host
define('DB_NAME', env('DB_NAME'));        // Database name
define('DB_USER', env('DB_USER'));          // Database username
define('DB_PASS', env('DB_PASS'));              // Database password


// Establish database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $conn = new PDO($dsn, DB_USER, DB_PASS);
    $conn->exec("SET NAMES 'utf8mb4'");
    // Configure PDO to throw exceptions on error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // In case of connection error
    logError('Database connection error', $e);
    die("Database connection error. Please contact the administrator.");
}
