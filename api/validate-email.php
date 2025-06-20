<?php
// =============================================================================
// ENHANCED EMAIL VALIDATION API WITH DEBUGGING (api/validate-email.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);

class EmailValidator {
    private $db;
    private $user_id;
    private $debug_info = [];
    
    public function __construct($database, $user_id) {
        $this->db = $database;
        $this->user_id = $user_id;
    }
    
    public function validateEmail($email, $sendVerification = false, $listId = null, $templateId = null) {
        $this->debug_info = ['email' => $email, 'steps' => []];
        
        // Check cache first (within 3 months)
        $cached = $this->checkCache($email);
        if ($cached) {
            $this->debug_info['steps'][] = 'Found in cache';
            // If email is cached but we want to add to list, do that
            if ($listId) {
                $this->assignToList($cached['validation_id'], $listId);
            }
            $cached['debug_info'] = $this->debug_info;
            return $cached;
        }
        
        $result = [
            'email' => $email,
            'format_valid' => false,
            'domain_valid' => false,
            'smtp_valid' => false,
            'is_valid' => false,
            'verified' => false,
            'validation_date' => date('Y-m-d H:i:s'),
            'debug_info' => []
        ];
        
        // Step 1: Format validation
        $this->debug_info['steps'][] = 'Starting format validation';
        $result['format_valid'] = $this->validateFormat($email);
        $this->debug_info['steps'][] = 'Format validation result: ' . ($result['format_valid'] ? 'PASS' : 'FAIL');
        
        if ($result['format_valid']) {
            // Step 2: Domain validation
            $this->debug_info['steps'][] = 'Starting domain validation';
            $result['domain_valid'] = $this->validateDomain($email);
            $this->debug_info['steps'][] = 'Domain validation result: ' . ($result['domain_valid'] ? 'PASS' : 'FAIL');
            
            if ($result['domain_valid']) {
                // Step 3: SMTP validation
                $this->debug_info['steps'][] = 'Starting SMTP validation';
                $result['smtp_valid'] = $this->validateSMTP($email);
                $this->debug_info['steps'][] = 'SMTP validation result: ' . ($result['smtp_valid'] ? 'PASS' : 'FAIL');
                
                if ($sendVerification && $result['smtp_valid']) {
                    $this->debug_info['steps'][] = 'Attempting to send verification email';
                    $result['verified'] = $this->sendVerificationEmail($email, $templateId);
                    $this->debug_info['steps'][] = 'Verification email result: ' . ($result['verified'] ? 'SENT' : 'FAILED');
                }
            }
        }
        
        $result['is_valid'] = $result['format_valid'] && $result['domain_valid'] && $result['smtp_valid'];
        $result['debug_info'] = $this->debug_info;
        
        // Store result in database
        $validationId = $this->storeResult($result, $listId, $templateId);
        $result['validation_id'] = $validationId;
        
        // Assign to list if specified
        if ($listId) {
            $this->assignToList($validationId, $listId);
            $this->updateListStats($listId);
        }
        
        return $result;
    }
    
    private function validateFormat($email) {
        try {
            $isValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
            $this->debug_info['format_check'] = [
                'email' => $email,
                'valid' => $isValid,
                'sanitized' => filter_var($email, FILTER_SANITIZE_EMAIL)
            ];
            return $isValid;
        } catch (Exception $e) {
            $this->debug_info['format_error'] = $e->getMessage();
            return false;
        }
    }
    
    private function validateDomain($email) {
        try {
            $domain = substr(strrchr($email, "@"), 1);
            $this->debug_info['domain'] = $domain;
            
            // Check if required functions exist
            if (!function_exists('checkdnsrr')) {
                $this->debug_info['domain_error'] = 'checkdnsrr function not available';
                return $this->fallbackDomainCheck($domain);
            }
            
            // Try MX record first
            $mx_valid = checkdnsrr($domain, "MX");
            $this->debug_info['mx_check'] = $mx_valid;
            
            if ($mx_valid) {
                return true;
            }
            
            // Fallback to A record
            $a_valid = checkdnsrr($domain, "A");
            $this->debug_info['a_check'] = $a_valid;
            
            return $a_valid;
            
        } catch (Exception $e) {
            $this->debug_info['domain_error'] = $e->getMessage();
            return false;
        }
    }
    
    private function fallbackDomainCheck($domain) {
        // Fallback method using gethostbyname
        $ip = gethostbyname($domain);
        $valid = ($ip !== $domain); // gethostbyname returns the hostname if resolution fails
        $this->debug_info['fallback_domain_check'] = [
            'domain' => $domain,
            'resolved_ip' => $ip,
            'valid' => $valid
        ];
        return $valid;
    }
    
    private function validateSMTP($email) {
        try {
            $domain = substr(strrchr($email, "@"), 1);
            $this->debug_info['smtp_domain'] = $domain;
            
            // Check if required functions exist
            if (!function_exists('getmxrr') || !function_exists('fsockopen')) {
                $this->debug_info['smtp_error'] = 'Required functions not available (getmxrr/fsockopen)';
                return $this->fallbackSMTPCheck($domain);
            }
            
            $mxRecords = [];
            
            if (!getmxrr($domain, $mxRecords)) {
                $this->debug_info['mx_records'] = 'No MX records found';
                return false;
            }
            
            $this->debug_info['mx_records'] = $mxRecords;
            $mx = $mxRecords[0];
            
            // Try multiple ports (587, 25, 465) as many servers block port 25
            $ports = [587, 25, 465];
            $success = false;
            
            foreach ($ports as $port) {
                $this->debug_info['smtp_attempts'][] = "Trying port $port on $mx";
                
                $socket = @fsockopen($mx, $port, $errno, $errstr, 10);
                
                if (!$socket) {
                    $this->debug_info['smtp_attempts'][] = "Port $port failed: $errno - $errstr";
                    continue;
                }
                
                $response = fgets($socket);
                $this->debug_info['smtp_attempts'][] = "Port $port response: " . trim($response);
                
                if (substr($response, 0, 3) != '220') {
                    fclose($socket);
                    $this->debug_info['smtp_attempts'][] = "Port $port: Invalid greeting";
                    continue;
                }
                
                // SMTP conversation
                fputs($socket, "HELO example.com\r\n");
                $response = fgets($socket);
                $this->debug_info['smtp_attempts'][] = "HELO response: " . trim($response);
                
                fputs($socket, "MAIL FROM: <test@example.com>\r\n");
                $response = fgets($socket);
                $this->debug_info['smtp_attempts'][] = "MAIL FROM response: " . trim($response);
                
                fputs($socket, "RCPT TO: <$email>\r\n");
                $response = fgets($socket);
                $this->debug_info['smtp_attempts'][] = "RCPT TO response: " . trim($response);
                
                fputs($socket, "QUIT\r\n");
                fclose($socket);
                
                if (substr($response, 0, 3) == '250') {
                    $success = true;
                    $this->debug_info['smtp_success_port'] = $port;
                    break;
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->debug_info['smtp_error'] = $e->getMessage();
            return false;
        }
    }
    
    private function fallbackSMTPCheck($domain) {
        // Very basic fallback - just check if domain resolves
        $ip = gethostbyname($domain);
        $valid = ($ip !== $domain);
        $this->debug_info['fallback_smtp_check'] = [
            'domain' => $domain,
            'resolved' => $valid,
            'note' => 'Fallback method used due to missing functions'
        ];
        return $valid;
    }
    
    private function sendVerificationEmail($email, $templateId = null) {
        try {
            require_once '../classes/EmailSender.php';
            $emailSender = new EmailSender($this->db, $this->user_id);
            
            if ($templateId) {
                return $emailSender->sendVerificationEmailWithTemplate($email, $templateId);
            } else {
                return $emailSender->sendVerificationEmail($email);
            }
        } catch (Exception $e) {
            $this->debug_info['verification_error'] = $e->getMessage();
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function checkCache($email) {
        $stmt = $this->db->prepare("
            SELECT *, id as validation_id FROM email_validations 
            WHERE email = ? AND validation_date > DATE_SUB(NOW(), INTERVAL 90 DAY) 
            ORDER BY validation_date DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'email' => $result['email'],
                'format_valid' => (bool)$result['format_valid'],
                'domain_valid' => (bool)$result['domain_valid'],
                'smtp_valid' => (bool)$result['smtp_valid'],
                'is_valid' => (bool)$result['is_valid'],
                'verified' => true,
                'validation_date' => $result['validation_date'],
                'validation_id' => $result['validation_id'],
                'cached' => true
            ];
        }
        
        return null;
    }
    
    private function storeResult($result, $listId = null, $templateId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO email_validations 
            (email, format_valid, domain_valid, smtp_valid, is_valid, user_id, list_id, template_id, validation_details) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $details = json_encode([
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'timestamp' => time(),
            'debug_info' => $this->debug_info
        ]);
        
        $stmt->execute([
            $result['email'],
            $result['format_valid'],
            $result['domain_valid'],
            $result['smtp_valid'],
            $result['is_valid'],
            $this->user_id,
            $listId,
            $templateId,
            $details
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function assignToList($validationId, $listId) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO list_email_assignments (list_id, validation_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$listId, $validationId]);
    }
    
    private function updateListStats($listId) {
        $stmt = $this->db->prepare("
            UPDATE email_lists SET 
                total_emails = (
                    SELECT COUNT(*) FROM list_email_assignments 
                    WHERE list_id = ?
                ),
                valid_emails = (
                    SELECT COUNT(*) FROM list_email_assignments lea
                    JOIN email_validations ev ON lea.validation_id = ev.id
                    WHERE lea.list_id = ? AND ev.is_valid = 1
                ),
                invalid_emails = (
                    SELECT COUNT(*) FROM list_email_assignments lea
                    JOIN email_validations ev ON lea.validation_id = ev.id
                    WHERE lea.list_id = ? AND ev.is_valid = 0
                )
            WHERE id = ?
        ");
        $stmt->execute([$listId, $listId, $listId, $listId]);
    }
    
    public function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'extensions' => [
                'filter' => extension_loaded('filter'),
                'sockets' => extension_loaded('sockets'),
                'openssl' => extension_loaded('openssl'),
                'curl' => extension_loaded('curl')
            ],
            'functions' => [
                'checkdnsrr' => function_exists('checkdnsrr'),
                'getmxrr' => function_exists('getmxrr'),
                'fsockopen' => function_exists('fsockopen'),
                'gethostbyname' => function_exists('gethostbyname')
            ],
            'dns_get_record' => function_exists('dns_get_record'),
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'disabled_functions' => ini_get('disable_functions')
        ];
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $validator = new EmailValidator($db, $_SESSION['user_id']);
    
    // If requesting system info
    if (isset($input['get_system_info']) && $input['get_system_info']) {
        echo json_encode([
            'system_info' => $validator->getSystemInfo()
        ]);
        exit;
    }
    
    $result = $validator->validateEmail(
        $email, 
        $input['sendVerification'] ?? false,
        $input['listId'] ?? null,
        $input['templateId'] ?? null
    );
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Validation failed: ' . $e->getMessage(),
        'debug_info' => [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
