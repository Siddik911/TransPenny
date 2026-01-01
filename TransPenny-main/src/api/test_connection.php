<?php
/**
 * Database Connection Test
 * 
 * Simple script to verify database connectivity
 */

header('Content-Type: application/json');

require_once '../config/database.php';

try {
    $database = new Database();
    
    // Test PDO connection
    $pdo = $database->getConnection();
    if ($pdo) {
        $pdoStatus = "✓ PDO connection successful";
    } else {
        $pdoStatus = "✗ PDO connection failed";
    }
    
    // Test mysqli connection
    $mysqli = $database->getMysqliConnection();
    if ($mysqli) {
        $mysqliStatus = "✓ MySQLi connection successful";
        $mysqli->close();
    } else {
        $mysqliStatus = "✗ MySQLi connection failed";
    }
    
    // Get environment variables
    $env = [
        'DB_HOST' => getenv('DB_HOST') ?: 'Not set',
        'DB_NAME' => getenv('DB_NAME') ?: 'Not set',
        'DB_USER' => getenv('DB_USER') ?: 'Not set',
        'DB_PASSWORD' => getenv('DB_PASSWORD') ? '***hidden***' : 'Not set'
    ];
    
    echo json_encode([
        'success' => true,
        'pdo_status' => $pdoStatus,
        'mysqli_status' => $mysqliStatus,
        'environment' => $env,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Connection test failed',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
