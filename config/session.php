<?php

// Durée de vie de la session en secondes (30 minutes)
$session_lifetime = 1800;

// Configurer la durée de vie du cookie de session
ini_set('session.cookie_lifetime', 0);

// Configurer la durée de vie de la session côté serveur
ini_set('session.gc_maxlifetime', $session_lifetime);

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier l'expiration de la session
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_lifetime)) {
    // La session a expiré
    session_unset();
    session_destroy();
    
    // Si l'utilisateur était connecté, rediriger vers login
    // Sinon, juste redémarrer une session fraîche (implicite au prochain appel)
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header("Location: login.php?expired=1");
        exit();
    }
}

// Mettre à jour le temps de la dernière activité pour tout le monde
$_SESSION['last_activity'] = time();

// Vérifier l'expiration de la session uniquement pour les utilisateurs connectés
if (isset($_SESSION['user_id'])) {
    // La logique d'expiration est déjà gérée ci-dessus
}
