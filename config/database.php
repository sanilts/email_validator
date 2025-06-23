<?php
// config/database.php - Minimal database configuration
class Database {
    // UPDATE THESE WITH YOUR ACTUAL DATABASE CREDENTIALS
    private $host = 'localhost';
    private $db_name = 'saemail_validator';  // Your database name
    private $username = 'root';            // Your MySQL username  
    private $password = '';                // Your MySQL password
    private $conn;

    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }
        
        try {
            // Try to connect to the database
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            return $this->conn;
        } catch (PDOException $e) {
            // If database doesn't exist, try to create it
            try {
                $dsn = "mysql:host={$this->host};charset=utf8mb4";
                $conn = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $conn->exec("CREATE DATABASE IF NOT EXISTS `{$this->db_name}`");
                $conn->exec("USE `{$this->db_name}`");
                $this->conn = $conn;
                return $this->conn;
            } catch (PDOException $e2) {
                throw new Exception("Database connection failed: " . $e2->getMessage());
            }
        }
    }
}
?>