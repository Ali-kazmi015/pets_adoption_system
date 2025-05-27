<?php
/**
 * Secure User Login
 * Pet Adoption System
 */

session_start();
include 'db_connection.php';

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Rate limiting for login attempts
 */
function checkRateLimit($email) {
    $attempts_key = 'login_attempts_' . md5($email);
    $lockout_key = 'login_lockout_' . md5($email);
    
    // Check if user is locked out
    if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > time()) {
        $remaining = $_SESSION[$lockout_key] - time();
        throw new Exception("Too many failed attempts. Try again in " . ceil($remaining / 60) . " minutes.");
    }
    
    // Initialize attempts if not set
    if (!isset($_SESSION[$attempts_key])) {
        $_SESSION[$attempts_key] = ['count' => 0, 'last_attempt' => time()];
    }
    
    $attempts = $_SESSION[$attempts_key];
    
    // Reset attempts if more than 15 minutes have passed
    if (time() - $attempts['last_attempt'] > 900) {
        $_SESSION[$attempts_key] = ['count' => 0, 'last_attempt' => time()];
        return;
    }
    
    // Check if maximum attempts exceeded
    if ($attempts['count'] >= 5) {
        $_SESSION[$lockout_key] = time() + 1800; // 30-minute lockout
        throw new Exception("Too many failed attempts. Account locked for 30 minutes.");
    }
}

/**
 * Record failed login attempt
 */
function recordFailedAttempt($email) {
    $attempts_key = 'login_attempts_' . md5($email);
    
    if (!isset($_SESSION[$attempts_key])) {
        $_SESSION[$attempts_key] = ['count' => 0, 'last_attempt' => time()];
    }
    
    $_SESSION[$attempts_key]['count']++;
    $_SESSION[$attempts_key]['last_attempt'] = time();
}

/**
 * Clear login attempts on successful login
 */
function clearFailedAttempts($email) {
    $attempts_key = 'login_attempts_' . md5($email);
    $lockout_key = 'login_lockout_' . md5($email);
    
    unset($_SESSION[$attempts_key]);
    unset($_SESSION[$lockout_key]);
}

/**
 * Create secure session
 */
function createSecureSession($user) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // Store user information in session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Set session timeout (24 hours)
    $_SESSION['session_timeout'] = time() + (24 * 60 * 60);
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
        session_destroy();
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    return true;
}

// Check if user is already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin_panel.html");
    } else {
        header("Location: home.html");
    }
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $response = [
        'success' => false,
        'message' => '',
        'redirect' => ''
    ];
    
    try {
        // Sanitize input
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Basic validation
        if (empty($email) || empty($password)) {
            throw new Exception("Please enter both email and password.");
        }
        
        if (!validateEmail($email)) {
            throw new Exception("Please enter a valid email address.");
        }
        
        // Check rate limiting
        checkRateLimit($email);
        
        // Query user from database
        $sql = "SELECT id, name, email, password, role, created_at FROM users WHERE email = ?";
        $result = executeQuery($sql, "s", [$email]);
        
        if (!$result || $result->num_rows === 0) {
            recordFailedAttempt($email);
            throw new Exception("Invalid email or password.");
        }
        
        $user = $result->fetch_assoc();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            recordFailedAttempt($email);
            throw new Exception("Invalid email or password.");
        }
        
        // Check if password needs rehashing (for security updates)
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            executeUpdate("UPDATE users SET password = ? WHERE id = ?", "si", [$new_hash, $user['id']]);
        }
        
        // Clear failed attempts
        clearFailedAttempts($email);
        
        // Create secure session
        createSecureSession($user);
        
        // Prepare response
        $response['success'] = true;
        $response['message'] = "Login successful! Welcome back, " . htmlspecialchars($user['name']) . "!";
        
        // Determine redirect based on user role
        if ($user['role'] === 'admin') {
            $response['redirect'] = 'admin_panel.html';
        } else {
            $response['redirect'] = 'home.html';
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Login error for " . ($email ?? 'unknown') . ": " . $e->getMessage());
    }
    
    // Return JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // For regular form submission, show alert and redirect
    if ($response['success']) {
        echo "<script>
            alert('" . addslashes($response['message']) . "');
            window.location.href = '" . $response['redirect'] . "';
        </script>";
    } else {
        echo "<script>
            alert('" . addslashes($response['message']) . "');
            window.location.href = 'login.html';
        </script>";
    }
    exit;
}

// If not POST request, redirect to login page
header("Location: login.html");
exit;
?>