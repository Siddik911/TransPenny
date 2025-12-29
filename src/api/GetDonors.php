<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Get all donors with user information
    $stmt = $db->prepare("
        SELECT 
            d.donorId,
            u.userId,
            u.name,
            u.email,
            u.phoneNumber,
            d.isAnonymous,
            d.totalDonated,
            d.donationCount,
            d.lastDonationAt,
            u.createdAt,
            u.lastLoginAt,
            u.isActive
        FROM Donor d
        INNER JOIN User u ON d.userId = u.userId
        ORDER BY u.createdAt DESC
    ");
    
    $stmt->execute();
    $donors = $stmt->fetchAll();
    
    // Format the response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($donors),
        'data' => $donors
    ]);
    
} catch (PDOException $e) {
    error_log("Get Donors Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve donors.'
    ]);
} catch (Exception $e) {
    error_log("Get Donors Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>