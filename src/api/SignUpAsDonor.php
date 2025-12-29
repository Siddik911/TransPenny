<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$requiredFields = ['email', 'password', 'name', 'phoneNumber'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
        exit();
    }
}

// Validate email format
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate password strength (minimum 8 characters)
if (strlen($input['password']) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT userId FROM User WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit();
    }
    
    // Generate UUIDs
    $userId = generateUUID();
    $donorId = generateUUID();
    
    // Hash password
    $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT);
    
    // Insert into User table
    $stmt = $db->prepare("
        INSERT INTO User (userId, email, passwordHash, name, userType, phoneNumber, createdAt, isActive)
        VALUES (?, ?, ?, ?, 'donor', ?, NOW(), TRUE)
    ");
    $stmt->execute([
        $userId,
        $input['email'],
        $passwordHash,
        $input['name'],
        $input['phoneNumber']
    ]);
    
    // Insert into Donor table
    $isAnonymous = isset($input['isAnonymous']) && $input['isAnonymous'] === true ? 1 : 0;
    $stmt = $db->prepare("
        INSERT INTO Donor (donorId, userId, isAnonymous, totalDonated, donationCount)
        VALUES (?, ?, ?, 0.00, 0)
    ");
    $stmt->execute([$donorId, $userId, $isAnonymous]);
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Donor registered successfully',
        'data' => [
            'userId' => $userId,
            'donorId' => $donorId,
            'email' => $input['email'],
            'name' => $input['name']
        ]
    ]);
    
} catch (PDOException $e) {
    // Rollback on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Donor Registration Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Donor Registration Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>