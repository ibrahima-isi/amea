<?php
/**
 * Script d'initialisation pour créer un utilisateur administrateur
 * Fichier: init-admin.php
 * 
 * Ce script crée un utilisateur administrateur par défaut dans la base de données.
 * Pour des raisons de sécurité, ce script devrait être exécuté une seule fois, 
 * puis supprimé du serveur.
 */

// Restreindre l'exécution au mode CLI pour éviter toute exposition via le web
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Accès interdit.');
}

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Vérifier la présence d'un secret d'exécution pour éviter les exécutions non autorisées
$expectedSecret = getenv('INIT_ADMIN_SECRET');
if ($expectedSecret === false || $expectedSecret === '') {
    echo "La variable d'environnement INIT_ADMIN_SECRET doit être définie pour exécuter ce script." . PHP_EOL;
    exit(1);
}

if ($argc < 7) {
    echo "Utilisation : php init-admin.php <username> <mot_de_passe> <email> <prenom> <nom> <secret>" . PHP_EOL;
    exit(1);
}

$username = trim($argv[1]);
$password = $argv[2];
$email = trim($argv[3]);
$prenom = trim($argv[4]);
$nom = trim($argv[5]);
$providedSecret = $argv[6];

if (!hash_equals($expectedSecret, $providedSecret)) {
    echo "Secret d'exécution invalide." . PHP_EOL;
    exit(1);
}

if (strlen($password) < 12) {
    echo "Le mot de passe doit contenir au moins 12 caractères." . PHP_EOL;
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "L'adresse email fournie n'est pas valide." . PHP_EOL;
    exit(1);
}

// Vérifier si un utilisateur administrateur existe déjà
$checkSql = "SELECT COUNT(*) FROM user WHERE role = 'admin'";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->execute();
$adminCount = $checkStmt->fetchColumn();

// Si un administrateur existe déjà, afficher un message et sortir
if ($adminCount > 0) {
    echo "Un utilisateur administrateur existe déjà dans la base de données." . PHP_EOL;
    echo "Veuillez utiliser l'interface d'administration pour gérer les comptes existants." . PHP_EOL;
    exit();
}

// Informations de l'administrateur fourni
$role = "admin";

// Hacher le mot de passe
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // Insérer l'utilisateur administrateur dans la base de données
    $sql = "INSERT INTO user (username, password, nom, prenom, email, role) 
            VALUES (:username, :password, :nom, :prenom, :email, :role)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':nom', $nom);
    $stmt->bindParam(':prenom', $prenom);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);

    $stmt->execute();

    // Afficher un message de succès
    echo "Initialisation réussie" . PHP_EOL;
    echo "L'utilisateur administrateur \"$username\" a été créé avec succès." . PHP_EOL;
    echo "Connectez-vous et supprimez ce script du serveur." . PHP_EOL;

} catch(PDOException $e) {
    logError("Erreur lors de l'initialisation de l'administrateur", $e);
    echo "Une erreur est survenue lors de la création de l'utilisateur administrateur." . PHP_EOL;
}
?>