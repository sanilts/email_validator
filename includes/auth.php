<?php
// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        redirect(BASE_URL . 'auth/login.php');
    }
    exit();
}

// Check session security
require_once __DIR__ . '/security.php';
$database = new Database();
$db = $database->getConnection();
$security = new SecurityManager($db);

if (!$security->check_session_security()) {
    redirect(BASE_URL . 'auth/login.php');
}

// Check if user still exists and is active
$stmt = $db->prepare("SELECT is_active FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['is_active']) {
    session_unset();
    session_destroy();
    redirect(BASE_URL . 'auth/login.php');
}

// Check for suspicious activity
if ($security->detect_suspicious_activity($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'suspicious_activity_warning');
}
?>