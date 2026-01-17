<?php
/**
 * Validate Organization - Approve or Reject organization registration
 * Only accessible by admin users
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

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['adminId']) || empty($input['organizationId']) || empty($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Admin ID, Organization ID, and action are required']);
    exit();
}

$adminId = $input['adminId'];
$organizationId = $input['organizationId'];
$action = strtolower($input['action']); // 'approve' or 'reject'
$remarks = $input['remarks'] ?? null;

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action. Must be "approve" or "reject"']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Verify admin exists and has proper role
    $stmt = $db->prepare("
        SELECT a.adminId, a.role, u.name as adminName 
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
    
    // Get organization current status
    $stmt = $db->prepare("
        SELECT o.organizationId, o.verificationStatus, o.userId, u.name as orgName, u.email
        FROM Organization o
        INNER JOIN User u ON o.userId = u.userId
        WHERE o.organizationId = ?
    ");
    $stmt->execute([$organizationId]);
    $organization = $stmt->fetch();
    
    if (!$organization) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit();
    }
    
    $previousStatus = $organization['verificationStatus'];
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    
    // Start transaction for atomic status update
    $db->beginTransaction();
    
    try {
        // Update organization status
        $stmt = $db->prepare("
            UPDATE Organization 
            SET verificationStatus = ?,
                verifiedAt = CASE WHEN ? = 'approved' THEN NOW() ELSE verifiedAt END
            WHERE organizationId = ?
        ");
        $stmt->execute([$newStatus, $newStatus, $organizationId]);
        
        // Record in VerificationHistory
        $stmt = $db->prepare("
            INSERT INTO VerificationHistory 
            (organizationId, reviewedBy, previousStatus, newStatus, remarks, reviewedAt)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $organizationId,
            $adminId,
            $previousStatus,
            $newStatus,
            $remarks
        ]);
        
        // Record in AuditLog
        $stmt = $db->prepare("
            INSERT INTO AuditLog 
            (adminId, actionType, entityType, entityId, description, changeDetails, performedAt)
            VALUES (?, ?, 'organization', ?, ?, ?, NOW())
        ");
        
        $description = sprintf(
            "Admin '%s' %s organization '%s' (previously: %s)",
            $admin['adminName'],
            $action === 'approve' ? 'approved' : 'rejected',
            $organization['orgName'],
            $previousStatus
        );
        
        $changeDetails = json_encode([
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'remarks' => $remarks,
            'organizationEmail' => $organization['email']
        ]);
        
        $stmt->execute([
            $adminId,
            $action,
            $organizationId,
            $description,
            $changeDetails
        ]);
        
        $db->commit();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => sprintf('Organization %s successfully', $action === 'approve' ? 'approved' : 'rejected'),
            'data' => [
                'organizationId' => $organizationId,
                'organizationName' => $organization['orgName'],
                'previousStatus' => $previousStatus,
                'newStatus' => $newStatus,
                'reviewedBy' => $admin['adminName'],
                'reviewedAt' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Validate Organization Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to validate organization'
    ]);
} catch (Exception $e) {
    error_log("Validate Organization Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
}
?>
