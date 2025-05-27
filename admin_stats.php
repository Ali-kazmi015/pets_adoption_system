<?php
/**
 * Admin Dashboard Statistics API
 * Pet Adoption System
 */

session_start();
include 'db_connection.php';
include 'auth.php';

// Require admin access
requireAdmin();

header('Content-Type: application/json');

try {
    $data = [];
    
    // Total pets in system
    $result = executeQuery("SELECT COUNT(*) as total FROM pets");
    $data['totalPets'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Available pets for adoption
    $result = executeQuery("SELECT COUNT(*) as total FROM pets WHERE is_adopted = 0");
    $data['availablePets'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Adopted pets
    $result = executeQuery("SELECT COUNT(*) as total FROM pets WHERE is_adopted = 1");
    $data['adoptedPets'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Pending adoption requests
    $result = executeQuery("SELECT COUNT(*) as total FROM adoption_requests WHERE status = 'pending'");
    $data['pendingAdoptions'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Pending surrender requests
    $result = executeQuery("SELECT COUNT(*) as total FROM surrender_requests WHERE status = 'Pending'");
    $data['pendingSurrenders'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Total users
    $result = executeQuery("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $data['totalUsers'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Recent feedback
    $result = executeQuery("SELECT COUNT(*) as total FROM feedback WHERE status = 'new'");
    $data['newFeedback'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Lost/Found reports
    $result = executeQuery("SELECT COUNT(*) as total FROM lost_found_reports WHERE is_active = 1");
    $data['activeLostFound'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Monthly statistics
    $result = executeQuery("SELECT COUNT(*) as total FROM adoption_requests WHERE status = 'approved' AND MONTH(processed_at) = MONTH(NOW()) AND YEAR(processed_at) = YEAR(NOW())");
    $data['monthlyAdoptions'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Recent activity (last 7 days)
    $result = executeQuery("SELECT COUNT(*) as total FROM adoption_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $data['weeklyRequests'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Success metrics
    $data['success'] = true;
    $data['timestamp'] = date('Y-m-d H:i:s');
    
    echo json_encode($data);
    
} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics',
        'totalPets' => 0,
        'pendingAdoptions' => 0,
        'pendingSurrenders' => 0
    ]);
}
?>