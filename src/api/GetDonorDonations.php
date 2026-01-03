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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get donorId from query parameter
$donorId = $_GET['donorId'] ?? null;

if (empty($donorId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Get all donations for this donor with organization info
    $stmt = $db->prepare("
        SELECT 
            d.donationId,
            d.amountTotal,
            d.amountAllocated,
            d.status,
            d.paymentMethod,
            d.transactionReference,
            d.donatedAt,
            d.isRecurring,
            o.organizationId,
            u.name as organizationName,
            o.verificationStatus
        FROM Donation d
        INNER JOIN Organization o ON d.organizationId = o.organizationId
        INNER JOIN User u ON o.userId = u.userId
        WHERE d.donorId = ?
        ORDER BY d.donatedAt DESC
    ");
    
    $stmt->execute([$donorId]);
    $donations = $stmt->fetchAll();
    
    // Calculate summary
    $totalAmount = 0;
    foreach ($donations as $donation) {
        $totalAmount += floatval($donation['amountTotal']);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($donations),
        'totalAmount' => $totalAmount,
        'data' => $donations
    ]);
    
} catch (PDOException $e) {
    error_log("Get Donor Donations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve donations.'
    ]);
} catch (Exception $e) {
    error_log("Get Donor Donations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
