<?php
header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare(
        "SELECT userId, name, userType, passwordHash FROM User WHERE email = ? AND isActive = TRUE"
    );
    $stmt->execute([$data['email']]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($data['password'], $user['passwordHash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    // Update last login
    $updateStmt = $conn->prepare("UPDATE User SET lastLoginAt = NOW() WHERE userId = ?");
    $updateStmt->execute([$user['userId']]);
    
    // Set session
    $_SESSION['userId'] = $user['userId'];
    $_SESSION['userName'] = $user['name'];
    $_SESSION['userType'] = $user['userType'];
    $_SESSION['userEmail'] = $data['email'];
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'userId' => $user['userId'],
        'userName' => $user['name'],
        'userType' => $user['userType']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed: ' . $e->getMessage()]);
}
?>
