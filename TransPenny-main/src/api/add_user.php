<?php
/**
 * API Endpoint: Add User
 * 
 * Accepts JSON POST request with user data and inserts into database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

require_once '../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validate input
    if (!isset($data['name']) || !isset($data['email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: name and email'
        ]);
        exit;
    }
    
    $name = trim($data['name']);
    $email = trim($data['email']);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Name and email cannot be empty'
        ]);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Prepare and execute insert query
    $query = "INSERT INTO users (name, email) VALUES (:name, :email)";
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    
    if ($stmt->execute()) {
        $userId = $db->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'User added successfully',
            'data' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email
            ]
        ]);
    } else {
        throw new Exception('Failed to insert user');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    
    // Check for duplicate email error
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => false,
            'message' => 'Email already exists'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred'
        ]);
    }
    
    error_log("Add user error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    error_log("Add user error: " . $e->getMessage());
}
