<?php

class SecurityManager {
    private $db;
    private $max_login_attempts = 5;
    private $lockout_time = 900; // 15 minutes
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function check_login_attempts($identifier) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as attempts 
                FROM activity_logs 
                WHERE action = 'login_failed' 
                AND details LIKE ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute(["%$identifier%", $this->lockout_time]);
            $result = $stmt->fetch();
            
            return $result['attempts'] < $this->max_login_attempts;
        } catch (Exception $e) {
            return true; // Allow login if check fails
        }
    }
    
    public function log_security_event($event_type, $details, $ip_address = null) {
        try {
            $ip_address = $ip_address ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (action, details, ip_address, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$event_type, $details, $ip_address]);
        } catch (Exception $e) {
            error_log("Security logging failed: " . $e->getMessage());
        }
    }
    
    public function detect_suspicious_activity($user_id) {
        try {
            // Check for rapid successive actions
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as rapid_actions 
                FROM activity_logs 
                WHERE user_id = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ");
            $stmt->execute([$user_id]);
            $rapid_actions = $stmt->fetch()['rapid_actions'];
            
            if ($rapid_actions > 100) {
                $this->log_security_event('suspicious_activity_detected', "User ID: $user_id, Actions: $rapid_actions in 60s");
                return true;
            }
            
            // Check for multiple IP addresses
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ip_address) as unique_ips 
                FROM activity_logs 
                WHERE user_id = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$user_id]);
            $unique_ips = $stmt->fetch()['unique_ips'];
            
            if ($unique_ips > 3) {
                $this->log_security_event('multiple_ip_activity', "User ID: $user_id, IPs: $unique_ips in 1h");
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function block_ip($ip_address, $reason = 'Automated security block') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO blocked_ips (ip_address, reason, blocked_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE reason = ?, blocked_at = NOW()
            ");
            $stmt->execute([$ip_address, $reason, $reason]);
            
            $this->log_security_event('ip_blocked', "IP: $ip_address, Reason: $reason");
        } catch (Exception $e) {
            error_log("IP blocking failed: " . $e->getMessage());
        }
    }
    
    public function is_ip_blocked($ip_address) {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM blocked_ips 
                WHERE ip_address = ? 
                AND blocked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$ip_address]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function sanitize_file_upload($file) {
        $allowed_types = ['text/csv', 'text/plain', 'application/csv'];
        $allowed_extensions = ['csv', 'txt'];
        
        // Check file type
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type');
        }
        
        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {
            throw new Exception('Invalid file extension');
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File too large');
        }
        
        // Sanitize filename
        $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $safe_name = trim($safe_name, '.');
        
        return [
            'original_name' => $file['name'],
            'safe_name' => $safe_name,
            'tmp_name' => $file['tmp_name'],
            'size' => $file['size'],
            'type' => $file['type']
        ];
    }
    
    public function validate_csrf_token($token) {
        return verify_csrf_token($token);
    }
    
    public function check_session_security() {
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        // Check for session hijacking
        if (isset($_SESSION['user_agent']) && 
            $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            session_unset();
            session_destroy();
            return false;
        }
        
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        return true;
    }
}

// Add to schema.sql for IP blocking
/*
CREATE TABLE blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE NOT NULL,
    reason VARCHAR(255),
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_address (ip_address),
    INDEX idx_blocked_at (blocked_at)
);
*/
?>