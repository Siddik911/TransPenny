<?php
/**
 * Get Impact Stories and Withdrawals for a specific Donor
 * Returns:
 * 1. Impact stories linked to the donor's donations via DonationAllocation
 * 2. Withdrawals that used the donor's donation funds (FIFO allocation)
 * 
 * This allows donors to see how their money was spent through the withdrawal process.
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
    
    // Verify donor exists
    $stmt = $db->prepare("SELECT donorId FROM Donor WHERE donorId = ?");
    $stmt->execute([$donorId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found']);
        exit();
    }
    
    // Get all allocations from this donor's donations that are linked to withdrawals
    // This shows how their donations were used
    $stmt = $db->prepare("
        SELECT 
            da.allocationId,
            da.amountSpent as yourContribution,
            da.purpose as allocationPurpose,
            da.spentAt,
            da.status as allocationStatus,
            d.donationId,
            d.amountTotal as donationAmount,
            d.donatedAt,
            w.withdrawalId,
            w.amount as withdrawalAmount,
            w.status as withdrawalStatus,
            w.purpose as withdrawalPurpose,
            w.requestedAt as withdrawalDate,
            w.processedAt as withdrawalProcessedAt,
            w.bankAccountNumber,
            u.name as organizationName,
            o.organizationId,
            i.storyId,
            i.title as impactTitle,
            i.content as impactContent,
            i.mediaUrl,
            i.mediaType,
            i.geoTag,
            i.totalFunding as impactTotalFunding,
            i.publishedAt as impactPublishedAt,
            i.isPublished
        FROM DonationAllocation da
        INNER JOIN Donation d ON da.donationId = d.donationId
        INNER JOIN Withdrawal w ON da.withdrawalId = w.withdrawalId
        INNER JOIN Organization o ON w.organizationId = o.organizationId
        INNER JOIN User u ON o.userId = u.userId
        LEFT JOIN ImpactStory i ON da.impactStoryId = i.storyId
        WHERE d.donorId = ?
        ORDER BY da.spentAt DESC
    ");
    
    $stmt->execute([$donorId]);
    $allocations = $stmt->fetchAll();
    
    // Also get impact stories that might be linked but without withdrawals (direct impact tracking)
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
            i.isPublished,
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
    $impactStories = $stmt->fetchAll();
    
    // Calculate totals
    $totalWithdrawalContribution = 0;
    $totalImpactContribution = 0;
    
    foreach ($allocations as $alloc) {
        $totalWithdrawalContribution += floatval($alloc['yourContribution']);
    }
    
    // Unique impact contributions (avoid double counting)
    $seenStories = [];
    foreach ($impactStories as $story) {
        if (!in_array($story['storyId'], $seenStories)) {
            $totalImpactContribution += floatval($story['yourContribution']);
            $seenStories[] = $story['storyId'];
        }
    }
    
    // Group allocations by withdrawal for better display
    $withdrawalGroups = [];
    foreach ($allocations as $alloc) {
        $wId = $alloc['withdrawalId'];
        if (!isset($withdrawalGroups[$wId])) {
            $withdrawalGroups[$wId] = [
                'withdrawalId' => $wId,
                'organizationName' => $alloc['organizationName'],
                'organizationId' => $alloc['organizationId'],
                'withdrawalAmount' => floatval($alloc['withdrawalAmount']),
                'withdrawalPurpose' => $alloc['withdrawalPurpose'],
                'withdrawalStatus' => $alloc['withdrawalStatus'],
                'withdrawalDate' => $alloc['withdrawalDate'],
                'processedAt' => $alloc['withdrawalProcessedAt'],
                'yourTotalContribution' => 0,
                'impactStory' => null,
                'allocations' => []
            ];
            
            // Add impact story if exists
            if ($alloc['storyId']) {
                $withdrawalGroups[$wId]['impactStory'] = [
                    'storyId' => $alloc['storyId'],
                    'title' => $alloc['impactTitle'],
                    'content' => $alloc['impactContent'],
                    'mediaUrl' => $alloc['mediaUrl'],
                    'geoTag' => $alloc['geoTag'],
                    'publishedAt' => $alloc['impactPublishedAt'],
                    'isPublished' => $alloc['isPublished']
                ];
            }
        }
        
        $withdrawalGroups[$wId]['yourTotalContribution'] += floatval($alloc['yourContribution']);
        $withdrawalGroups[$wId]['allocations'][] = [
            'allocationId' => $alloc['allocationId'],
            'donationId' => $alloc['donationId'],
            'amount' => floatval($alloc['yourContribution']),
            'donatedAt' => $alloc['donatedAt'],
            'spentAt' => $alloc['spentAt']
        ];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'counts' => [
            'withdrawals' => count($withdrawalGroups),
            'impactStories' => count($impactStories)
        ],
        'totals' => [
            'withdrawalContribution' => $totalWithdrawalContribution,
            'impactContribution' => $totalImpactContribution
        ],
        'withdrawals' => array_values($withdrawalGroups),
        'impactStories' => $impactStories,
        // Legacy format for backward compatibility
        'count' => count($impactStories) + count($withdrawalGroups),
        'totalContribution' => $totalWithdrawalContribution,
        'data' => array_merge(
            array_map(function($w) {
                return [
                    'type' => 'withdrawal',
                    'id' => $w['withdrawalId'],
                    'title' => 'Withdrawal: ' . $w['withdrawalPurpose'],
                    'content' => 'Fund withdrawal for: ' . $w['withdrawalPurpose'],
                    'organizationName' => $w['organizationName'],
                    'organizationId' => $w['organizationId'],
                    'yourContribution' => $w['yourTotalContribution'],
                    'publishedAt' => $w['withdrawalDate'],
                    'status' => $w['withdrawalStatus'],
                    'impactStory' => $w['impactStory']
                ];
            }, array_values($withdrawalGroups)),
            array_map(function($s) {
                return [
                    'type' => 'impact_story',
                    'id' => $s['storyId'],
                    'storyId' => $s['storyId'],
                    'title' => $s['title'],
                    'content' => $s['content'],
                    'mediaUrl' => $s['mediaUrl'],
                    'mediaType' => $s['mediaType'],
                    'geoTag' => $s['geoTag'],
                    'totalFunding' => floatval($s['totalFunding']),
                    'organizationName' => $s['organizationName'],
                    'organizationId' => $s['organizationId'],
                    'yourContribution' => floatval($s['yourContribution']),
                    'publishedAt' => $s['publishedAt']
                ];
            }, $impactStories)
        )
    ]);
    
} catch (PDOException $e) {
    error_log("Get Donor Impacts Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve impact data.'
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
