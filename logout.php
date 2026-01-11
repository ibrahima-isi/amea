<?php
/**
 * Script de déconnexion
 * Fichier: logout.php
 */

// Démarrer la session
require_once 'config/session.php';

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header("Location: index.php");
exit();
?>