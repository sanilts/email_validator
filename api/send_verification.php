<?php
// ===================================================================
// API/SEND_VERIFICATION.PHP - Send Verification Email
// ===================================================================
?>
<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/smtp_handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = sanitize_input($input['email'] ?? '');

if (empty($email)) {
    json_response(['success' => false, 'message' => 'Email is required']);
}

if (!validate_email_format($email)) {
    json_response(['success' => false, 'message' => 'Invalid email format']);
}

if (!check_rate_limit($_SESSION['user_id'], 'send_verification')) {
    json_response(['success' => false, 'message' => 'Rate limit exceeded'], 429);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Generate verification code
    $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Store verification code
    $stmt = $db->prepare("
        INSERT INTO email_verifications (user_id, email, verification_code, expires_at) 
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ON DUPLICATE KEY UPDATE 
        verification_code = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmt->execute([$_SESSION['user_id'], $email, $verification_code, $verification_code]);
    
    // Send verification email
    $smtp_handler = new EnhancedSMTPHandler($db);
    $sent = $smtp_handler->sendVerificationEmail($_SESSION['user_id'], $email, $verification_code);
    
    if ($sent) {
        log_activity($_SESSION['user_id'], 'verification_email_sent', "Email: $email");
        json_response(['success' => true, 'message' => 'Verification email sent successfully']);
    } else {
        json_response(['success' => false, 'message' => 'Failed to send verification email']);
    }
    
} catch (Exception $e) {
    error_log("Verification email error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to send verification email'], 500);
}
?>