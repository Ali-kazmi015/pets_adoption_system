<?php
session_start();
include 'db_connection.php';

// Error reporting for debugging (remove in production)
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

// Validate species
function validateSpecies($species) {
    $allowed = ['Dog', 'Cat', 'Rabbit', 'Bird', 'Hamster', 'Other'];
    return in_array($species, $allowed);
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input));
}



// Handle image upload
function processImageUpload($file) {
    $uploadDir = "uploads/surrenders/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload error.");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Max file size is 5MB.");
    }

    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $type = mime_content_type($file['tmp_name']);
    if (!in_array($type, $allowed)) {
        throw new Exception("Invalid image format.");
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = uniqid('surrender_', true) . '.' . $ext;
    $target = $uploadDir . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception("Image upload failed.");
    }

    return $target;
}

// Check for duplicate request
function checkDuplicate($email, $petName) {
    $sql = "SELECT id FROM surrender_requests 
            WHERE user_email = ? AND pet_name = ? 
            AND status = 'Pending' 
            AND submitted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $result = executeQuery($sql, "ss", [$email, $petName]);
    return $result && $result->num_rows > 0;
}

// Handle POST submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $response = ['success' => false, 'message' => '', 'errors' => []];

    try {
        // CSRF check
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception("Invalid CSRF token.");
        }

        // Sanitize inputs
        $pet_name = sanitize($_POST['pet_name'] ?? '');
        $species = sanitize($_POST['species'] ?? '');
        $age = sanitize($_POST['age'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $user_email = sanitize($_POST['user_email'] ?? '');

        // Validate
        if (strlen($pet_name) < 2 || strlen($pet_name) > 100) {
            $response['errors'][] = "Pet name must be between 2 and 100 characters.";
        }

        if (!validateSpecies($species)) {
            $response['errors'][] = "Invalid species.";
        }

        if (!is_numeric($age) || floatval($age) < 0 || floatval($age) > 30) {
            $response['errors'][] = "Age must be a number between 0 and 30.";
        }

        if (strlen($description) < 20 || strlen($description) > 2000) {
            $response['errors'][] = "Description must be between 20 and 2000 characters.";
        }

        if (!validateEmail($user_email)) {
            $response['errors'][] = "Invalid email address.";
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            $response['errors'][] = "Pet image is required.";
        }

        if (empty($response['errors'])) {
            if (checkDuplicate($user_email, $pet_name)) {
                throw new Exception("You already submitted a similar request within the last 24 hours.");
            }

            $image_path = processImageUpload($_FILES['image']);

            $user_id = $_SESSION['user_id'] ?? null;
            if (!$user_id) {
                throw new Exception("User not logged in.");
            }

            // Insert into DB
            $sql = "INSERT INTO surrender_requests 
                    (user_id, pet_name, species, age, description, user_email, image_path, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
            $result = executeUpdate($sql, "issdsss", [
                $user_id,
                $pet_name,
                $species,
                floatval($age),
                $description,
                $user_email,
                $image_path
            ]);

            if ($result) {
                $response['success'] = true;
                $response['message'] = "Surrender request submitted successfully. Request ID: #$result";
            } else {
                if (isset($image_path) && file_exists($image_path)) unlink($image_path);
                throw new Exception("Database error.");
            }
        }
    } catch (Exception $e) {
        $response['errors'][] = $e->getMessage();
        if (isset($image_path) && file_exists($image_path)) unlink($image_path);
    }

    // Output for AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Non-AJAX response
    if ($response['success']) {
        echo "<script>alert('".$response['message']."'); window.location.href='adopt.html';</script>";
    } else {
        $errors = implode("\\n", $response['errors']);
        echo "<script>alert('Error:\\n$errors'); window.history.back();</script>";
    }

    exit;
}

// CSRF token for form
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Surrender a Pet</title>
</head>
<body>
    <h1>Surrender a Pet</h1>
    <form action="surrender.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />

        <label for="pet_name">Pet Name:</label>
        <input type="text" name="pet_name" required><br>

        <label for="species">Species:</label>
        <select name="species" required>
            <option value="">--Select--</option>
            <option value="Dog">Dog</option>
            <option value="Cat">Cat</option>
            <option value="Rabbit">Rabbit</option>
            <option value="Bird">Bird</option>
            <option value="Hamster">Hamster</option>
            <option value="Other">Other</option>
        </select><br>

        <label for="age">Age (years):</label>
        <input type="number" name="age" step="0.1" min="0" max="30" required><br>

        <label for="description">Reason for Surrender:</label><br>
        <textarea name="description" rows="5" cols="40" required></textarea><br>

        <label for="user_email">Your Email:</label>
        <input type="email" name="user_email" required><br>

        <label for="image">Pet Image:</label>
        <input type="file" name="image" accept="image/*" required><br>

        <button type="submit">Submit Request</button>
    </form>
</body>
</html>
