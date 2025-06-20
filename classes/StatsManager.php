<?php

// =============================================================================
// STATISTICS MANAGER (classes/StatsManager.php)
// =============================================================================

class StatsManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getUserStats($userId, $days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_validations,
                SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid_emails,
                SUM(CASE WHEN is_valid = 0 THEN 1 ELSE 0 END) as invalid_emails,
                COUNT(DISTINCT DATE(validation_date)) as active_days
            FROM email_validations 
            WHERE user_id = ? AND validation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$userId, $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getSystemStats($days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_validations,
                SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid_emails,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(DISTINCT DATE(validation_date)) as active_days
            FROM email_validations 
            WHERE validation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getValidationTrends($userId = null, $days = 30) {
        $whereClause = $userId ? 'WHERE user_id = ?' : '';
        $params = $userId ? [$userId, $days] : [$days];
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE(validation_date) as date,
                COUNT(*) as total,
                SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid
            FROM email_validations 
            $whereClause
            AND validation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(validation_date)
            ORDER BY date DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTopDomains($userId = null, $limit = 10) {
        $whereClause = $userId ? 'WHERE user_id = ?' : '';
        $params = $userId ? [$userId, $limit] : [$limit];
        
        $stmt = $this->db->prepare("
            SELECT 
                SUBSTRING_INDEX(email, '@', -1) as domain,
                COUNT(*) as count,
                SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid_count
            FROM email_validations 
            $whereClause
            GROUP BY domain
            ORDER BY count DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}