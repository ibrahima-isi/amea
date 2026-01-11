<?php
/**
 * Logout script.
 * File: logout.php
 */

// Start session
require_once 'config/session.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>