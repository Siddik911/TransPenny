<?php
/**
 * Get Pending Withdrawals - Admin can view all pending withdrawal requests
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

$adminId = $_GET['adminId'] ?? null;
$status = $_GET['status'] ?? 'pending'; // Can be 'pending', 'approved', 'rejected', 'all'

try {
    $db = getDBConnection();
    
    // Verify admin if provided
    if ($adminId) {
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
            echo json_encode(['success' => false, 'message' => 'Admin not found or inactive']);
            exit();
        }
    }
    
    // Build query based on status filter
    $whereClause = "";
    $params = [];
    
    if ($status !== 'all') {
        $validStatuses = ['pending', 'approved', 'rejected', 'completed'];
        if (in_array($status, $validStatuses)) {
            $whereClause = "WHERE w.status = ?";
            $params[] = $status;
        }
    }
    
    $stmt = $db->prepare("
        SELECT 
            w.withdrawalId,
            w.organizationId,
            w.amount,
            w.status,
            w.bankAccountNumber,
            w.purpose,
            w.requestedAt,
            w.processedAt,
            w.processedBy,
            w.remarks,
            u.name as organizationName,
            u.email as organizationEmail,
            o.verificationStatus,
            o.totalReceived,
            o.availableBalance,
            admin_u.name as processedByName
        FROM Withdrawal w
        INNER JOIN Organization o ON w.organizationId = o.organizationId
        INNER JOIN User u ON o.userId = u.userId
        LEFT JOIN Admin a ON w.processedBy = a.adminId
        LEFT JOIN User admin_u ON a.userId = admin_u.userId
        $whereClause
        ORDER BY 
            CASE w.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 4 
            END,
            w.requestedAt DESC
    ");
    
    $stmt->execute($params);
    $withdrawals = $stmt->fetchAll();
    
    // Calculate totals
    $totalPending = 0;
    $totalApproved = 0;
    $totalRejected = 0;
    
    foreach ($withdrawals as $w) {
        if ($w['status'] === 'pending') {
            $totalPending += floatval($w['amount']);
        } elseif ($w['status'] === 'approved' || $w['status'] === 'completed') {
            $totalApproved += floatval($w['amount']);
        } elseif ($w['status'] === 'rejected') {
            $totalRejected += floatval($w['amount']);
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($withdrawals),
        'summary' => [
            'totalPending' => $totalPending,
            'totalApproved' => $totalApproved,
            'totalRejected' => $totalRejected
        ],
        'data' => $withdrawals
    ]);
    
} catch (PDOException $e) {
    error_log("Get Pending Withdrawals Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve withdrawals.'
    ]);
} catch (Exception $e) {
    error_log("Get Pending Withdrawals Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
