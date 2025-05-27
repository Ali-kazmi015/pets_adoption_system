<?php
/**
 * Secure Feedback Processing (Updated with user_id)
 * Pet Adoption System
 */

session_start();
require_once 'db_connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}


// Check for spam content
function isSpam($content) {
    $spam_keywords = ['viagra', 'casino', 'lottery', 'winner', 'congratulations', 'free money', 'click here', 'limited time', 'act now'];
    $content_lower = strtolower($content);
    foreach ($spam_keywords as $keyword) {
        if (strpos($content_lower, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

// Rate limiting for feedback submission
function checkFeedbackRateLimit() {
    $rate_limit_key = 'feedback_rate_limit_' . ($_SESSION['user_id'] ?? session_id());
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = ['count' => 0, 'last_submission' => 0];
    }
    $rate_limit = $_SESSION[$rate_limit_key];
    $current_time = time();
    if ($current_time - $rate_limit['last_submission'] > 3600) {
        $_SESSION[$rate_limit_key] = ['count' => 0, 'last_submission' => $current_time];
        return;
    }
    if ($rate_limit['count'] >= 3) {
        throw new Exception("You can only submit 3 feedback messages per hour. Please try again later.");
    }
}

// Record feedback submission
function recordFeedbackSubmission() {
    $rate_limit_key = 'feedback_rate_limit_' . ($_SESSION['user_id'] ?? session_id());
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = ['count' => 0, 'last_submission' => time()];
    }
    $_SESSION[$rate_limit_key]['count']++;
    $_SESSION[$rate_limit_key]['last_submission'] = time();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $response = [
        'success' => false,
        'message' => '',
        'errors' => []
    ];
    
    try {
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception("Invalid security token. Please refresh the page and try again.");
        }

        checkFeedbackRateLimit();

        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $comments = sanitizeInput($_POST['comments'] ?? '');

        if (empty($name)) {
            $response['errors'][] = "Name is required";
        } elseif (strlen($name) < 2) {
            $response['errors'][] = "Name must be at least 2 characters long";
        } elseif (strlen($name) > 100) {
            $response['errors'][] = "Name must not exceed 100 characters";
        }

        if (empty($email)) {
            $response['errors'][] = "Email address is required";
        } elseif (!validateEmail($email)) {
            $response['errors'][] = "Please enter a valid email address";
        }

        if (empty($comments)) {
            $response['errors'][] = "Comments are required";
        } elseif (strlen($comments) < 10) {
            $response['errors'][] = "Comments must be at least 10 characters long";
        } elseif (strlen($comments) > 2000) {
            $response['errors'][] = "Comments must not exceed 2000 characters";
        } elseif (isSpam($comments)) {
            $response['errors'][] = "Your message appears to contain spam content. Please revise and try again.";
        }

        $user_id = $_SESSION['user_id'] ?? null;

        if (empty($response['errors'])) {
            $sql = "INSERT INTO feedback (name, email, comments, status, user_id) VALUES (?, ?, ?, 'new', ?)";
            $result = executeUpdate($sql, "sssi", [$name, $email, $comments, $user_id]);

            if ($result) {
                $response['success'] = true;
                $response['message'] = "Thank you for your feedback! We appreciate your input and will review it soon. Your feedback ID is #" . $result . ".";
                recordFeedbackSubmission();
                unset($_POST);
                error_log("Feedback received from: " . $email . " (ID: " . $result . ")");
            } else {
                throw new Exception("Failed to submit feedback. Please try again.");
            }
        }
    } catch (Exception $e) {
        $response['errors'][] = $e->getMessage();
        error_log("Feedback error: " . $e->getMessage());
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($response['success']) {
        echo "<script>
            alert('" . addslashes($response['message']) . "');
            window.location.href = 'home.html';
        </script>";
    } else {
        $error_message = implode("\\n", $response['errors']);
        echo "<script>
            alert('Feedback submission failed:\\n" . addslashes($error_message) . "');
            window.location.href = 'feedback.html';
        </script>";
    }
    exit;
}

header("Location: feedback.html");
exit;
?>
