<?php
/**
 * API Endpoint: Get Users
 * 
 * Returns all users from the database as JSON
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');

require_once '../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
    exit;
}

try {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Prepare and execute select query
    $query = "SELECT id, name, email, created_at FROM users ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Users retrieved successfully',
        'data' => $users,
        'count' => count($users)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve users',
        'error' => $e->getMessage()
    ]);
    
    error_log("Get users error: " . $e->getMessage());
}
