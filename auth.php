<?php
/**
 * Authentication and Session Management
 * Pet Adoption System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in and session is valid
 */
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_timeout'])) {
        return false;
    }
    
    // Check session timeout
    if (time() > $_SESSION['session_timeout']) {
        destroySession();
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.html");
        exit;
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    if (!isAdmin()) {
        if (isLoggedIn()) {
            header("Location: home.html");
        } else {
            header("Location: login.html");
        }
        exit;
    }
}

/**
 * Safely destroy session
 */
function destroySession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    // Prevent XSS attacks
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // HTTPS enforcement (uncomment in production with SSL)
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Content Security Policy (adjust as needed)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
}

// Apply security headers
setSecurityHeaders();
?>