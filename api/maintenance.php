<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    json_response(['success' => false, 'message' => 'Access denied'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$database = new Database();
$db = $database->getConnection();

try {
    switch ($action) {
        case 'clear_cache':
            $stmt = $db->prepare("DELETE FROM email_validations WHERE expires_at <= NOW()");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            log_activity($_SESSION['user_id'], 'cache_cleared', "Deleted: $deleted records");
            json_response(['success' => true, 'deleted' => $deleted]);
            break;
            
        case 'optimize_database':
            $tables = ['users', 'email_validations', 'email_lists', 'email_list_items', 
                      'email_templates', 'smtp_configs', 'activity_logs', 'bulk_jobs'];
            
            foreach ($tables as $table) {
                $db->exec("OPTIMIZE TABLE $table");
            }
            
            log_activity($_SESSION['user_id'], 'database_optimized');
            json_response(['success' => true, 'message' => 'Database optimized']);
            break;
            
        case 'cleanup_logs':
            $stmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            log_activity($_SESSION['user_id'], 'logs_cleaned', "Deleted: $deleted records");
            json_response(['success' => true, 'deleted' => $deleted]);
            break;
            
        default:
            json_response(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    error_log("Maintenance error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Maintenance failed'], 500);
}
?>