<?php
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

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Get user by email
    $stmt = $db->prepare("SELECT * FROM User WHERE email = ? AND isActive = TRUE");
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit();
    }
    
    // Verify password
    if (!password_verify($input['password'], $user['passwordHash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit();
    }
    
    // Update last login time
    $stmt = $db->prepare("UPDATE User SET lastLoginAt = NOW() WHERE userId = ?");
    $stmt->execute([$user['userId']]);
    
    // Get additional info based on user type
    $additionalInfo = null;
    
    if ($user['userType'] === 'donor') {
        $stmt = $db->prepare("SELECT * FROM Donor WHERE userId = ?");
        $stmt->execute([$user['userId']]);
        $additionalInfo = $stmt->fetch();
    } elseif ($user['userType'] === 'organization') {
        $stmt = $db->prepare("SELECT * FROM Organization WHERE userId = ?");
        $stmt->execute([$user['userId']]);
        $additionalInfo = $stmt->fetch();
    } elseif ($user['userType'] === 'admin') {
        $stmt = $db->prepare("SELECT * FROM Admin WHERE userId = ?");
        $stmt->execute([$user['userId']]);
        $additionalInfo = $stmt->fetch();
    }
    
    // Prepare response
    $responseData = [
        'userId' => $user['userId'],
        'email' => $user['email'],
        'name' => $user['name'],
        'userType' => $user['userType'],
        'phoneNumber' => $user['phoneNumber'],
        'isActive' => (bool)$user['isActive'],
        'createdAt' => $user['createdAt'],
        'lastLoginAt' => $user['lastLoginAt']
    ];
    
    // Add type-specific info
    if ($user['userType'] === 'donor' && $additionalInfo) {
        $responseData['donorInfo'] = [
            'donorId' => $additionalInfo['donorId'],
            'isAnonymous' => (bool)$additionalInfo['isAnonymous'],
            'totalDonated' => $additionalInfo['totalDonated'],
            'donationCount' => $additionalInfo['donationCount'],
            'lastDonationAt' => $additionalInfo['lastDonationAt']
        ];
    } elseif ($user['userType'] === 'organization' && $additionalInfo) {
        $responseData['orgInfo'] = [
            'organizationId' => $additionalInfo['organizationId'],
            'verificationStatus' => $additionalInfo['verificationStatus'],
            'registrationNumber' => $additionalInfo['registrationNumber'],
            'address' => $additionalInfo['address'],
            'website' => $additionalInfo['website'],
            'description' => $additionalInfo['description'],
            'totalReceived' => $additionalInfo['totalReceived'],
            'totalSpent' => $additionalInfo['totalSpent'],
            'availableBalance' => $additionalInfo['availableBalance']
        ];
    } elseif ($user['userType'] === 'admin' && $additionalInfo) {
        $responseData['adminInfo'] = [
            'adminId' => $additionalInfo['adminId'],
            'role' => $additionalInfo['role']
        ];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $responseData
    ]);
    
} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed. Please try again later.']);
} catch (Exception $e) {
    error_log("Login Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}
?>
