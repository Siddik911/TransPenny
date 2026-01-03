<?php
/**
 * Get Leaderboard - Top 10 Donors by Total Donations
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

try {
    $db = getDBConnection();
    
    // Get top 10 donors by total donations
    // Respects anonymous setting - shows "Anonymous Donor" for those who opted in
    $stmt = $db->prepare("
        SELECT 
            d.donorId,
            CASE 
                WHEN d.isAnonymous = TRUE THEN 'Anonymous Donor'
                ELSE u.name 
            END as displayName,
            d.isAnonymous,
            d.totalDonated,
            d.donationCount,
            d.lastDonationAt,
            u.createdAt as memberSince
        FROM Donor d
        INNER JOIN User u ON d.userId = u.userId
        WHERE u.isActive = TRUE
        AND d.totalDonated > 0
        ORDER BY d.totalDonated DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $leaderboard = $stmt->fetchAll();
    
    // Add rank to each entry
    $rankedLeaderboard = [];
    $rank = 1;
    foreach ($leaderboard as $donor) {
        $donor['rank'] = $rank;
        $rankedLeaderboard[] = $donor;
        $rank++;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($rankedLeaderboard),
        'data' => $rankedLeaderboard
    ]);
    
} catch (PDOException $e) {
    error_log("Get Leaderboard Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve leaderboard.'
    ]);
} catch (Exception $e) {
    error_log("Get Leaderboard Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
