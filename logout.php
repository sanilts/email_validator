<?php
// =============================================================================
// LOGOUT (logout.php)
// =============================================================================

session_start();

if (isset($_SESSION['user_id'])) {
    // Log activity
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'logout', ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
}

session_destroy();
header('Location: login.php');
exit;
?>
