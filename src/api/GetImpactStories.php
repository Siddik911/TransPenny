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

try {
    $db = getDBConnection();
    
    // Get published impact stories with organization info
    $stmt = $db->prepare("
        SELECT 
            i.storyId,
            i.title,
            i.content,
            i.mediaUrl,
            i.mediaType,
            i.totalFunding,
            i.allocationCount,
            i.publishedAt,
            i.viewCount,
            u.name as organizationName
        FROM ImpactStory i
        INNER JOIN Organization o ON i.organizationId = o.organizationId
        INNER JOIN User u ON o.userId = u.userId
        WHERE i.isPublished = TRUE
        ORDER BY i.publishedAt DESC
        LIMIT 20
    ");
    $stmt->execute();
    $stories = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $stories,
        'count' => count($stories)
    ]);
    
} catch (PDOException $e) {
    error_log("Get Impact Stories Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve impact stories'
    ]);
}
?>
