<?php
/**
 * Secure Adoption Request Approval System
 * Pet Adoption System
 */

session_start();
include 'db_connection.php';
include 'auth.php';

// Require admin access
requireAdmin();

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Log admin action
 */
function logAdminAction($admin_id, $action, $target_type, $target_id, $description) {
    $sql = "INSERT INTO admin_logs (admin_id, action, target_type, target_id, description) VALUES (?, ?, ?, ?, ?)";
    executeUpdate($sql, "issds", [$admin_id, $action, $target_type, $target_id, $description]);
}

/**
 * Send email notification (placeholder - implement actual email sending)
 */
function sendNotificationEmail($email, $subject, $message) {
    // Placeholder for email functionality
    error_log("Email notification - To: {$email}, Subject: {$subject}");
    // In production, implement actual email sending using PHPMailer or similar
}

// Generate CSRF token for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateToken();
}

// Handle approve/reject POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Invalid security token");
        }
        
        $action = sanitizeInput($_POST['action']);
        $request_id = (int)($_POST['requestId'] ?? 0);
        $admin_id = $_SESSION['user_id'];
        
        if (!in_array($action, ['approve', 'reject'])) {
            throw new Exception("Invalid action");
        }
        
        if ($request_id <= 0) {
            throw new Exception("Invalid request ID");
        }
        
        // Get adoption request details
        $sql = "SELECT ar.request_id, ar.pet_id, ar.user_email, ar.reason, ar.status,
                       p.name as pet_name, p.species, p.age
                FROM adoption_requests ar
                LEFT JOIN pets p ON ar.pet_id = p.id
                WHERE ar.request_id = ? AND ar.status = 'pending'";
        
        $result = executeQuery($sql, "i", [$request_id]);
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception("Adoption request not found or already processed");
        }
        
        $request = $result->fetch_assoc();
        
        if ($action === 'approve') {
            // Check if pet is still available
            $pet_check = executeQuery("SELECT is_adopted FROM pets WHERE id = ?", "i", [$request['pet_id']]);
            if (!$pet_check) {
                throw new Exception("Pet not found");
            }
            
            $pet_data = $pet_check->fetch_assoc();
            if ($pet_data['is_adopted'] == 1) {
                throw new Exception("Pet has already been adopted");
            }
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Mark pet as adopted
                $update_pet = executeUpdate("UPDATE pets SET is_adopted = 1 WHERE id = ?", "i", [$request['pet_id']]);
                if (!$update_pet) {
                    throw new Exception("Failed to update pet status");
                }
                
                // Update request status to approved
                $update_request = executeUpdate(
                    "UPDATE adoption_requests SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE request_id = ?",
                    "ii", [$admin_id, $request_id]
                );
                if (!$update_request) {
                    throw new Exception("Failed to update request status");
                }
                
                // Reject all other pending requests for this pet
                executeUpdate(
                    "UPDATE adoption_requests SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE pet_id = ? AND status = 'pending' AND request_id != ?",
                    "iii", [$admin_id, $request['pet_id'], $request_id]
                );
                
                $conn->commit();
                
                // Log admin action
                logAdminAction($admin_id, 'approve_adoption', 'adoption_request', $request_id, 
                    "Approved adoption of {$request['pet_name']} for {$request['user_email']}");
                
                // Send notification email
                sendNotificationEmail($request['user_email'], 
                    "Adoption Request Approved - {$request['pet_name']}", 
                    "Congratulations! Your adoption request for {$request['pet_name']} has been approved.");
                
                $response['success'] = true;
                $response['message'] = "Adoption request approved successfully!";
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            
        } elseif ($action === 'reject') {
            // Update request status to rejected
            $update_result = executeUpdate(
                "UPDATE adoption_requests SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE request_id = ?",
                "ii", [$admin_id, $request_id]
            );
            
            if (!$update_result) {
                throw new Exception("Failed to reject adoption request");
            }
            
            // Log admin action
            logAdminAction($admin_id, 'reject_adoption', 'adoption_request', $request_id, 
                "Rejected adoption of {$request['pet_name']} for {$request['user_email']}");
            
            // Send notification email
            sendNotificationEmail($request['user_email'], 
                "Adoption Request Update - {$request['pet_name']}", 
                "We regret to inform you that your adoption request for {$request['pet_name']} was not approved at this time.");
            
            $response['success'] = true;
            $response['message'] = "Adoption request rejected successfully!";
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Adoption approval error: " . $e->getMessage());
    }
    
    // Return JSON for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Redirect back to the page
    header("Location: approve_adoptions.php?result=" . ($response['success'] ? 'success' : 'error') . "&message=" . urlencode($response['message']));
    exit;
}

// Fetch pending adoption requests with pet info
$sql = "SELECT ar.request_id, ar.user_email, ar.reason, ar.created_at,
               p.name, p.species, p.age, p.image
        FROM adoption_requests ar
        LEFT JOIN pets p ON ar.pet_id = p.id
        WHERE ar.status = 'pending'
        ORDER BY ar.created_at ASC";

$result = executeQuery($sql);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Approve Adoption Requests</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 0; }
    nav.navbar {
      background-color: #4a90e2;
      color: white;
      padding: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    nav.navbar .logo img {
      height: 40px;
      vertical-align: middle;
    }
    nav.navbar .logo span {
      font-weight: bold;
      font-size: 1.5rem;
      margin-left: 0.5rem;
    }
    .nav-links {
      list-style: none;
      display: flex;
      gap: 1rem;
      margin: 0;
      padding: 0;
    }
    .nav-links li a {
      color: white;
      text-decoration: none;
      font-weight: 600;
    }
    .container {
      max-width: 1000px;
      margin: 2rem auto;
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    h1 {
      margin-bottom: 1.5rem;
      color: #333;
    }
    .request-card {
      border: 1px solid #ccc;
      padding: 1rem;
      margin-bottom: 1rem;
      display: flex;
      gap: 1rem;
      border-radius: 8px;
      background: #fff;
      align-items: flex-start;
    }
    .request-card img {
      max-width: 150px;
      border-radius: 8px;
      object-fit: cover;
      height: 120px;
    }
    .details {
      flex-grow: 1;
      color: #444;
    }
    .details h2 {
      margin: 0 0 0.5rem 0;
      color: #222;
    }
    .details .meta {
      font-size: 0.9em;
      color: #666;
      margin-bottom: 0.5rem;
    }
    .actions {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      min-width: 120px;
    }
    button {
      padding: 0.5rem 1rem;
      border: none;
      cursor: pointer;
      border-radius: 5px;
      font-weight: bold;
      transition: background-color 0.3s ease;
    }
    button.approve { background-color: #4CAF50; color: white; }
    button.approve:hover { background-color: #45a049; }
    button.reject { background-color: #f44336; color: white; }
    button.reject:hover { background-color: #e53935; }
    .no-requests {
      text-align: center;
      padding: 3rem;
      color: #666;
    }
    .stats {
      background: #e3f2fd;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
    }
    .alert {
      padding: 10px;
      margin-bottom: 15px;
      border-radius: 4px;
    }
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body>

<nav class="navbar">
  <div class="logo">
    <img src="colorful_logo.png" alt="Logo" />
    <span>Pet Welfare - Admin</span>
  </div>
  <ul class="nav-links">
    <li><a href="admin_panel.html">Dashboard</a></li>
    <li><a href="approve_surrenders.php">Approve Surrenders</a></li>
    <!-- <li><a href="../home.html">Home</a></li> -->
    <li><a href="logout.php">Logout</a></li>
  </ul>
</nav>

<div class="container">
  <h1>Pending Adoption Requests</h1>
  
  <?php
  // Show result message if present
  if (isset($_GET['result']) && isset($_GET['message'])) {
    $alertClass = $_GET['result'] === 'success' ? 'alert-success' : 'alert-error';
    echo '<div class="alert ' . $alertClass . '">' . htmlspecialchars($_GET['message']) . '</div>';
  }
  
  $total_requests = $result ? $result->num_rows : 0;
  ?>
  
  <div class="stats">
    <strong>Total Pending Requests: <?= $total_requests ?></strong>
  </div>

  <?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="request-card">
        <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" />
        <div class="details">
          <h2><?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['species']) ?>)</h2>
          <div class="meta">
            <strong>Age:</strong> <?= htmlspecialchars($row['age']) ?> years |
            <strong>Submitted:</strong> <?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>
          </div>
          <p><strong>Applicant Email:</strong> <?= htmlspecialchars($row['user_email']) ?></p>
          <p><strong>Reason for Adoption:</strong></p>
          <p style="font-style: italic; background: #f9f9f9; padding: 0.5rem; border-radius: 4px;">
            <?= nl2br(htmlspecialchars($row['reason'])) ?>
          </p>
        </div>
        <div class="actions">
          <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>" />
            <input type="hidden" name="requestId" value="<?= $row['request_id'] ?>" />
            <button type="submit" name="action" value="approve" class="approve" 
                    onclick="return confirm('Are you sure you want to approve this adoption request?')">
              Approve
            </button>
          </form>
          <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>" />
            <input type="hidden" name="requestId" value="<?= $row['request_id'] ?>" />
            <button type="submit" name="action" value="reject" class="reject"
                    onclick="return confirm('Are you sure you want to reject this adoption request?')">
              Reject
            </button>
          </form>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="no-requests">
      <h3>No pending adoption requests</h3>
      <p>All adoption requests have been processed.</p>
    </div>
  <?php endif; ?>
</div>

</body>
</html>