<?php
/**
 * Get Donations received by a specific Organization
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
    
    // Get all donations received by this organization
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
            donor.donorId,
            donor.isAnonymous,
            CASE 
                WHEN donor.isAnonymous = TRUE THEN 'Anonymous Donor'
                ELSE u.name 
            END as donorName
        FROM Donation d
        INNER JOIN Donor donor ON d.donorId = donor.donorId
        INNER JOIN User u ON donor.userId = u.userId
        WHERE d.organizationId = ?
        ORDER BY d.donatedAt DESC
    ");
    
    $stmt->execute([$organizationId]);
    $donations = $stmt->fetchAll();
    
    // Calculate totals
    $totalReceived = 0;
    $totalAllocated = 0;
    foreach ($donations as $donation) {
        $totalReceived += floatval($donation['amountTotal']);
        $totalAllocated += floatval($donation['amountAllocated']);
    }
    
    // Get updated organization balance
    $stmt = $db->prepare("SELECT totalReceived, availableBalance FROM Organization WHERE organizationId = ?");
    $stmt->execute([$organizationId]);
    $orgStats = $stmt->fetch();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($donations),
        'totalReceived' => floatval($orgStats['totalReceived'] ?? $totalReceived),
        'availableBalance' => floatval($orgStats['availableBalance'] ?? $totalReceived),
        'data' => $donations
    ]);
    
} catch (PDOException $e) {
    error_log("Get Org Donations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve donations.'
    ]);
} catch (Exception $e) {
    error_log("Get Org Donations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
