<?php

// =============================================================================
// SYSTEM SETTINGS API (api/system-settings.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/ConfigManager.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$config = new ConfigManager($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all system settings
    try {
        $settings = $config->getAll();
        echo json_encode($settings);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load settings']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update system settings
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        foreach ($input as $key => $value) {
            $config->set($key, $value);
        }
        
        // Log activity
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, 'system_settings_updated', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            json_encode($input),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update settings']);
    }
}
?>
