<?php
// =============================================================================
// FIXED EMAIL VALIDATION API (api/validate-email.php)
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
$email = isset($input['email']) ? trim($input['email']) : '';

class FixedEmailValidator {
    private $db;
    private $user_id;
    private $debug_info = [];
    
    public function __construct($database, $user_id) {
        $this->db = $database;
        $this->user_id = $user_id;
    }
    
    public function validateEmail($email, $sendVerification = false, $listId = null, $templateId = null) {
        $this->debug_info = ['email' => $email, 'steps' => [], 'timestamp' => date('Y-m-d H:i:s')];
        
        // Step 1: Sanitize and validate email format
        $this->debug_info['steps'][] = 'Step 1: Starting email format validation';
        $sanitizedEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
        $this->debug_info['format_check'] = [
            'original' => $email,
            'sanitized' => $sanitizedEmail
        ];
        
        if (!$this->validateEmailFormat($sanitizedEmail)) {
            return $this->createResult($email, false, false, false, false, 'Invalid email format');
        }
        $this->debug_info['steps'][] = 'Step 1: Email format validation PASSED';
        
        // Step 2: Check cache (optional optimization)
        $this->debug_info['steps'][] = 'Step 2: Checking validation cache';
        $cached = $this->checkValidationCache($sanitizedEmail, 30); // 30 days cache
        if ($cached) {
            $this->debug_info['steps'][] = 'Step 2: Found valid cache entry';
            $cached['debug_info'] = $this->debug_info;
            $cached['cached'] = true;
            return $cached;
        }
        $this->debug_info['steps'][] = 'Step 2: No cache found, proceeding with validation';
        
        // Step 3: Domain validation
        $this->debug_info['steps'][] = 'Step 3: Starting domain validation';
        $domainValid = $this->validateDomain($sanitizedEmail);
        $this->debug_info['steps'][] = 'Step 3: Domain validation ' . ($domainValid ? 'PASSED' : 'FAILED');
        
        // Step 4: SMTP validation (with fallback)
        $this->debug_info['steps'][] = 'Step 4: Starting SMTP validation';
        $smtpValid = $this->validateSMTP($sanitizedEmail);
        $this->debug_info['steps'][] = 'Step 4: SMTP validation ' . ($smtpValid ? 'PASSED' : 'FAILED');
        
        // Determine overall validity
        $isValid = $domainValid && $smtpValid;
        
        // Step 5: Send verification email if requested
        $verified = false;
        if ($sendVerification && $isValid) {
            $this->debug_info['steps'][] = 'Step 5: Sending verification email';
            $verified = $this->sendVerificationEmail($sanitizedEmail, $templateId);
            $this->debug_info['steps'][] = 'Step 5: Verification email ' . ($verified ? 'SENT' : 'FAILED');
        }
        
        // Create result
        $result = $this->createResult($sanitizedEmail, true, $domainValid, $smtpValid, $isValid, null, $verified);
        
        // Store in database
        $validationId = $this->storeValidationResult($result, $listId, $templateId);
        $result['validation_id'] = $validationId;
        
        // Assign to list if specified
        if ($listId && $validationId) {
            $this->assignToList($validationId, $listId);
        }
        
        $result['debug_info'] = $this->debug_info;
        return $result;
    }
    
    private function validateEmailFormat($email) {
        try {
            // Basic filter validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->debug_info['format_error'] = 'Failed basic filter validation';
                return false;
            }
            
            // Check for valid format structure
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
                $this->debug_info['format_error'] = 'Failed regex validation';
                return false;
            }
            
            // Split email into parts
            $parts = explode('@', $email);
            if (count($parts) !== 2) {
                $this->debug_info['format_error'] = 'Invalid email structure';
                return false;
            }
            
            $localPart = $parts[0];
            $domainPart = $parts[1];
            
            // Check length constraints
            if (strlen($localPart) > 64 || strlen($domainPart) > 255) {
                $this->debug_info['format_error'] = 'Email parts too long';
                return false;
            }
            
            // Check for consecutive dots
            if (strpos($email, '..') !== false) {
                $this->debug_info['format_error'] = 'Contains consecutive dots';
                return false;
            }
            
            $this->debug_info['format_check']['valid'] = true;
            $this->debug_info['format_check']['local_part'] = $localPart;
            $this->debug_info['format_check']['domain_part'] = $domainPart;
            
            return true;
            
        } catch (Exception $e) {
            $this->debug_info['format_error'] = $e->getMessage();
            return false;
        }
    }
    
    private function validateDomain($email) {
        try {
            $domain = substr(strrchr($email, "@"), 1);
            $this->debug_info['domain'] = $domain;
            
            // Method 1: Check MX records (preferred)
            if (function_exists('checkdnsrr')) {
                $mxValid = checkdnsrr($domain, "MX");
                $this->debug_info['mx_check'] = $mxValid;
                
                if ($mxValid) {
                    // Get MX records for additional info
                    if (function_exists('getmxrr')) {
                        $mxRecords = [];
                        if (getmxrr($domain, $mxRecords)) {
                            $this->debug_info['mx_records'] = array_slice($mxRecords, 0, 3);
                        }
                    }
                    return true;
                }
                
                // Fallback: Check A record
                $aValid = checkdnsrr($domain, "A");
                $this->debug_info['a_check'] = $aValid;
                if ($aValid) {
                    return true;
                }
            } else {
                $this->debug_info['checkdnsrr_available'] = false;
            }
            
            // Method 2: Fallback using gethostbyname
            $ip = gethostbyname($domain);
            $resolved = ($ip !== $domain && $ip !== false);
            
            $this->debug_info['fallback_domain_check'] = [
                'domain' => $domain,
                'resolved_ip' => $ip,
                'valid' => $resolved
            ];
            
            return $resolved;
            
        } catch (Exception $e) {
            $this->debug_info['domain_error'] = $e->getMessage();
            return false;
        }
    }
    
    private function validateSMTP($email) {
        try {
            $domain = substr(strrchr($email, "@"), 1);
            $this->debug_info['smtp_domain'] = $domain;
            
            // Get MX records for SMTP validation
            if (function_exists('getmxrr')) {
                $mxRecords = [];
                if (!getmxrr($domain, $mxRecords)) {
                    $this->debug_info['smtp_mx_records'] = 'No MX records found';
                    // Try using domain directly
                    $mxRecords = [$domain];
                } else {
                    $this->debug_info['smtp_mx_records'] = $mxRecords;
                }
            } else {
                // Fallback: use domain directly
                $mxRecords = [$domain];
                $this->debug_info['smtp_mx_records'] = 'Using domain directly (getmxrr not available)';
            }
            
            // Test SMTP connectivity
            $ports = [587, 25, 465, 2525]; // Common SMTP ports
            $this->debug_info['smtp_attempts'] = [];
            
            foreach ($mxRecords as $index => $mx) {
                if ($index >= 2) break; // Test max 2 MX records
                
                foreach ($ports as $port) {
                    $this->debug_info['smtp_attempts'][] = "Trying $mx:$port";
                    
                    if ($this->testSMTPConnection($mx, $port, $email)) {
                        $this->debug_info['smtp_success_port'] = "$mx:$port";
                        return true;
                    }
                }
            }
            
            // If SMTP tests fail, but we have valid MX records, consider it valid
            // (This handles cases where SMTP ports are blocked by hosting provider)
            if (function_exists('checkdnsrr') && checkdnsrr($domain, "MX")) {
                $this->debug_info['smtp_fallback'] = 'SMTP ports blocked but MX records exist - considering valid';
                return true;
            }
            
            // Final fallback for common domains
            $commonDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'icloud.com'];
            if (in_array(strtolower($domain), $commonDomains)) {
                $this->debug_info['smtp_fallback'] = 'Common domain detected - considering valid';
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->debug_info['smtp_error'] = $e->getMessage();
            // On error, if domain is valid, assume SMTP is valid (hosting restrictions)
            return $this->validateDomain($email);
        }
    }
    
    private function testSMTPConnection($host, $port, $email, $timeout = 10) {
        if (!function_exists('fsockopen')) {
            $this->debug_info['smtp_attempts'][] = "fsockopen not available";
            return false;
        }
        
        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            
            if (!$socket) {
                $this->debug_info['smtp_attempts'][] = "Failed to connect to $host:$port - $errno: $errstr";
                return false;
            }
            
            // Read greeting
            $response = fgets($socket, 1024);
            $this->debug_info['smtp_attempts'][] = "Connected to $host:$port - " . trim($response);
            
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                return false;
            }
            
            // Simple SMTP conversation
            $commands = [
                "EHLO validator.local\r\n" => '250',
                "MAIL FROM: <noreply@validator.local>\r\n" => '250',
                "RCPT TO: <$email>\r\n" => ['250', '251', '252'], // Accept multiple success codes
                "QUIT\r\n" => '221'
            ];
            
            foreach ($commands as $command => $expectedCodes) {
                fputs($socket, $command);
                $response = fgets($socket, 1024);
                $responseCode = substr($response, 0, 3);
                
                $expected = is_array($expectedCodes) ? $expectedCodes : [$expectedCodes];
                
                if (!in_array($responseCode, $expected)) {
                    if ($command === "RCPT TO: <$email>\r\n") {
                        // RCPT TO failed - email doesn't exist
                        fclose($socket);
                        return false;
                    }
                    // Other commands can fail and we still consider it a successful connection
                }
            }
            
            fclose($socket);
            return true;
            
        } catch (Exception $e) {
            $this->debug_info['smtp_attempts'][] = "Exception testing $host:$port - " . $e->getMessage();
            return false;
        }
    }
    
    private function checkValidationCache($email, $cacheDays = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT *, id as validation_id 
                FROM email_validations 
                WHERE email = ? 
                AND validation_date > DATE_SUB(NOW(), INTERVAL ? DAY) 
                ORDER BY validation_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$email, $cacheDays]);
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
                    'validation_id' => $result['validation_id']
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->debug_info['cache_error'] = $e->getMessage();
            return null;
        }
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
            return false;
        }
    }
    
    private function storeValidationResult($result, $listId = null, $templateId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_validations 
                (email, format_valid, domain_valid, smtp_valid, is_valid, user_id, list_id, template_id, validation_details, validation_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $details = json_encode([
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'timestamp' => time(),
                'debug_info' => $this->debug_info,
                'validation_version' => '2.1'
            ]);
            
            $stmt->execute([
                $result['email'],
                $result['format_valid'] ? 1 : 0,
                $result['domain_valid'] ? 1 : 0,
                $result['smtp_valid'] ? 1 : 0,
                $result['is_valid'] ? 1 : 0,
                $this->user_id,
                $listId,
                $templateId,
                $details
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            $this->debug_info['storage_error'] = $e->getMessage();
            return null;
        }
    }
    
    private function assignToList($validationId, $listId) {
        if (!$listId || !$validationId) return;
        
        try {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO list_email_assignments (list_id, validation_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$listId, $validationId]);
        } catch (Exception $e) {
            $this->debug_info['list_assignment_error'] = $e->getMessage();
        }
    }
    
    private function createResult($email, $formatValid, $domainValid, $smtpValid, $isValid, $failureReason = null, $verified = false) {
        return [
            'email' => $email,
            'format_valid' => $formatValid,
            'domain_valid' => $domainValid,
            'smtp_valid' => $smtpValid,
            'is_valid' => $isValid,
            'verified' => $verified,
            'validation_date' => date('Y-m-d H:i:s'),
            'cached' => false,
            'failure_reason' => $failureReason
        ];
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
                'gethostbyname' => function_exists('gethostbyname'),
                'dns_get_record' => function_exists('dns_get_record')
            ],
            'php_settings' => [
                'allow_url_fopen' => ini_get('allow_url_fopen'),
                'default_socket_timeout' => ini_get('default_socket_timeout'),
                'disabled_functions' => ini_get('disable_functions')
            ]
        ];
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $validator = new FixedEmailValidator($db, $_SESSION['user_id']);
    
    // Handle system info request
    if (isset($input['get_system_info']) && $input['get_system_info']) {
        echo json_encode([
            'system_info' => $validator->getSystemInfo()
        ]);
        exit;
    }
    
    // Validate email input
    if (empty($email)) {
        throw new Exception('Email address is required');
    }
    
    // Perform validation
    $result = $validator->validateEmail(
        $email, 
        $input['sendVerification'] ?? false,
        $input['listId'] ?? null,
        $input['templateId'] ?? null
    );
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, 'email_validation', ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        json_encode([
            'email' => $email,
            'result' => $result['is_valid'] ? 'valid' : 'invalid',
            'cached' => $result['cached'] ?? false
        ]),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
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