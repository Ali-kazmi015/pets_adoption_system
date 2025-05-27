<?php
session_start();
include 'db_connection.php';

// Enable error reporting (development only)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSRF token utilities
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('You must be logged in to submit a lost or found report.'); window.location.href = 'login.html';</script>";
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $response = ['success' => false, 'errors' => [], 'message' => ''];

    try {
        // CSRF token check
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception("Invalid security token. Please refresh and try again.");
        }

        // Sanitize inputs
        $user_id = $_SESSION['user_id'];
        $reporter_name = sanitizeInput($_POST['name'] ?? '');
        $contact_info = sanitizeInput($_POST['contact_no'] ?? '');
        $pet_description = sanitizeInput($_POST['pet_description'] ?? '');
        $location = sanitizeInput($_POST['last_seen'] ?? '');
        $status = sanitizeInput($_POST['method'] ?? '');

        // Validation
        if (strlen($reporter_name) < 2 || strlen($reporter_name) > 100) {
            $response['errors'][] = "Name must be between 2 and 100 characters.";
        }

        if (!preg_match('/^03[0-9]{9}$/', $contact_info)) {
            $response['errors'][] = "Invalid contact number format. Example: 03001234567";
        }

        if (strlen($pet_description) < 20 || strlen($pet_description) > 1000) {
            $response['errors'][] = "Pet description must be between 20 and 1000 characters.";
        }

        if (strlen($location) < 5 || strlen($location) > 255) {
            $response['errors'][] = "Last seen location must be between 5 and 255 characters.";
        }

        if (!in_array($status, ['lost', 'found'])) {
            $response['errors'][] = "Invalid status selected.";
        }

        // If no errors, insert into DB
        if (empty($response['errors'])) {
            $sql = "INSERT INTO lost_found_reports (user_id, reporter_name, contact_info, pet_description, location, status, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)";

            $result = executeUpdate($sql, "isssss", [
                $user_id,
                $reporter_name,
                $contact_info,
                $pet_description,
                $location,
                $status
            ]);

            if ($result) {
                $response['success'] = true;
                $response['message'] = "Thank you! Your '$status' report has been submitted.";
                unset($_POST);
            } else {
                throw new Exception("Failed to submit the report. Please try again.");
            }
        }
    } catch (Exception $e) {
        $response['errors'][] = $e->getMessage();
    }

    // If using AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Regular form response
    if ($response['success']) {
        echo "<script>
            alert('" . addslashes($response['message']) . "');
            window.location.href = 'home.html';
        </script>";
    } else {
        $error_message = implode("\\n", $response['errors']);
        echo "<script>
            alert('Submission failed:\\n" . addslashes($error_message) . "');
            window.location.href = 'lost-found.html';
        </script>";
    }

    exit;
}

// Redirect GET requests back to form
header("Location: lost-found.html");
exit;
?>
