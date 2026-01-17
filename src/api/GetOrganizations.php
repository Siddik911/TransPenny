<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDBConnection();
    
    // Check if we should include inactive organizations (for admin view)
    $includeInactive = isset($_GET['includeInactive']) && $_GET['includeInactive'] === 'true';
    
    // Get all organizations with user info
    $sql = "
        SELECT 
            o.organizationId,
            o.userId,
            o.verificationStatus,
            o.registrationNumber,
            o.website,
            o.description,
            o.totalReceived,
            o.availableBalance,
            o.registeredAt,
            o.address,
            u.name,
            u.email,
            u.phoneNumber,
            u.isActive
        FROM Organization o
        INNER JOIN User u ON o.userId = u.userId
    ";
    
    if (!$includeInactive) {
        // By default, show all organizations but include isActive status for admin
        // Admins can see all, regular users only see active
    }
    
    $sql .= " ORDER BY o.registeredAt DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $organizations = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $organizations,
        'count' => count($organizations)
    ]);
    
} catch (PDOException $e) {
    error_log("Get Organizations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve organizations'
    ]);
}
?>
