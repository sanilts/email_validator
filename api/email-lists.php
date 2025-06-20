<?php
// =============================================================================
// EMAIL LISTS API (api/email-lists.php)
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
$role = $_SESSION['role'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $all = isset($_GET['all']) && $_GET['all'] == '1' && $role === 'admin';
        
        if ($all) {
            // Admin can see all lists with user info
            $stmt = $db->prepare("
                SELECT el.*, u.username 
                FROM email_lists el
                JOIN users u ON el.user_id = u.id
                WHERE el.is_active = 1
                ORDER BY el.created_at DESC
            ");
            $stmt->execute();
        } else {
            // Users see only their lists
            $stmt = $db->prepare("
                SELECT * FROM email_lists 
                WHERE user_id = ? AND is_active = 1
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id]);
        }
        
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($lists);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load lists']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['list_name'])) {
            throw new Exception('List name is required');
        }
        
        // Check if list name already exists for this user
        $stmt = $db->prepare("SELECT id FROM email_lists WHERE user_id = ? AND list_name = ? AND is_active = 1");
        $stmt->execute([$user_id, $input['list_name']]);
        if ($stmt->fetch()) {
            throw new Exception('List name already exists');
        }
        
        $stmt = $db->prepare("
            INSERT INTO email_lists (list_name, description, user_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $input['list_name'],
            $input['description'] ?? null,
            $user_id
        ]);
        
        $list_id = $db->lastInsertId();
        
        // Log activity
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, 'list_created', ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            json_encode(['list_name' => $input['list_name'], 'list_id' => $list_id]),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'List created successfully', 'list_id' => $list_id]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $list_id = $_GET['id'] ?? null;
        
        if (!$list_id) {
            throw new Exception('List ID required');
        }
        
        // Check if user owns this list or is admin
        $stmt = $db->prepare("SELECT user_id FROM email_lists WHERE id = ?");
        $stmt->execute([$list_id]);
        $list = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$list || ($list['user_id'] != $user_id && $role !== 'admin')) {
            throw new Exception('List not found or access denied');
        }
        
        // Soft delete
        $stmt = $db->prepare("UPDATE email_lists SET is_active = 0 WHERE id = ?");
        $stmt->execute([$list_id]);
        
        echo json_encode(['success' => true, 'message' => 'List deleted successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>