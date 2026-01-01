<?php
/**
 * Database Configuration and Connection
 * 
 * This file establishes a secure database connection using environment variables
 * and provides a PDO instance for database operations.
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'db';
        $this->db_name = getenv('DB_NAME') ?: 'transpenny_db';
        $this->username = getenv('DB_USER') ?: 'transpenny_user';
        $this->password = getenv('DB_PASSWORD') ?: 'transpenny_pass_2025';
    }

    /**
     * Get database connection using PDO
     * 
     * @return PDO|null Database connection or null on failure
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
            return null;
        }

        return $this->conn;
    }

    /**
     * Alternative: Get database connection using mysqli
     * 
     * @return mysqli|null Database connection or null on failure
     */
    public function getMysqliConnection() {
        try {
            $conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8mb4");
            return $conn;
        } catch(Exception $e) {
            error_log("Connection error: " . $e->getMessage());
            return null;
        }
    }
}
