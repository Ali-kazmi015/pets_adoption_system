<?php
/**
 * Get Available Pets for Adoption
 * Pet Adoption System
 */

header('Content-Type: application/json');
include 'db_connection.php';

try {
    // Query to get all available pets (not adopted)
    $sql = "SELECT id, name, species, age, description, image FROM pets WHERE is_adopted = 0 ORDER BY created_at DESC";
    $result = executeQuery($sql);
    
    $pets = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $pets[] = [
                'id' => (int)$row['id'],
                'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                'species' => htmlspecialchars($row['species'], ENT_QUOTES, 'UTF-8'),
                'age' => (float)$row['age'],
                'description' => htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'),
                'image' => htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8')
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $pets,
        'count' => count($pets)
    ]);
    
} catch (Exception $e) {
    error_log("Get pets error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load pets',
        'data' => []
    ]);
}
?>