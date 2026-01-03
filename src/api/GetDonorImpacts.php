<?php
/**
 * Get Impact Stories for a specific Donor
 * Returns impact stories linked to the donor's donations via FIFO allocation
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

$donorId = $_GET['donorId'] ?? null;

if (empty($donorId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Get impact stories linked to this donor's donations through DonationAllocation
    // The FIFO allocation connects donations to impact stories via withdrawals
    $stmt = $db->prepare("
        SELECT DISTINCT
            i.storyId,
            i.title,
            i.content,
            i.mediaUrl,
            i.mediaType,
            i.geoTag,
            i.totalFunding,
            i.publishedAt,
            i.viewCount,
            u.name as organizationName,
            o.organizationId,
            da.amountSpent as yourContribution,
            da.purpose as allocationPurpose,
            da.spentAt,
            d.donationId,
            d.amountTotal as donationAmount,
            d.donatedAt
        FROM ImpactStory i
        INNER JOIN Organization o ON i.organizationId = o.organizationId
        INNER JOIN User u ON o.userId = u.userId
        INNER JOIN DonationAllocation da ON da.impactStoryId = i.storyId
        INNER JOIN Donation d ON da.donationId = d.donationId
        WHERE d.donorId = ?
        AND i.isPublished = TRUE
        ORDER BY i.publishedAt DESC
    ");
    
    $stmt->execute([$donorId]);
    $impacts = $stmt->fetchAll();
    
    // Calculate total impact
    $totalContribution = 0;
    foreach ($impacts as $impact) {
        $totalContribution += floatval($impact['yourContribution']);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($impacts),
        'totalContribution' => $totalContribution,
        'data' => $impacts
    ]);
    
} catch (PDOException $e) {
    error_log("Get Donor Impacts Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve impact stories.'
    ]);
} catch (Exception $e) {
    error_log("Get Donor Impacts Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
