<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['email']) || !isset($data['password']) || !isset($data['name']) || !isset($data['userType'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate userType
if (!in_array($data['userType'], ['donor', 'organization', 'admin'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user type']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT userId FROM User WHERE email = ?");
    $stmt->execute([$data['email']]);
    
    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Insert user
    $stmt = $conn->prepare(
        "INSERT INTO User (email, passwordHash, name, userType, phoneNumber, createdAt) 
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    
    $phoneNumber = isset($data['phoneNumber']) ? $data['phoneNumber'] : null;
    $stmt->execute([
        $data['email'],
        $hashedPassword,
        $data['name'],
        $data['userType'],
        $phoneNumber
    ]);
    
    // Get the userId that was just inserted (since it's a UUID, we query for it)
    $getUserStmt = $conn->prepare("SELECT userId FROM User WHERE email = ?");
    $getUserStmt->execute([$data['email']]);
    $user = $getUserStmt->fetch(PDO::FETCH_ASSOC);
    $userId = $user['userId'];
    
    // Create role-specific record if needed
    if ($data['userType'] === 'donor') {
        $donorStmt = $conn->prepare(
            "INSERT INTO Donor (userId, isAnonymous, totalDonated, donationCount) 
             VALUES (?, ?, 0, 0)"
        );
        $isAnonymous = isset($data['isAnonymous']) ? $data['isAnonymous'] : false;
        $donorStmt->execute([$userId, $isAnonymous ? 1 : 0]);
    } elseif ($data['userType'] === 'organization') {
        $orgStmt = $conn->prepare(
            "INSERT INTO Organization (userId, description, registeredAt) 
             VALUES (?, ?, NOW())"
        );
        $description = isset($data['description']) ? $data['description'] : '';
        $orgStmt->execute([$userId, $description]);
    }
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'userId' => $userId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
?>
