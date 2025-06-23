<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/email_validator.php';

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

if (!check_rate_limit($_SESSION['user_id'], 'api_validation')) {
    json_response(['success' => false, 'message' => 'Rate limit exceeded'], 429);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $validator = new EmailValidator($db);
    
    $result = $validator->validate_email($email, $_SESSION['user_id']);
    
    log_activity($_SESSION['user_id'], 'api_email_validation', "Email: $email, Status: {$result['status']}");
    
    json_response(['success' => true, 'result' => $result]);
    
} catch (Exception $e) {
    error_log("API validation error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Validation failed'], 500);
}
?>