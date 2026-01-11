<?php

// Session lifetime in seconds (30 minutes)
$session_lifetime = 1800;

// Configure session cookie lifetime
ini_set('session.cookie_lifetime', 0);

// Configure server-side session lifetime
ini_set('session.gc_maxlifetime', $session_lifetime);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session expiration
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_lifetime)) {
    // Session expired
    session_unset();
    session_destroy();
    
    // If user was logged in, redirect to login
    // Otherwise, just start a fresh session (implicit on next call)
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header("Location: login.php?expired=1");
        exit();
    }
}

// Update last activity time for everyone
$_SESSION['last_activity'] = time();

// Check session expiration only for logged-in users
if (isset($_SESSION['user_id'])) {
    // Expiration logic is already handled above
}
