<?php
/**
 * Update User Status - Admin can activate/deactivate donors and organizations
 * Only admins can perform this action
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$adminId = $input['adminId'] ?? null;
$userId = $input['userId'] ?? null;
$isActive = isset($input['isActive']) ? (bool)$input['isActive'] : null;

// Validation
if (empty($adminId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
    exit();
}

if (empty($userId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

if ($isActive === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Status (isActive) is required']);
    exit();
}

try {
    $db = getDBConnection();
    $db->beginTransaction();
    
    // Verify admin exists and has permission
    $stmt = $db->prepare("
        SELECT a.adminId, a.role, u.isActive 
        FROM Admin a 
        INNER JOIN User u ON a.userId = u.userId 
        WHERE a.adminId = ? AND u.isActive = TRUE
    ");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin not found or inactive']);
        exit();
    }
    
    // Get user information
    $stmt = $db->prepare("SELECT userId, userType, name, email, isActive FROM User WHERE userId = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Prevent admin from deactivating themselves or other admins (unless super_admin)
    if ($user['userType'] === 'admin' && $admin['role'] !== 'super_admin') {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only super admins can modify admin accounts']);
        exit();
    }
    
    $previousStatus = $user['isActive'] ? 'active' : 'inactive';
    $newStatus = $isActive ? 'active' : 'inactive';
    
    // No change needed
    if (($user['isActive'] && $isActive) || (!$user['isActive'] && !$isActive)) {
        $db->rollBack();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User status is already ' . $newStatus,
            'data' => [
                'userId' => $userId,
                'name' => $user['name'],
                'email' => $user['email'],
                'userType' => $user['userType'],
                'isActive' => $isActive
            ]
        ]);
        exit();
    }
    
    // Update user status
    $stmt = $db->prepare("UPDATE User SET isActive = ? WHERE userId = ?");
    $stmt->execute([$isActive ? 1 : 0, $userId]);
    
    // Generate UUID for audit log
    $stmt = $db->query("SELECT UUID() as uuid");
    $logUuid = $stmt->fetch()['uuid'];
    
    // Log the action
    $stmt = $db->prepare("
        INSERT INTO AuditLog (
            logId,
            adminId,
            actionType,
            entityType,
            entityId,
            description,
            changeDetails,
            performedAt
        ) VALUES (?, ?, 'update', ?, ?, ?, ?, NOW())
    ");
    
    $entityType = $user['userType'];
    $description = ($isActive ? 'Activated' : 'Deactivated') . ' ' . $entityType . ': ' . $user['name'] . ' (' . $user['email'] . ')';
    $changeDetails = json_encode([
        'previousStatus' => $previousStatus,
        'newStatus' => $newStatus,
        'userId' => $userId,
        'userType' => $user['userType']
    ]);
    
    $stmt->execute([
        $logUuid,
        $adminId,
        $entityType,
        $userId,
        $description,
        $changeDetails
    ]);
    
    $db->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'User ' . ($isActive ? 'activated' : 'deactivated') . ' successfully',
        'data' => [
            'userId' => $userId,
            'name' => $user['name'],
            'email' => $user['email'],
            'userType' => $user['userType'],
            'isActive' => $isActive
        ]
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Update User Status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update user status.'
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Update User Status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
