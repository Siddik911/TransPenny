<?php
/**
 * Get Pending Organizations - List organizations awaiting verification
 * Only accessible by admin users
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get admin ID from query parameter for session validation
$adminId = $_GET['adminId'] ?? null;

if (empty($adminId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Verify admin exists and is active
    $stmt = $db->prepare("
        SELECT a.adminId, a.role 
        FROM Admin a 
        INNER JOIN User u ON a.userId = u.userId 
        WHERE a.adminId = ? AND u.isActive = TRUE
    ");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin not found or inactive']);
        exit();
    }
    
    // Get pending organizations with essential details
    $stmt = $db->prepare("
        SELECT 
            o.organizationId,
            o.verificationStatus,
            o.registrationNumber,
            o.address,
            o.website,
            o.description,
            o.registeredAt,
            u.userId,
            u.name,
            u.email,
            u.phoneNumber,
            u.createdAt as userCreatedAt
        FROM Organization o
        INNER JOIN User u ON o.userId = u.userId
        WHERE o.verificationStatus = 'pending'
        AND u.isActive = TRUE
        ORDER BY o.registeredAt ASC
    ");
    $stmt->execute();
    $pendingOrgs = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($pendingOrgs),
        'data' => $pendingOrgs
    ]);
    
} catch (PDOException $e) {
    error_log("Get Pending Organizations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve pending organizations'
    ]);
} catch (Exception $e) {
    error_log("Get Pending Organizations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
}
?>
