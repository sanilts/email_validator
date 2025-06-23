<?php
// ===================================================================
// API/VERIFY_CODE.PHP - Verify Email Code
// ===================================================================
?>
<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = sanitize_input($input['email'] ?? '');
$code = sanitize_input($input['code'] ?? '');

if (empty($email) || empty($code)) {
    json_response(['success' => false, 'message' => 'Email and verification code are required']);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("
        SELECT * FROM email_verifications 
        WHERE user_id = ? AND email = ? AND verification_code = ? 
        AND expires_at > NOW()
    ");
    $stmt->execute([$_SESSION['user_id'], $email, $code]);
    $verification = $stmt->fetch();
    
    if ($verification) {
        // Mark as verified
        $stmt = $db->prepare("
            UPDATE email_verifications 
            SET verified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$verification['id']]);
        
        log_activity($_SESSION['user_id'], 'email_verified', "Email: $email");
        json_response(['success' => true, 'message' => 'Email verified successfully']);
    } else {
        json_response(['success' => false, 'message' => 'Invalid or expired verification code']);
    }
    
} catch (Exception $e) {
    error_log("Verification error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Verification failed'], 500);
}
?>