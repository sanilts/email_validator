<?php
// =============================================================================
// FIXED SMTP SETTINGS API (api/smtp-settings.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get current SMTP settings
    try {
        $stmt = $db->prepare("SELECT * FROM smtp_settings WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            // Hide sensitive data but indicate if they exist
            $settings['smtp_password'] = $settings['smtp_password'] ? '••••••••' : '';
            $settings['api_key'] = $settings['api_key'] ? '••••••••' : '';
            $settings['has_password'] = !empty($settings['smtp_password']);
            $settings['has_api_key'] = !empty($settings['api_key']);
        }
        
        echo json_encode($settings ?: []);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load settings']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save SMTP settings
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Get existing settings to preserve passwords/keys if not provided
        $stmt = $db->prepare("SELECT * FROM smtp_settings WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Prepare values, keeping existing sensitive data if new values are placeholder or empty
        $smtp_password = null;
        $api_key = null;
        
        // Handle SMTP password
        if (!empty($input['smtp_password']) && $input['smtp_password'] !== '••••••••') {
            $smtp_password = $input['smtp_password'];
        } elseif ($existing && !empty($existing['smtp_password'])) {
            $smtp_password = $existing['smtp_password']; // Keep existing
        }
        
        // Handle API key
        if (!empty($input['api_key']) && $input['api_key'] !== '••••••••') {
            $api_key = $input['api_key'];
        } elseif ($existing && !empty($existing['api_key'])) {
            $api_key = $existing['api_key']; // Keep existing
        }
        
        // Deactivate existing settings
        $stmt = $db->prepare("UPDATE smtp_settings SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Insert new settings
        $stmt = $db->prepare("
            INSERT INTO smtp_settings 
            (user_id, provider_type, smtp_host, smtp_port, smtp_username, smtp_password, 
             api_key, from_email, from_name, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $user_id,
            $input['provider_type'],
            $input['smtp_host'] ?? null,
            $input['smtp_port'] ?? null,
            $input['smtp_username'] ?? null,
            $smtp_password,
            $api_key,
            $input['from_email'],
            $input['from_name'] ?? null
        ]);
        
        // Log activity
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, 'smtp_settings_updated', ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            json_encode([
                'provider_type' => $input['provider_type'],
                'has_password' => !empty($smtp_password),
                'has_api_key' => !empty($api_key)
            ]),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'SMTP settings saved successfully']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save settings: ' . $e->getMessage()]);
    }
}
?>