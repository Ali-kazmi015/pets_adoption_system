<?php
session_start();
include 'db_connection.php';

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Email validation
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// CSRF protection
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $response = ['success' => false, 'message' => '', 'errors' => []];

    try {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("You must be logged in to submit an adoption request.");
        }
        $user_id = (int)$_SESSION['user_id'];

        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception("Invalid CSRF token.");
        }

        $pet_id = isset($_POST['petId']) ? (int)$_POST['petId'] : 0;
        $user_email = sanitize($_POST['userEmail'] ?? '');
        $reason = sanitize($_POST['reason'] ?? '');

        if ($pet_id <= 0) $response['errors'][] = "Invalid pet selected.";
        if (empty($user_email)) $response['errors'][] = "Email is required.";
        elseif (!isValidEmail($user_email)) $response['errors'][] = "Invalid email format.";
        if (empty($reason)) $response['errors'][] = "Reason is required.";
        elseif (strlen($reason) < 20) $response['errors'][] = "Reason must be at least 20 characters.";
        elseif (strlen($reason) > 1000) $response['errors'][] = "Reason must not exceed 1000 characters.";

        if (empty($response['errors'])) {
            // Check request limit (max 3 per 24 hours)
            $limit_sql = "SELECT COUNT(*) AS count FROM adoption_requests WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY";
            $limit_result = executeQuery($limit_sql, "i", [$user_id]);
            if ($limit_result && $limit_result->fetch_assoc()['count'] >= 3) {
                throw new Exception("You can only submit 3 adoption requests per day.");
            }

            // Check if pet is available
            $pet_sql = "SELECT id, name, is_adopted FROM pets WHERE id = ?";
            $pet_result = executeQuery($pet_sql, "i", [$pet_id]);
            if (!$pet_result || $pet_result->num_rows === 0 || $pet_result->fetch_assoc()['is_adopted']) {
                throw new Exception("This pet is not available for adoption.");
            }

            // Check for pending request
            $pending_sql = "SELECT request_id FROM adoption_requests WHERE pet_id = ? AND user_id = ? AND status = 'pending'";
            $pending_result = executeQuery($pending_sql, "ii", [$pet_id, $user_id]);
            if ($pending_result && $pending_result->num_rows > 0) {
                throw new Exception("You already have a pending request for this pet.");
            }

            // Insert request
            $insert_sql = "INSERT INTO adoption_requests (pet_id, user_id, user_email, reason, status) VALUES (?, ?, ?, ?, 'pending')";
            $insert_result = executeUpdate($insert_sql, "iiss", [$pet_id, $user_id, $user_email, $reason]);

            if ($insert_result) {
                $response['success'] = true;
                $response['message'] = "Adoption request submitted successfully! Request ID: #" . $insert_result;
            } else {
                throw new Exception("Something went wrong while submitting your request.");
            }
        }
    } catch (Exception $e) {
        $response['errors'][] = $e->getMessage();
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($response['success']) {
        echo "<script>alert('" . addslashes($response['message']) . "'); window.location.href='adopt.html';</script>";
    } else {
        $error_msg = implode("\\n", $response['errors']);
        echo "<script>alert('Adoption request failed:\\n$error_msg'); window.location.href='adopt.html';</script>";
    }
    exit;
}

header("Location: adopt.html");
exit;
?>
