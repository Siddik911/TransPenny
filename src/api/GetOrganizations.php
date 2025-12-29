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
    
    // Get all organizations with user info
    $stmt = $db->prepare("
        SELECT 
            o.organizationId,
            o.verificationStatus,
            o.registrationNumber,
            o.website,
            o.description,
            o.totalReceived,
            o.availableBalance,
            o.registeredAt,
            u.name,
            u.email,
            u.phoneNumber
        FROM Organization o
        INNER JOIN User u ON o.userId = u.userId
        WHERE u.isActive = TRUE
        ORDER BY o.registeredAt DESC
    ");
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
