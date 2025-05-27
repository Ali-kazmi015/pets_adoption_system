<?php
session_start();
include 'db_connection.php';

header('Content-Type: application/json');

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateToken();
}

echo json_encode([
    'csrf_token' => $_SESSION['csrf_token']
]);
?>