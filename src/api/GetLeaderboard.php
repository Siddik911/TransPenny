<?php
/**
 * Get Leaderboard - Top Donors and Organizations
 * Supports both donor and organization leaderboards
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

// Get leaderboard type from query parameter (default: 'donors')
$type = $_GET['type'] ?? 'donors';
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;

try {
    $db = getDBConnection();
    
    if ($type === 'organizations') {
        // Get top organizations by total raised
        $stmt = $db->prepare("
            SELECT 
                o.organizationId,
                u.name,
                u.email,
                o.verificationStatus,
                o.totalReceived,
                o.totalSpent,
                o.availableBalance,
                o.registeredAt,
                (SELECT COUNT(*) FROM Donation d WHERE d.organizationId = o.organizationId) as donationCount,
                (SELECT COUNT(*) FROM ImpactStory i WHERE i.organizationId = o.organizationId AND i.isPublished = TRUE) as impactCount
            FROM Organization o
            INNER JOIN User u ON o.userId = u.userId
            WHERE u.isActive = TRUE
            AND o.verificationStatus = 'approved'
            AND o.totalReceived > 0
            ORDER BY o.totalReceived DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $leaderboard = $stmt->fetchAll();
        
        // Add rank to each entry
        $rankedLeaderboard = [];
        $rank = 1;
        foreach ($leaderboard as $org) {
            $org['rank'] = $rank;
            $rankedLeaderboard[] = $org;
            $rank++;
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'type' => 'organizations',
            'count' => count($rankedLeaderboard),
            'data' => $rankedLeaderboard
        ]);
        
    } else {
        // Get top donors by total donations (default)
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
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
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
            'type' => 'donors',
            'count' => count($rankedLeaderboard),
            'data' => $rankedLeaderboard
        ]);
    }
    
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
