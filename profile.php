<?php
session_start();
include 'db_connection.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get current user profile data
$user_id = $_SESSION['user_id'];
$profile_data = null;

$stmt = $conn->prepare("SELECT name, email, phone, address FROM profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $profile_data = $result->fetch_assoc();
} else {
    // Get basic info from users table
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $profile_data = [
            'name' => $user_data['name'],
            'email' => $user_data['email'],
            'phone' => '',
            'address' => ''
        ];
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $response = [
        'success' => false,
        'message' => '',
        'errors' => []
    ];

    try {
        // CSRF check
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Invalid security token. Please refresh the page.");
        }

        // Sanitize inputs
        $name = htmlspecialchars(trim($_POST['name'] ?? ''));
        $email = htmlspecialchars(trim($_POST['email'] ?? ''));
        $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
        $address = htmlspecialchars(trim($_POST['address'] ?? ''));

        // Validation
        if (strlen($name) < 2 || strlen($name) > 100) {
            $response['errors'][] = "Name must be between 2 and 100 characters.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['errors'][] = "Enter a valid email address.";
        }

        if (!empty($phone)) {
            $phone_cleaned = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($phone_cleaned) < 7 || strlen($phone_cleaned) > 15) {
                $response['errors'][] = "Enter a valid phone number.";
            } else {
                $phone = $phone_cleaned;
            }
        }

        if (!empty($address) && strlen($address) > 500) {
            $response['errors'][] = "Address must be less than 500 characters.";
        }

        if (empty($response['errors'])) {
            // Check if profile exists
            $check_stmt = $conn->prepare("SELECT id FROM profile WHERE user_id = ?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                // Update existing profile
                $update_stmt = $conn->prepare("UPDATE profile SET name=?, email=?, phone=?, address=? WHERE user_id=?");
                $update_stmt->bind_param("ssssi", $name, $email, $phone, $address, $user_id);
                $success = $update_stmt->execute();
            } else {
                // Insert new profile
                $insert_stmt = $conn->prepare("INSERT INTO profile (user_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("issss", $user_id, $name, $email, $phone, $address);
                $success = $insert_stmt->execute();
            }

            if ($success) {
                // Update user's main name in users table
                $user_stmt = $conn->prepare("UPDATE users SET name=? WHERE id=?");
                $user_stmt->bind_param("si", $name, $user_id);
                $user_stmt->execute();

                $_SESSION['user_name'] = $name;
                $response['success'] = true;
                $response['message'] = "Profile updated successfully!";
                
                // Update profile data for display
                $profile_data = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address
                ];
            } else {
                throw new Exception("Error updating profile.");
            }
        }

    } catch (Exception $e) {
        $response['errors'][] = $e->getMessage();
    }

    // Show result via alert
    if ($response['success']) {
        echo "<script>alert('" . addslashes($response['message']) . "');</script>";
    } else {
        $error_message = implode("\\n", $response['errors']);
        echo "<script>alert('Update failed:\\n" . addslashes($error_message) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Update Profile</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <header>
    <nav class="navbar">
      <div class="logo">
        <img src="colorful_logo.png" alt="Cat Logo">
        <span>Pet Welfare</span>
      </div>
      <ul class="nav-links">
        <li><a href="home.html">Home</a></li>
        <li><a href="adopt.html">View & Adopt Pets</a></li>
        <li><a href="surrender.html">Surrender</a></li>
        <li><a href="lost-found.html">Lost & Found</a></li>
        <li><a href="profile.php" class="active">Profile</a></li>
        <li><a href="feedback.html">Feedback</a></li>
        <li><a href="donation.html">Donate</a></li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
    </nav>
  </header>

  <main>
    <section class="form-section">
      <h2>Update Your Profile</h2>
      
      <?php if (isset($response) && !$response['success'] && !empty($response['errors'])): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
          <?php foreach ($response['errors'] as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      
      <form action="profile.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <input type="text" name="name" placeholder="Full name" maxlength="100" 
               value="<?= htmlspecialchars($profile_data['name'] ?? '') ?>" required>
        
        <input type="email" name="email" placeholder="Email address" 
               value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>" required>
        
        <input type="tel" name="phone" placeholder="Contact number (optional)" maxlength="15"
               value="<?= htmlspecialchars($profile_data['phone'] ?? '') ?>">
        
        <textarea name="address" placeholder="Address (optional)" maxlength="500"><?= htmlspecialchars($profile_data['address'] ?? '') ?></textarea>
        
        <button type="submit">Update Profile</button>
      </form>
    </section>
  </main>

  <footer>
    <p>&copy; 2025 Pet Welfare & Adoption Centre</p>
  </footer>
</body>
</html>