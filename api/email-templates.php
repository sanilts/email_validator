<?php
// =============================================================================
// EMAIL TEMPLATES API (api/email-templates.php)
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
    try {
        if (isset($_GET['id'])) {
            // Get specific template
            $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user_id]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception('Template not found');
            }
            
            echo json_encode($template);
        } else {
            // Get all user templates
            $stmt = $db->prepare("
                SELECT * FROM email_templates 
                WHERE user_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$user_id]);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($templates);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load templates']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['template_name']) || empty($input['subject']) || empty($input['message'])) {
            throw new Exception('Template name, subject, and message are required');
        }
        
        // If setting as default, unset other defaults
        if ($input['is_default']) {
            $stmt = $db->prepare("UPDATE email_templates SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        
        $stmt = $db->prepare("
            INSERT INTO email_templates (template_name, subject, message, template_type, user_id, is_default) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['template_name'],
            $input['subject'],
            $input['message'],
            $input['template_type'] ?? 'verification',
            $user_id,
            $input['is_default'] ? 1 : 0
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Template created successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id']) || empty($input['template_name']) || empty($input['subject']) || empty($input['message'])) {
            throw new Exception('Template ID, name, subject, and message are required');
        }
        
        // If setting as default, unset other defaults
        if ($input['is_default']) {
            $stmt = $db->prepare("UPDATE email_templates SET is_default = 0 WHERE user_id = ? AND id != ?");
            $stmt->execute([$user_id, $input['id']]);
        }
        
        $stmt = $db->prepare("
            UPDATE email_templates 
            SET template_name = ?, subject = ?, message = ?, is_default = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $input['template_name'],
            $input['subject'],
            $input['message'],
            $input['is_default'] ? 1 : 0,
            $input['id'],
            $user_id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Template updated successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $template_id = $_GET['id'] ?? null;
        
        if (!$template_id) {
            throw new Exception('Template ID required');
        }
        
        $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ? AND user_id = ?");
        $stmt->execute([$template_id, $user_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Template not found');
        }
        
        echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>