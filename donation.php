<?php
session_start();
include 'db_connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSRF token generator
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Unique function: specific to donations
function validateAmount($amount) {
    if (!is_numeric($amount)) {
        return "Amount must be a valid number";
    }
    $amount = floatval($amount);
    if ($amount < 100) return "Minimum donation amount is PKR 100";
    if ($amount > 1000000) return "Maximum donation amount is PKR 1,000,000";
    return null;
}

// Unique function: specific to donations
function validatePaymentMethod($method) {
    $allowed_methods = ['Bank Transfer', 'Easypaisa', 'JazzCash', 'Credit Card'];
    return in_array($method, $allowed_methods);
}

// Unique function: specific to donations
function sanitizeAccountNumber($acc_number) {
    return preg_replace('/[^A-Za-z0-9\s\-]/', '', trim($acc_number));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $response = [
        'success' => false,
        'message' => '',
        'errors' => []
    ];

    try {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("You must be logged in to make a donation.");
        }

        $user_id = $_SESSION['user_id'];

        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception("Invalid security token. Please refresh and try again.");
        }

        // Use functions from db_connection.php
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $amount = sanitizeInput($_POST['amount'] ?? '');
        $method = sanitizeInput($_POST['method'] ?? '');
        $acc_number = sanitizeAccountNumber($_POST['acc_number'] ?? '');

        if (empty($name)) {
            $response['errors'][] = "Full name is required";
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

        if (empty($amount)) {
            $response['errors'][] = "Donation amount is required";
        } else {
            $amount_error = validateAmount($amount);
            if ($amount_error) {
                $response['errors'][] = $amount_error;
            }
        }

        if (empty($method)) {
            $response['errors'][] = "Payment method is required";
        } elseif (!validatePaymentMethod($method)) {
            $response['errors'][] = "Invalid payment method selected";
        }

        if (empty($acc_number)) {
            $response['errors'][] = "Account number is required";
        } elseif (strlen($acc_number) < 5) {
            $response['errors'][] = "Account number must be at least 5 characters long";
        }

        if (empty($response['errors'])) {
            $amount_decimal = number_format(floatval($amount), 2, '.', '');

            $sql = "INSERT INTO donations (user_id, donor_name, donor_email, amount, payment_method, acc_no, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')";

            $result = executeUpdate($sql, "issdss", [
                $user_id,
                $name,
                $email,
                $amount_decimal,
                $method,
                $acc_number
            ]);

            if ($result) {
                $response['success'] = true;
                $response['message'] = "Thank you for your generous donation of PKR " . number_format($amount_decimal, 2) .
                    "! Your donation ID is #" . $result . ". We will process it within 24 hours.";
                unset($_POST);
                error_log("Donation received: PKR " . $amount_decimal . " from " . $email);
            } else {
                throw new Exception("Failed to process donation. Please try again.");
            }
        }

    } catch (Exception $e) {
        $response['errors'][] = $e->getMessage();
        error_log("Donation error: " . $e->getMessage());
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
            alert('Donation failed:\\n" . addslashes($error_message) . "');
            window.location.href = 'donation.html';
        </script>";
    }
    exit;
}

header("Location: donation.html");
exit;
?>
