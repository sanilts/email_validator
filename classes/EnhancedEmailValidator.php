<?php

// =============================================================================
// ENHANCED EMAIL VALIDATOR (classes/EnhancedEmailValidator.php)
// =============================================================================

class EnhancedEmailValidator extends EmailValidator {
    private $security;
    private $disposableEmails;
    
    public function __construct($database, $userId) {
        parent::__construct($database, $userId);
        $this->security = new SecurityManager($database);
        $this->loadDisposableEmailList();
    }
    
    private function loadDisposableEmailList() {
        // Load from file or database - common disposable email domains
        $this->disposableEmails = [
            '10minutemail.com', '10minutemail.net', 'tempmail.org', 'guerrillamail.com',
            'mailinator.com', 'yopmail.com', 'temp-mail.org', 'throwaway.email'
        ];
    }
    
    public function validateEmailWithAdvancedChecks($email, $sendVerification = false) {
        // Check rate limit
        if (!$this->security->checkRateLimit($this->user_id, 'email_validation', 1000)) {
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
        
        // Basic validation
        $result = parent::validateEmail($email, $sendVerification);
        
        // Add advanced checks
        $result['disposable'] = $this->isDisposableEmail($email);
        $result['role_based'] = $this->isRoleBasedEmail($email);
        $result['catch_all'] = $this->isCatchAllDomain($email);
        $result['deliverable'] = $this->isDeliverable($email);
        $result['risk_score'] = $this->calculateRiskScore($result);
        
        // Update overall validity based on advanced checks
        if ($result['disposable'] || $result['risk_score'] > 70) {
            $result['is_valid'] = false;
        }
        
        return $result;
    }
    
    private function isDisposableEmail($email) {
        $domain = substr(strrchr($email, "@"), 1);
        return in_array(strtolower($domain), $this->disposableEmails);
    }
    
    private function isRoleBasedEmail($email) {
        $localPart = substr($email, 0, strpos($email, '@'));
        $roleKeywords = ['admin', 'info', 'support', 'sales', 'marketing', 'noreply', 'no-reply'];
        
        foreach ($roleKeywords as $keyword) {
            if (stripos($localPart, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isCatchAllDomain($email) {
        $domain = substr(strrchr($email, "@"), 1);
        
        // Test with a random email on the same domain
        $randomEmail = 'test' . rand(10000, 99999) . '@' . $domain;
        $catchAllResult = $this->validateSMTP($randomEmail);
        
        return $catchAllResult; // If random email is valid, it's likely catch-all
    }
    
    private function isDeliverable($email) {
        // Combine all checks for final deliverability assessment
        $domain = substr(strrchr($email, "@"), 1);
        
        // Check if domain has valid MX record
        $mxRecords = [];
        if (!getmxrr($domain, $mxRecords)) {
            return false;
        }
        
        // Check if SMTP accepts the email
        return $this->validateSMTP($email);
    }
    
    private function calculateRiskScore($result) {
        $score = 0;
        
        if (!$result['format_valid']) $score += 40;
        if (!$result['domain_valid']) $score += 30;
        if (!$result['smtp_valid']) $score += 25;
        if ($result['disposable']) $score += 20;
        if ($result['role_based']) $score += 10;
        if ($result['catch_all']) $score += 15;
        
        return min($score, 100);
    }
}
