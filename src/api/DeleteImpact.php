<?php
/**
 * Delete Impact Story - Organizations can delete their own impact stories
 * Only the organization that created the story can delete it
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$storyId = $input['storyId'] ?? null;
$organizationId = $input['organizationId'] ?? null;

// Validation
if (empty($storyId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Story ID is required']);
    exit();
}

if (empty($organizationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Organization ID is required']);
    exit();
}

try {
    $db = getDBConnection();
    $db->beginTransaction();
    
    // Verify the impact story exists and belongs to this organization
    $stmt = $db->prepare("
        SELECT storyId, organizationId, title, totalFunding
        FROM ImpactStory 
        WHERE storyId = ?
        FOR UPDATE
    ");
    $stmt->execute([$storyId]);
    $story = $stmt->fetch();
    
    if (!$story) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Impact story not found']);
        exit();
    }
    
    // Check ownership
    if ($story['organizationId'] !== $organizationId) {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete your own impact stories']);
        exit();
    }
    
    // Check for related DonationAllocations that reference this story
    $stmt = $db->prepare("
        SELECT COUNT(*) as allocationCount 
        FROM DonationAllocation 
        WHERE impactStoryId = ?
    ");
    $stmt->execute([$storyId]);
    $allocations = $stmt->fetch();
    
    // If there are allocations, update them to remove the reference (SET NULL behavior)
    if ($allocations['allocationCount'] > 0) {
        $stmt = $db->prepare("
            UPDATE DonationAllocation 
            SET impactStoryId = NULL 
            WHERE impactStoryId = ?
        ");
        $stmt->execute([$storyId]);
    }
    
    // Delete the impact story
    $stmt = $db->prepare("DELETE FROM ImpactStory WHERE storyId = ?");
    $stmt->execute([$storyId]);
    
    $db->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Impact story deleted successfully',
        'data' => [
            'storyId' => $storyId,
            'title' => $story['title']
        ]
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Delete Impact Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete impact story.'
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Delete Impact Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
