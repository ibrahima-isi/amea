<?php
/**
 * Configuration de la connexion à la base de données
 * Fichier : config/database.php
 */
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

// Informations de connexion à la base de données
define('DB_HOST', env('DB_HOST'));     // Hôte de la base de données
define('DB_NAME', env('DB_NAME'));        // Nom de la base de données
define('DB_USER', env('DB_USER'));          // Nom d'utilisateur de la base de données (à modifier)
define('DB_PASS', env('DB_PASS'));              // Mot de passe de la base de données (à modifier)


// Établir la connexion à la base de données
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    // Configurer PDO pour qu'il génère des exceptions en cas d'erreur
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Définir le jeu de caractères à utf8
    $conn->exec("SET NAMES utf8");
} catch(PDOException $e) {
    // En cas d'erreur de connexion
    logError('Erreur de connexion à la base de données', $e);
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}
