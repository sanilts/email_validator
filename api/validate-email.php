<?php
// =============================================================================
// EMAIL VALIDATION API (api/validate-email.php)
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
    
    public function __construct($database, $user_id) {
        $this->db = $database;
        $this->user_id = $user_id;
    }
    
    public function validateEmail($email, $sendVerification = false, $listId = null, $templateId = null) {
        // Check cache first (within 3 months)
        $cached = $this->checkCache($email);
        if ($cached) {
            // If email is cached but we want to add to list, do that
            if ($listId) {
                $this->assignToList($cached['validation_id'], $listId);
            }
            return $cached;
        }
        
        $result = [
            'email' => $email,
            'format_valid' => $this->validateFormat($email),
            'domain_valid' => false,
            'smtp_valid' => false,
            'is_valid' => false,
            'verified' => false,
            'validation_date' => date('Y-m-d H:i:s')
        ];
        
        if ($result['format_valid']) {
            $result['domain_valid'] = $this->validateDomain($email);
            
            if ($result['domain_valid']) {
                $result['smtp_valid'] = $this->validateSMTP($email);
                
                if ($sendVerification && $result['smtp_valid']) {
                    $result['verified'] = $this->sendVerificationEmail($email, $templateId);
                }
            }
        }
        
        $result['is_valid'] = $result['format_valid'] && $result['domain_valid'] && $result['smtp_valid'];
        
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
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function validateDomain($email) {
        $domain = substr(strrchr($email, "@"), 1);
        return checkdnsrr($domain, "MX") || checkdnsrr($domain, "A");
    }
    
    private function validateSMTP($email) {
        $domain = substr(strrchr($email, "@"), 1);
        $mxRecords = [];
        
        if (!getmxrr($domain, $mxRecords)) {
            return false;
        }
        
        $mx = $mxRecords[0];
        $socket = @fsockopen($mx, 25, $errno, $errstr, 10);
        
        if (!$socket) {
            return false;
        }
        
        $response = fgets($socket);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return false;
        }
        
        fputs($socket, "HELO example.com\r\n");
        $response = fgets($socket);
        
        fputs($socket, "MAIL FROM: <test@example.com>\r\n");
        $response = fgets($socket);
        
        fputs($socket, "RCPT TO: <$email>\r\n");
        $response = fgets($socket);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return substr($response, 0, 3) == '250';
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
            'timestamp' => time()
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
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $validator = new EmailValidator($db, $_SESSION['user_id']);
    $result = $validator->validateEmail(
        $email, 
        $input['sendVerification'] ?? false,
        $input['listId'] ?? null,
        $input['templateId'] ?? null
    );
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Validation failed: ' . $e->getMessage()]);
}
?>