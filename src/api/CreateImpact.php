<?php
/**
 * Create Impact Story - Organizations can create impact stories
 * Tracks how donations are being used
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$organizationId = $input['organizationId'] ?? null;
$title = trim($input['title'] ?? '');
$content = trim($input['content'] ?? '');
$totalFunding = isset($input['totalFunding']) ? floatval($input['totalFunding']) : 0;
$geoTag = isset($input['geoTag']) ? trim($input['geoTag']) : null;
$mediaUrl = isset($input['mediaUrl']) ? trim($input['mediaUrl']) : null;
$mediaType = $input['mediaType'] ?? 'image';
$isPublished = isset($input['isPublished']) ? (bool)$input['isPublished'] : false;

// Validation
if (empty($organizationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Organization ID is required']);
    exit();
}

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit();
}

if (strlen($title) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title must be less than 500 characters']);
    exit();
}

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Content is required']);
    exit();
}

if ($totalFunding < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Total funding cannot be negative']);
    exit();
}

// Validate media type
$validMediaTypes = ['image', 'video', 'document'];
if (!in_array($mediaType, $validMediaTypes)) {
    $mediaType = 'image';
}

// Validate media URL if provided
if (!empty($mediaUrl) && !filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid media URL']);
    exit();
}

try {
    $db = getDBConnection();
    $db->beginTransaction();
    
    // Verify organization exists
    $stmt = $db->prepare("SELECT organizationId, verificationStatus FROM Organization WHERE organizationId = ?");
    $stmt->execute([$organizationId]);
    $org = $stmt->fetch();
    
    if (!$org) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit();
    }
    
    // Generate UUID for story
    $stmt = $db->query("SELECT UUID() as uuid");
    $uuid = $stmt->fetch()['uuid'];
    
    // Create impact story
    $stmt = $db->prepare("
        INSERT INTO ImpactStory (
            storyId,
            organizationId,
            title,
            content,
            mediaUrl,
            mediaType,
            geoTag,
            totalFunding,
            allocationCount,
            publishedAt,
            isPublished,
            viewCount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?, 0)
    ");
    $stmt->execute([
        $uuid,
        $organizationId,
        $title,
        $content,
        $mediaUrl,
        $mediaType,
        $geoTag,
        $totalFunding,
        $isPublished ? 1 : 0
    ]);
    
    $db->commit();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Impact story created successfully',
        'data' => [
            'storyId' => $uuid,
            'title' => $title,
            'content' => $content,
            'mediaUrl' => $mediaUrl,
            'mediaType' => $mediaType,
            'geoTag' => $geoTag,
            'totalFunding' => $totalFunding,
            'isPublished' => $isPublished
        ]
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Create Impact Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create impact story.'
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Create Impact Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
