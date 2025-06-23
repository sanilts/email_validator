<?php
// ===================================================================
// INCLUDES/FUNCTIONS.PHP - Additional Helper Functions
// ===================================================================
?>
<?php
// Rate limiting functions
function get_user_rate_limit($user_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM activity_logs 
            WHERE user_id = ? 
            AND action IN ('email_validation', 'bulk_upload') 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        return [
            'current_usage' => $result['count'],
            'limit' => get_system_setting('rate_limit_per_hour', 1000),
            'remaining' => max(0, get_system_setting('rate_limit_per_hour', 1000) - $result['count'])
        ];
    } catch (Exception $e) {
        return ['current_usage' => 0, 'limit' => 1000, 'remaining' => 1000];
    }
}

// Email validation helpers
function extract_domain($email) {
    return substr(strrchr($email, "@"), 1);
}

function is_disposable_domain($domain) {
    $disposable_domains = [
        '10minutemail.com', 'tempmail.org', 'guerrillamail.com', 
        'mailinator.com', 'yopmail.com', 'temp-mail.org',
        'throwaway.email', 'getnada.com', 'fakemailgenerator.com',
        'sharklasers.com', 'mailnesia.com', 'maildrop.cc'
    ];
    return in_array(strtolower($domain), $disposable_domains);
}

function is_role_based_email($email) {
    $role_prefixes = [
        'admin', 'administrator', 'info', 'support', 'help',
        'sales', 'marketing', 'noreply', 'no-reply', 'contact',
        'service', 'team', 'office', 'mail', 'email', 'webmaster',
        'postmaster', 'abuse', 'security', 'privacy', 'legal'
    ];
    
    $local_part = substr($email, 0, strpos($email, '@'));
    return in_array(strtolower($local_part), $role_prefixes);
}

// File processing helpers
function parse_csv_file($filepath) {
    $emails = [];
    $handle = fopen($filepath, 'r');
    
    if ($handle) {
        while (($line = fgetcsv($handle)) !== false) {
            foreach ($line as $field) {
                $field = trim($field);
                if (validate_email_format($field)) {
                    $emails[] = strtolower($field);
                }
            }
        }
        fclose($handle);
    }
    
    return array_unique($emails);
}

function parse_txt_file($filepath) {
    $emails = [];
    $content = file_get_contents($filepath);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (validate_email_format($line)) {
            $emails[] = strtolower($line);
        }
    }
    
    return array_unique($emails);
}

// Template processing
function process_template($template, $variables = []) {
    $default_variables = [
        '{{date}}' => date('Y-m-d'),
        '{{time}}' => date('H:i:s'),
        '{{company_name}}' => get_system_setting('company_name', APP_NAME),
        '{{year}}' => date('Y')
    ];
    
    $variables = array_merge($default_variables, $variables);
    
    $processed = $template;
    foreach ($variables as $key => $value) {
        $processed = str_replace($key, $value, $processed);
    }
    
    return $processed;
}

// Security helpers
function generate_verification_code($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// System maintenance functions
function cleanup_expired_validations() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("DELETE FROM email_validations WHERE expires_at <= NOW()");
        $stmt->execute();
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Cleanup error: " . $e->getMessage());
        return 0;
    }
}

function cleanup_old_logs($days = 90) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Log cleanup error: " . $e->getMessage());
        return 0;
    }
}

// Statistics functions
function get_user_statistics($user_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stats = [];
        
        // Total validations
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM email_validations WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats['total_validations'] = $stmt->fetch()['total'];
        
        // Valid emails
        $stmt = $db->prepare("SELECT COUNT(*) as valid FROM email_validations WHERE user_id = ? AND status = 'valid'");
        $stmt->execute([$user_id]);
        $stats['valid_emails'] = $stmt->fetch()['valid'];
        
        // Invalid emails
        $stmt = $db->prepare("SELECT COUNT(*) as invalid FROM email_validations WHERE user_id = ? AND status = 'invalid'");
        $stmt->execute([$user_id]);
        $stats['invalid_emails'] = $stmt->fetch()['invalid'];
        
        // Risky emails
        $stmt = $db->prepare("SELECT COUNT(*) as risky FROM email_validations WHERE user_id = ? AND status = 'risky'");
        $stmt->execute([$user_id]);
        $stats['risky_emails'] = $stmt->fetch()['risky'];
        
        // Today's validations
        $stmt = $db->prepare("SELECT COUNT(*) as today FROM email_validations WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$user_id]);
        $stats['today_validations'] = $stmt->fetch()['today'];
        
        // Success rate
        $stats['success_rate'] = $stats['total_validations'] > 0 ? 
            round(($stats['valid_emails'] / $stats['total_validations']) * 100, 1) : 0;
        
        return $stats;
    } catch (Exception $e) {
        return [
            'total_validations' => 0,
            'valid_emails' => 0,
            'invalid_emails' => 0,
            'risky_emails' => 0,
            'today_validations' => 0,
            'success_rate' => 0
        ];
    }
}

function get_system_statistics() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stats = [];
        
        // Total users
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['total'];
        
        // Active users
        $stmt = $db->prepare("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
        $stmt->execute();
        $stats['active_users'] = $stmt->fetch()['active'];
        
        // Total validations
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM email_validations");
        $stmt->execute();
        $stats['total_validations'] = $stmt->fetch()['total'];
        
        // Today's validations
        $stmt = $db->prepare("SELECT COUNT(*) as today FROM email_validations WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['today_validations'] = $stmt->fetch()['today'];
        
        return $stats;
    } catch (Exception $e) {
        return [
            'total_users' => 0,
            'active_users' => 0,
            'total_validations' => 0,
            'today_validations' => 0
        ];
    }
}
?>