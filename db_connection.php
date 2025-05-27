<?php
/**
 * Database Connection File
 * Pet Adoption System
 */

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$database = "pets_adoptions_database";

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection with proper error handling
try {
    $conn = new mysqli($servername, $username, $password, $database);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to UTF-8 to handle special characters properly
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error loading character set utf8mb4: " . $conn->error);
    }
    
    // Set timezone (optional - adjust to your timezone)
    $conn->query("SET time_zone = '+05:00'"); // Pakistan timezone
    
} catch (Exception $e) {
    // Log error (in production, log to file instead of displaying)
    error_log("Database connection error: " . $e->getMessage());
    
    // Display user-friendly error in development
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}

/**
 * Function to close database connection
 */
function closeConnection() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}

/**
 * Function to execute prepared statements safely
 * @param string $sql SQL query with placeholders
 * @param string $types Parameter types (e.g., 'ssi' for string, string, integer)
 * @param array $params Parameters array
 * @return mysqli_result|bool
 */
function executeQuery($sql, $types = '', $params = []) {
    global $conn;
    
    try {
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Query execution error: " . $e->getMessage());
        return false;
    }
}

/**
 * Function to execute INSERT/UPDATE/DELETE queries
 * @param string $sql SQL query with placeholders
 * @param string $types Parameter types
 * @param array $params Parameters array
 * @return bool|int Returns true for success, or inserted ID for INSERT queries
 */
function executeUpdate($sql, $types = '', $params = []) {
    global $conn;
    
    try {
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $insert_id = $conn->insert_id;
        $stmt->close();
        
        // Return insert ID for INSERT queries, or true for other successful queries
        return ($insert_id > 0) ? $insert_id : ($affected_rows > 0);
        
    } catch (Exception $e) {
        error_log("Update execution error: " . $e->getMessage());
        return false;
    }
}

/**
 * Function to sanitize input data
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    global $conn;
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Function to validate email
 * @param string $email Email address
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Function to generate secure random token
 * @param int $length Token length
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Set environment (change to 'production' in live environment)
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

// Register shutdown function to close connection
register_shutdown_function('closeConnection');
?>