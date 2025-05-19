<?php
/**
 * Script d'initialisation pour créer un utilisateur administrateur
 * Fichier: init-admin.php
 * 
 * Ce script crée un utilisateur administrateur par défaut dans la base de données.
 * Pour des raisons de sécurité, ce script devrait être exécuté une seule fois, 
 * puis supprimé du serveur.
 */

// Inclure la configuration de la base de données
require_once 'config/database.php';

// Vérifier si un utilisateur administrateur existe déjà
$checkSql = "SELECT COUNT(*) FROM user WHERE role = 'admin'";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->execute();
$adminCount = $checkStmt->fetchColumn();

// Si un administrateur existe déjà, afficher un message et sortir
if ($adminCount > 0) {
    echo "Un utilisateur administrateur existe déjà dans la base de données.<br>";
    echo "Pour des raisons de sécurité, vous ne pouvez pas exécuter ce script à nouveau.<br>";
    echo "Si vous avez besoin de créer un nouvel administrateur, utilisez l'interface d'administration existante.";
    exit();
}

// Informations de l'administrateur par défaut
$username = "admin";
$password = "admin123"; // Ce mot de passe devrait être changé après la première connexion
$nom = "Administrateur";
$prenom = "AMEA";
$email = "admin@amea.org";
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
    echo "<h1>Initialisation réussie</h1>";
    echo "<p>L'utilisateur administrateur a été créé avec succès.</p>";
    echo "<p>Nom d'utilisateur: <strong>$username</strong><br>";
    echo "Mot de passe: <strong>$password</strong></p>";
    echo "<p>Pour des raisons de sécurité, veuillez :</p>";
    echo "<ol>";
    echo "<li>Vous connecter à l'interface d'administration</li>";
    echo "<li>Changer immédiatement le mot de passe par défaut</li>";
    echo "<li>Supprimer ce fichier du serveur</li>";
    echo "</ol>";
    echo "<p><a href='login.php'>Aller à la page de connexion</a></p>";
    
} catch(PDOException $e) {
    echo "<h1>Erreur</h1>";
    echo "<p>Une erreur s'est produite lors de la création de l'utilisateur administrateur :</p>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>