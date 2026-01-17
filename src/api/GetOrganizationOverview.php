<?php
/**
 * Get Organization Overview - Dashboard data for organizations
 * Returns total raised, available balance, top donors, and stats
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
    
    // Get organization details with user info
    $stmt = $db->prepare("
        SELECT 
            o.organizationId,
            o.verificationStatus,
            o.registrationNumber,
            o.address,
            o.website,
            o.description,
            o.totalReceived,
            o.totalSpent,
            o.availableBalance,
            o.verifiedAt,
            o.registeredAt,
            u.name,
            u.email,
            u.phoneNumber
        FROM Organization o
        INNER JOIN User u ON o.userId = u.userId
        WHERE o.organizationId = ?
    ");
    $stmt->execute([$organizationId]);
    $org = $stmt->fetch();
    
    if (!$org) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit();
    }
    
    // Get top 5 donors for this organization using SQL aggregation
    $stmt = $db->prepare("
        SELECT 
            d.donorId,
            d.isAnonymous,
            CASE 
                WHEN d.isAnonymous = TRUE THEN 'Anonymous Donor'
                ELSE u.name 
            END as displayName,
            SUM(don.amountTotal) as totalAmount,
            COUNT(don.donationId) as donationCount
        FROM Donation don
        INNER JOIN Donor d ON don.donorId = d.donorId
        INNER JOIN User u ON d.userId = u.userId
        WHERE don.organizationId = ?
        GROUP BY d.donorId, d.isAnonymous, u.name
        ORDER BY totalAmount DESC
        LIMIT 5
    ");
    $stmt->execute([$organizationId]);
    $topDonors = $stmt->fetchAll();
    
    // Get donation count
    $stmt = $db->prepare("
        SELECT COUNT(*) as donationCount
        FROM Donation
        WHERE organizationId = ?
    ");
    $stmt->execute([$organizationId]);
    $donationStats = $stmt->fetch();
    
    // Get impact story count
    $stmt = $db->prepare("
        SELECT COUNT(*) as impactCount
        FROM ImpactStory
        WHERE organizationId = ?
    ");
    $stmt->execute([$organizationId]);
    $impactStats = $stmt->fetch();
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'organizationId' => $org['organizationId'],
            'name' => $org['name'],
            'email' => $org['email'],
            'phoneNumber' => $org['phoneNumber'],
            'verificationStatus' => $org['verificationStatus'],
            'registrationNumber' => $org['registrationNumber'],
            'address' => $org['address'],
            'website' => $org['website'],
            'description' => $org['description'],
            'totalReceived' => floatval($org['totalReceived']),
            'totalSpent' => floatval($org['totalSpent']),
            'availableBalance' => floatval($org['availableBalance']),
            'verifiedAt' => $org['verifiedAt'],
            'registeredAt' => $org['registeredAt'],
            'donationCount' => intval($donationStats['donationCount']),
            'impactCount' => intval($impactStats['impactCount']),
            'topDonors' => $topDonors
        ]
    ];
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Get Organization Overview Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve organization data.'
    ]);
} catch (Exception $e) {
    error_log("Get Organization Overview Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
