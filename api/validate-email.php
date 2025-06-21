<?php
// =============================================================================
// ENHANCED EMAIL VALIDATION API (api/validate-email.php)
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

class EnhancedEmailValidator {
    private $db;
    private $user_id;
    private $debug_info = [];
    
    public function __construct($database, $user_id) {
        $this->db = $database;
        $this->user_id = $user_id;
    }
    
    public function validateEmail($email, $sendVerification = false, $listId = null, $templateId = null) {
        $this->debug_info = ['email' => $email, 'steps' => [], 'timestamp' => date('Y-m-d H:i:s')];
        
        // Step 1: Format Validation
        $this->debug_info['steps'][] = 'Step 1: Starting email format validation';
        if (!$this->validateEmailFormat($email)) {
            return $this->createFailureResult($email, 'Invalid email format', [
                'format_valid' => false,
                'domain_valid' => false,
                'smtp_valid' => false
            ]);
        }
        $this->debug_info['steps'][] = 'Step 1: Email format validation PASSED';
        
        // Step 2: Check 60-day cache
        $this->debug_info['steps'][] = 'Step 2: Checking 60-day validation cache';
        $cached = $this->checkValidationCache($email, 60);
        if ($cached) {
            $this->debug_info['steps'][] = 'Step 2: Found valid cache entry, returning cached result';
            // If email is cached but we want to add to list, do that
            if ($listId) {
                $this->assignToList($cached['validation_id'], $listId);
                $this->updateListStats($listId);
            }
            $cached['debug_info'] = $this->debug_info;
            $cached['cached'] = true;
            return $cached;
        }
        $this->debug_info['steps'][] = 'Step 2: No valid cache found, proceeding with validation';
        
        // Step 3: Domain Validation
        $this->debug_info['steps'][] = 'Step 3: Starting domain validation';
        $domainValid = $this->validateDomain($email);
        if (!$domainValid) {
            return $this->createFailureResult($email, 'Domain validation failed', [
                'format_valid' => true,
                'domain_valid' => false,
                'smtp_valid' => false
            ]);
        }
        $this->debug_info['steps'][] = 'Step 3: Domain validation PASSED';
        
        // Step 4: SMTP Validation
        $this->debug_info['steps'][] = 'Step 4: Starting SMTP validation';
        $smtpValid = $this->validateSMTP($email);
        $this->debug_info['steps'][] = 'Step 4: SMTP validation ' . ($smtpValid ? 'PASSED' : 'FAILED');
        
        // Create validation result
        $result = [
            'email' => $email,
            'format_valid' => true,
            'domain_valid' => $domainValid,
            'smtp_valid' => $smtpValid,
            'is_valid' => $domainValid && $smtpValid,
            'verified' => false,
            'validation_date' => date('Y-m-d H:i:s'),
            'cached' => false,
            'debug_info' => $this->debug_info
        ];
        
        // Step 5: Send verification email if requested and email is valid
        if ($sendVerification && $result['is_valid']) {
            $this->debug_info['steps'][] = 'Step 5: Attempting to send verification email';
            $result['verified'] = $this->sendVerificationEmail($email, $templateId);
            $this->debug_info['steps'][] = 'Step 5: Verification email ' . ($result['verified'] ? 'SENT' : 'FAILED');
        }
        
        // Store result in database
        $validationId = $this->storeValidationResult($result, $listId, $templateId);
        $result['validation_id'] = $validationId;
        
        // Assign to list if specified
        if ($listId) {
            $this->assignToList($validationId, $listId);
            $this->updateListStats($listId);
        }
        
        $result['debug_info'] = $this->debug_info;
        return $result;
    }
    
    /**
     * Step 1: Validate email format using multiple methods
     */
    private function validateEmailFormat($email) {
        try {
            // Basic filter validation
            $basicValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
            $this->debug_info['format_checks']['basic_filter'] = $basicValid;
            
            if (!$basicValid) {
                return false;
            }
            
            // Additional format checks
            $parts = explode('@', $email);
            if (count($parts) !== 2) {
                $this->debug_info['format_checks']['parts_count'] = false;
                return false;
            }
            
            [$localPart, $domainPart] = $parts;
            
            // Check local part length (max 64 characters)
            if (strlen($localPart) > 64) {
                $this->debug_info['format_checks']['local_part_length'] = false;
                return false;
            }
            
            // Check domain part length (max 255 characters)
            if (strlen($domainPart) > 255) {
                $this->debug_info['format_checks']['domain_part_length'] = false;
                return false;
            }
            
            // Check for valid characters in local part
            if (!preg_match('/^[a-zA-Z0-9._%+-]+$/', $localPart)) {
                $this->debug_info['format_checks']['local_part_chars'] = false;
                return false;
            }
            
            // Check domain format
            if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domainPart)) {
                $this->debug_info['format_checks']['domain_format'] = false;
                return false;
            }
            
            $this->debug_info['format_checks'] = [
                'basic_filter' => true,
                'parts_count' => true,
                'local_part_length' => true,
                'domain_part_length' => true,
                'local_part_chars' => true,
                'domain_format' => true,
                'local_part' => $localPart,
                'domain_part' => $domainPart
            ];
            
            return true;
            
        } catch (Exception $e) {
            $this->debug_info['format_error'] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Step 2: Check validation cache (60 days)
     */
    private function checkValidationCache($email, $cacheDays = 60) {
        try {
            $stmt = $this->db->prepare("
                SELECT *, id as validation_id 
                FROM email_validations 
                WHERE email = ? 
                AND validation_date > DATE_SUB(NOW(), INTERVAL ? DAY) 
                AND format_valid = 1
                ORDER BY validation_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$email, $cacheDays]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->debug_info['cache_info'] = [
                    'found' => true,
                    'validation_date' => $result['validation_date'],
                    'days_old' => $this->calculateDaysOld($result['validation_date'])
                ];
                
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
            
            $this->debug_info['cache_info'] = ['found' => false];
            return null;
            
        } catch (Exception $e) {
            $this->debug_info['cache_error'] = $e->getMessage();
            return null;
        }
    }
    
    /**
     * Step 3: Validate domain existence
     */
    private function validateDomain($email) {
        try {
            $domain = substr(strrchr($email, "@"), 1);
            $this->debug_info['domain_info']['domain'] = $domain;
            
            // Method 1: Check MX records
            if (function_exists('checkdnsrr')) {
                $mxValid = checkdnsrr($domain, "MX");
                $this->debug_info['domain_info']['mx_check'] = $mxValid;
                
                if ($mxValid) {
                    // Get actual MX records for additional info
                    if (function_exists('getmxrr')) {
                        $mxRecords = [];
                        getmxrr($domain, $mxRecords);
                        $this->debug_info['domain_info']['mx_records'] = array_slice($mxRecords, 0, 3); // First 3 MX records
                    }
                    return true;
                }
                
                // Fallback: Check A record
                $aValid = checkdnsrr($domain, "A");
                $this->debug_info['domain_info']['a_check'] = $aValid;
                
                if ($aValid) {
                    return true;
                }
            } else {
                $this->debug_info['domain_info']['checkdnsrr_available'] = false;
            }
            
            // Method 2: Fallback using gethostbyname
            $ip = gethostbyname($domain);
            $hostValid = ($ip !== $domain);
            $this->debug_info['domain_info']['host_check'] = [
                'domain' => $domain,
                'resolved_ip' => $ip,
                'valid' => $hostValid
            ];
            
            return $hostValid;
            
        } catch (Exception $e) {
            $this->debug_info['domain_error'] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Step 4: Validate SMTP delivery capability
     */
    private function validateSMTP($email) {
        try {
            $domain = substr(strrchr($email, "@"), 1);
            $this->debug_info['smtp_info']['domain'] = $domain;
            
            // Get MX records
            if (!function_exists('getmxrr')) {
                $this->debug_info['smtp_info']['getmxrr_available'] = false;
                return $this->fallbackSMTPCheck($domain);
            }
            
            $mxRecords = [];
            if (!getmxrr($domain, $mxRecords)) {
                $this->debug_info['smtp_info']['mx_records'] = 'None found';
                return false;
            }
            
            $this->debug_info['smtp_info']['mx_records'] = $mxRecords;
            
            // Try connecting to MX servers
            $ports = [587, 25, 465, 2525]; // Common SMTP ports
            $timeout = 10;
            
            foreach ($mxRecords as $index => $mx) {
                if ($index >= 3) break; // Test max 3 MX records
                
                foreach ($ports as $port) {
                    $this->debug_info['smtp_info']['attempts'][] = "Trying {$mx}:{$port}";
                    
                    $socket = @fsockopen($mx, $port, $errno, $errstr, $timeout);
                    
                    if (!$socket) {
                        $this->debug_info['smtp_info']['attempts'][] = "Failed {$mx}:{$port} - {$errno}: {$errstr}";
                        continue;
                    }
                    
                    // Read greeting
                    $response = fgets($socket);
                    $this->debug_info['smtp_info']['attempts'][] = "Connected {$mx}:{$port} - " . trim($response);
                    
                    if (substr($response, 0, 3) != '220') {
                        fclose($socket);
                        continue;
                    }
                    
                    // Try SMTP conversation
                    $success = $this->performSMTPConversation($socket, $email, $mx, $port);
                    fclose($socket);
                    
                    if ($success) {
                        $this->debug_info['smtp_info']['success'] = "{$mx}:{$port}";
                        return true;
                    }
                }
            }
            
            $this->debug_info['smtp_info']['result'] = 'All SMTP attempts failed';
            return false;
            
        } catch (Exception $e) {
            $this->debug_info['smtp_error'] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Perform SMTP conversation to test email deliverability
     */
    private function performSMTPConversation($socket, $email, $mx, $port) {
        try {
            // EHLO/HELO
            fputs($socket, "EHLO emailvalidator.local\r\n");
            $response = fgets($socket);
            $this->debug_info['smtp_info']['conversation'][] = "EHLO: " . trim($response);
            
            if (substr($response, 0, 3) != '250') {
                // Try HELO if EHLO fails
                fputs($socket, "HELO emailvalidator.local\r\n");
                $response = fgets($socket);
                $this->debug_info['smtp_info']['conversation'][] = "HELO: " . trim($response);
                
                if (substr($response, 0, 3) != '250') {
                    return false;
                }
            }
            
            // MAIL FROM
            fputs($socket, "MAIL FROM: <noreply@emailvalidator.local>\r\n");
            $response = fgets($socket);
            $this->debug_info['smtp_info']['conversation'][] = "MAIL FROM: " . trim($response);
            
            if (substr($response, 0, 3) != '250') {
                return false;
            }
            
            // RCPT TO
            fputs($socket, "RCPT TO: <{$email}>\r\n");
            $response = fgets($socket);
            $this->debug_info['smtp_info']['conversation'][] = "RCPT TO: " . trim($response);
            
            // QUIT
            fputs($socket, "QUIT\r\n");
            fgets($socket);
            
            // Check if recipient was accepted
            $responseCode = substr($response, 0, 3);
            return in_array($responseCode, ['250', '251', '252']);
            
        } catch (Exception $e) {
            $this->debug_info['smtp_info']['conversation_error'] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Fallback SMTP check when functions are not available
     */
    private function fallbackSMTPCheck($domain) {
        $ip = gethostbyname($domain);
        $valid = ($ip !== $domain);
        $this->debug_info['smtp_info']['fallback'] = [
            'method' => 'gethostbyname',
            'domain' => $domain,
            'resolved_ip' => $ip,
            'valid' => $valid
        ];
        return $valid;
    }
    
    /**
     * Send verification email
     */
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
    
    /**
     * Store validation result in database
     */
    private function storeValidationResult($result, $listId = null, $templateId = null) {
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
            'validation_version' => '2.0'
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
    }
    
    /**
     * Create failure result
     */
    private function createFailureResult($email, $reason, $validationStatus) {
        return [
            'email' => $email,
            'format_valid' => $validationStatus['format_valid'],
            'domain_valid' => $validationStatus['domain_valid'],
            'smtp_valid' => $validationStatus['smtp_valid'],
            'is_valid' => false,
            'verified' => false,
            'validation_date' => date('Y-m-d H:i:s'),
            'cached' => false,
            'failure_reason' => $reason,
            'debug_info' => $this->debug_info
        ];
    }
    
    /**
     * Assign email to list
     */
    private function assignToList($validationId, $listId) {
        if (!$listId) return;
        
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO list_email_assignments (list_id, validation_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$listId, $validationId]);
    }
    
    /**
     * Update list statistics
     */
    private function updateListStats($listId) {
        if (!$listId) return;
        
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
    
    /**
     * Calculate days old from timestamp
     */
    private function calculateDaysOld($timestamp) {
        $date = new DateTime($timestamp);
        $now = new DateTime();
        return $now->diff($date)->days;
    }
    
    /**
     * Get system information for debugging
     */
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
    
    $validator = new EnhancedEmailValidator($db, $_SESSION['user_id']);
    
    // If requesting system info
    if (isset($input['get_system_info']) && $input['get_system_info']) {
        echo json_encode([
            'system_info' => $validator->getSystemInfo()
        ]);
        exit;
    }
    
    // Validate the email
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
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}