<?php
/**
 * Process Withdrawal - Admin approves or rejects withdrawal requests
 * 
 * BUSINESS LOGIC:
 * 1. Admin can approve or reject pending withdrawals
 * 2. On approval: 
 *    - Status changes to 'approved'
 *    - Organization's availableBalance is reduced
 *    - DonationAllocation statuses are updated to 'completed'
 * 3. On rejection:
 *    - Status changes to 'rejected'
 *    - No balance changes (pending was already excluded from available)
 *    - DonationAllocation records are removed or marked as 'rejected'
 * 4. Creates audit log entry
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
$withdrawalId = $input['withdrawalId'] ?? null;
$action = $input['action'] ?? null; // 'approve' or 'reject'
$remarks = trim($input['remarks'] ?? '');

// Validation
if (empty($adminId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
    exit();
}

if (empty($withdrawalId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Withdrawal ID is required']);
    exit();
}

if (empty($action) || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action must be either "approve" or "reject"']);
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
    
    // Only finance_admin or super_admin can process withdrawals
    if (!in_array($admin['role'], ['finance_admin', 'super_admin'])) {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions. Finance admin or super admin required.']);
        exit();
    }
    
    // Get withdrawal with lock
    $stmt = $db->prepare("
        SELECT w.*, o.organizationId, u.name as organizationName
        FROM Withdrawal w
        INNER JOIN Organization o ON w.organizationId = o.organizationId
        INNER JOIN User u ON o.userId = u.userId
        WHERE w.withdrawalId = ?
        FOR UPDATE
    ");
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch();
    
    if (!$withdrawal) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
        exit();
    }
    
    // Check if withdrawal is already processed
    if ($withdrawal['status'] !== 'pending') {
        $db->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Withdrawal has already been processed. Current status: ' . $withdrawal['status']
        ]);
        exit();
    }
    
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $amount = floatval($withdrawal['amount']);
    
    if ($action === 'approve') {
        // Re-verify balance is still available (race condition protection)
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amountTotal), 0) as totalDonations
            FROM Donation WHERE organizationId = ?
        ");
        $stmt->execute([$withdrawal['organizationId']]);
        $totalDonations = floatval($stmt->fetch()['totalDonations']);
        
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) as approvedWithdrawals
            FROM Withdrawal 
            WHERE organizationId = ? AND status IN ('approved', 'completed')
        ");
        $stmt->execute([$withdrawal['organizationId']]);
        $approvedWithdrawals = floatval($stmt->fetch()['approvedWithdrawals']);
        
        $availableAfterApproval = $totalDonations - $approvedWithdrawals - $amount;
        
        if ($availableAfterApproval < 0) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot approve: insufficient balance. Organization may have other approved withdrawals.'
            ]);
            exit();
        }
        
        // Update withdrawal status to approved
        $stmt = $db->prepare("
            UPDATE Withdrawal 
            SET status = 'approved', 
                processedAt = NOW(), 
                processedBy = ?,
                remarks = ?
            WHERE withdrawalId = ?
        ");
        $stmt->execute([$adminId, $remarks, $withdrawalId]);
        
        // Update organization balance
        $stmt = $db->prepare("
            UPDATE Organization 
            SET totalSpent = totalSpent + ?,
                availableBalance = totalReceived - (totalSpent + ?)
            WHERE organizationId = ?
        ");
        $stmt->execute([$amount, $amount, $withdrawal['organizationId']]);
        
        // Update donation allocation statuses to completed
        $stmt = $db->prepare("
            UPDATE DonationAllocation 
            SET status = 'completed' 
            WHERE withdrawalId = ?
        ");
        $stmt->execute([$withdrawalId]);
        
        // Update donation statuses based on allocation
        $stmt = $db->prepare("
            UPDATE Donation d
            SET d.status = CASE
                WHEN d.amountAllocated = 0 THEN 'allocated'
                WHEN (SELECT COALESCE(SUM(da.amountSpent), 0) FROM DonationAllocation da WHERE da.donationId = d.donationId AND da.status = 'completed') >= d.amountTotal THEN 'fully_spent'
                ELSE 'partially_spent'
            END
            WHERE d.donationId IN (
                SELECT DISTINCT donationId FROM DonationAllocation WHERE withdrawalId = ?
            )
        ");
        $stmt->execute([$withdrawalId]);
        
    } else {
        // Reject withdrawal
        $stmt = $db->prepare("
            UPDATE Withdrawal 
            SET status = 'rejected', 
                processedAt = NOW(), 
                processedBy = ?,
                remarks = ?
            WHERE withdrawalId = ?
        ");
        $stmt->execute([$adminId, $remarks, $withdrawalId]);
        
        // Remove or mark allocation records as cancelled
        // We'll delete them since the withdrawal was rejected
        $stmt = $db->prepare("DELETE FROM DonationAllocation WHERE withdrawalId = ?");
        $stmt->execute([$withdrawalId]);
    }
    
    // Create audit log
    $stmt = $db->query("SELECT UUID() as uuid");
    $logUuid = $stmt->fetch()['uuid'];
    
    $description = ($action === 'approve' ? 'Approved' : 'Rejected') . 
                   ' withdrawal of $' . number_format($amount, 2) . 
                   ' for ' . $withdrawal['organizationName'];
    
    $stmt = $db->prepare("
        INSERT INTO AuditLog (
            logId, adminId, actionType, entityType, entityId, description, changeDetails, performedAt
        ) VALUES (?, ?, ?, 'withdrawal', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $logUuid,
        $adminId,
        $action,
        $withdrawalId,
        $description,
        json_encode([
            'previousStatus' => 'pending',
            'newStatus' => $newStatus,
            'amount' => $amount,
            'organizationId' => $withdrawal['organizationId'],
            'remarks' => $remarks
        ])
    ]);
    
    $db->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Withdrawal ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully',
        'data' => [
            'withdrawalId' => $withdrawalId,
            'status' => $newStatus,
            'amount' => $amount,
            'organizationName' => $withdrawal['organizationName'],
            'processedAt' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Process Withdrawal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process withdrawal request.'
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Process Withdrawal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
