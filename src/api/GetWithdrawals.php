<?php
/**
 * Get Withdrawals for an Organization
 * Returns withdrawal history for a specific organization
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

$organizationId = $_GET['organizationId'] ?? null;

if (empty($organizationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Organization ID is required']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Get all withdrawals for this organization
    $stmt = $db->prepare("
        SELECT 
            w.withdrawalId,
            w.amount,
            w.status,
            w.bankAccountNumber,
            w.purpose,
            w.requestedAt,
            w.processedAt,
            w.remarks,
            CASE 
                WHEN a.adminId IS NOT NULL THEN u.name
                ELSE NULL 
            END as processedByName
        FROM Withdrawal w
        LEFT JOIN Admin a ON w.processedBy = a.adminId
        LEFT JOIN User u ON a.userId = u.userId
        WHERE w.organizationId = ?
        ORDER BY w.requestedAt DESC
    ");
    
    $stmt->execute([$organizationId]);
    $withdrawals = $stmt->fetchAll();
    
    // Calculate totals by status
    $totalPending = 0;
    $totalApproved = 0;
    $totalCompleted = 0;
    
    foreach ($withdrawals as $w) {
        $amount = floatval($w['amount']);
        switch ($w['status']) {
            case 'pending':
                $totalPending += $amount;
                break;
            case 'approved':
                $totalApproved += $amount;
                break;
            case 'completed':
                $totalCompleted += $amount;
                break;
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($withdrawals),
        'totals' => [
            'pending' => $totalPending,
            'approved' => $totalApproved,
            'completed' => $totalCompleted
        ],
        'data' => $withdrawals
    ]);
    
} catch (PDOException $e) {
    error_log("Get Withdrawals Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve withdrawals.'
    ]);
} catch (Exception $e) {
    error_log("Get Withdrawals Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
