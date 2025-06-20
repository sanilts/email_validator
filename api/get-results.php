<?php
// =============================================================================
// GET RESULTS API (api/get-results.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Build query based on user role
    $whereClause = '';
    $params = [];
    
    if ($role !== 'admin') {
        $whereClause = 'WHERE user_id = ?';
        $params[] = $user_id;
    }
    
    if ($search) {
        $whereClause .= ($whereClause ? ' AND' : 'WHERE') . ' email LIKE ?';
        $params[] = '%' . $search . '%';
    }
    
    $stmt = $db->prepare("
        SELECT id, email, is_valid, format_valid, domain_valid, smtp_valid, validation_date, batch_id
        FROM email_validations 
        $whereClause 
        ORDER BY validation_date DESC 
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load results']);
}
?>