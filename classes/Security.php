<?php
// =============================================================================
// SECURITY & RATE LIMITING (classes/Security.php)
// =============================================================================

class SecurityManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function checkRateLimit($userId, $action, $limit = 100, $window = 3600) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM activity_logs 
            WHERE user_id = ? AND action = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$userId, $action, $window]);
        $count = $stmt->fetchColumn();
        
        return $count < $limit;
    }
    
    public function logFailedAttempt($userId, $action, $details = null) {
        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            'failed_' . $action,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    }
    
    public function isIpBlocked($ip) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as failed_attempts
            FROM activity_logs 
            WHERE ip_address = ? 
            AND action LIKE 'failed_%' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip]);
        $failedAttempts = $stmt->fetchColumn();
        
        return $failedAttempts > 10; // Block after 10 failed attempts in 1 hour
    }
    
    public function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
}