<?php
/**
 * Fixed User Registration
 * Pet Adoption System
 */

session_start();
include 'db_connection.php';

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if (!preg_match("/[A-Z]/", $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match("/[a-z]/", $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}

/**
 * Check if email already exists
 */
function emailExists($email) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $response = [
        'success' => false,
        'message' => '',
        'errors' => []
    ];
    
    try {
        // Sanitize and validate input
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Debug: Log the input data
        error_log("Signup attempt - Name: '$name', Email: '$email', Password length: " . strlen($password));
        
        // Validation
        if (empty($name)) {
            $response['errors'][] = "Name is required";
        } elseif (strlen($name) < 2) {
            $response['errors'][] = "Name must be at least 2 characters long";
        } elseif (strlen($name) > 100) {
            $response['errors'][] = "Name must not exceed 100 characters";
        }
        
        if (empty($email)) {
            $response['errors'][] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['errors'][] = "Please enter a valid email address";
        } elseif (emailExists($email)) {
            $response['errors'][] = "An account with this email already exists";
        }
        
        if (empty($password)) {
            $response['errors'][] = "Password is required";
        } else {
            $password_errors = validatePassword($password);
            $response['errors'] = array_merge($response['errors'], $password_errors);
        }
        
        if ($password !== $confirm_password) {
            $response['errors'][] = "Passwords do not match";
        }
        
        // If validation passes, create user
        if (empty($response['errors'])) {
            // Hash password securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user into database using direct connection
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                $response['success'] = true;
                $response['message'] = "Account created successfully! You can now login. User ID: " . $user_id;
                
                error_log("User created successfully - ID: $user_id, Name: $name, Email: $email");
                
            } else {
                error_log("Database insert failed: " . $stmt->error);
                throw new Exception("Failed to create account. Database error: " . $stmt->error);
            }
            
            $stmt->close();
        }
        
    } catch (Exception $e) {
        $response['errors'][] = $e->getMessage();
        error_log("Signup error: " . $e->getMessage());
    }
    
    // For regular form submission, show alert and redirect
    if ($response['success']) {
        echo "<script>
            alert('" . addslashes($response['message']) . "');
            window.location.href = 'login.html';
        </script>";
    } else {
        $error_message = implode("\\n", $response['errors']);
        echo "<script>
            alert('Registration failed:\\n" . addslashes($error_message) . "');
            window.location.href = 'signup.html';
        </script>";
    }
    exit;
}

// If not POST request, redirect to signup page
header("Location: signup.html");
exit;
?>