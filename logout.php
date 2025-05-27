<?php
/**
 * Secure Logout
 * Pet Adoption System
 */

session_start();
include 'auth.php';

// Log the logout action
if (isset($_SESSION['user_email'])) {
    error_log("User logged out: " . $_SESSION['user_email']);
}

// Clear all session data
destroySession();

// Clear any remember me cookies if they exist
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to index page
header("Location: index.html");
exit;
?>