<?php

// =============================================================================
// CONFIGURATION MANAGER (classes/ConfigManager.php)
// =============================================================================

class ConfigManager {
    private $db;
    private $cache = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function get($key, $default = null) {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        
        $this->cache[$key] = $value !== false ? $value : $default;
        return $this->cache[$key];
    }
    
    public function set($key, $value, $description = null) {
        $stmt = $this->db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            description = COALESCE(VALUES(description), description)
        ");
        $stmt->execute([$key, $value, $description]);
        
        $this->cache[$key] = $value;
    }
    
    public function getAll() {
        $stmt = $this->db->prepare("SELECT * FROM system_settings ORDER BY setting_key");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getValidationCacheDays() {
        return (int)$this->get('validation_cache_days', 90);
    }
    
    public function getMaxBulkEmails() {
        return (int)$this->get('max_bulk_emails', 10000);
    }
    
    public function getVerificationTimeout() {
        return (int)$this->get('verification_timeout', 30);
    }
    
    public function getDailyValidationLimit() {
        return (int)$this->get('daily_validation_limit', 1000);
    }
}