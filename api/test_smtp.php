<?php
// ===================================================================
// API/TEST_SMTP.PHP - SMTP Connection Testing
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

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $config = [
        'host' => sanitize_input($_POST['host'] ?? ''),
        'port' => (int)($_POST['port'] ?? 587),
        'username' => sanitize_input($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'encryption' => sanitize_input($_POST['encryption'] ?? 'tls')
    ];
    
    if (empty($config['host']) || empty($config['username'])) {
        json_response(['success' => false, 'message' => 'Host and username are required']);
    }
    
    $smtp_handler = new SMTPHandler($config);
    $result = $smtp_handler->testConnection();
    
    log_activity($_SESSION['user_id'], 'smtp_test', "Host: {$config['host']}, Result: " . ($result['success'] ? 'Success' : 'Failed'));
    
    json_response($result);
    
} catch (Exception $e) {
    error_log("SMTP test error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Test failed: ' . $e->getMessage()], 500);
}
?>