<?php
// =============================================================================
// USER MANAGEMENT API (api/users.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all users
    try {
        $stmt = $db->prepare("
            SELECT id, username, email, role, is_active, created_at, last_login,
                   (SELECT COUNT(*) FROM email_validations WHERE user_id = users.id) as validation_count
            FROM users 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($users);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load users']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new user
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            throw new Exception('All fields are required');
        }
        
        // Check if username or email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input['username'], $input['email']]);
        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }
        
        // Create user
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, role) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['username'],
            $input['email'],
            $hashedPassword,
            $input['role'] ?? 'user'
        ]);
        
        // Log activity
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, 'user_created', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            json_encode(['new_user' => $input['username']]),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'User created successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update user
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['id'];
        
        $stmt = $db->prepare("
            UPDATE users 
            SET username = ?, email = ?, role = ?, is_active = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $input['username'],
            $input['email'],
            $input['role'],
            $input['is_active'] ? 1 : 0,
            $userId
        ]);
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to update user']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Delete user (soft delete)
    try {
        $userId = $_GET['id'] ?? null;
        
        if (!$userId) {
            throw new Exception('User ID required');
        }
        
        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        
        echo json_encode(['success' => true, 'message' => 'User deactivated successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>