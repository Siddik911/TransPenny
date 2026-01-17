<?php
/**
 * Get Impact Stories for a specific Organization
 * Returns all impact stories created by an organization
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
    
    // Verify organization exists
    $stmt = $db->prepare("SELECT organizationId FROM Organization WHERE organizationId = ?");
    $stmt->execute([$organizationId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit();
    }
    
    // Get all impact stories for this organization
    $stmt = $db->prepare("
        SELECT 
            i.storyId,
            i.title,
            i.content,
            i.mediaUrl,
            i.mediaType,
            i.geoTag,
            i.totalFunding,
            i.allocationCount,
            i.publishedAt,
            i.isPublished,
            i.viewCount
        FROM ImpactStory i
        WHERE i.organizationId = ?
        ORDER BY i.publishedAt DESC
    ");
    
    $stmt->execute([$organizationId]);
    $stories = $stmt->fetchAll();
    
    // Calculate totals
    $totalFunding = 0;
    $publishedCount = 0;
    $draftCount = 0;
    
    foreach ($stories as $story) {
        $totalFunding += floatval($story['totalFunding']);
        if ($story['isPublished']) {
            $publishedCount++;
        } else {
            $draftCount++;
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($stories),
        'stats' => [
            'totalFunding' => $totalFunding,
            'publishedCount' => $publishedCount,
            'draftCount' => $draftCount
        ],
        'data' => $stories
    ]);
    
} catch (PDOException $e) {
    error_log("Get Org Impacts Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve impact stories.'
    ]);
} catch (Exception $e) {
    error_log("Get Org Impacts Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
