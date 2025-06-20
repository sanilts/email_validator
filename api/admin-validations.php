<?php
// =============================================================================
// ADMIN VALIDATIONS API (api/admin-validations.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;
    
    // Build where clause based on filters
    $whereConditions = [];
    $params = [];
    
    if (!empty($_GET['user'])) {
        $whereConditions[] = 'ev.user_id = ?';
        $params[] = $_GET['user'];
    }
    
    if (!empty($_GET['list'])) {
        $whereConditions[] = 'ev.list_id = ?';
        $params[] = $_GET['list'];
    }
    
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $whereConditions[] = 'ev.is_valid = ?';
        $params[] = $_GET['status'];
    }
    
    if (!empty($_GET['date'])) {
        $whereConditions[] = 'DATE(ev.validation_date) = ?';
        $params[] = $_GET['date'];
    }
    
    if (!empty($_GET['search'])) {
        $whereConditions[] = 'ev.email LIKE ?';
        $params[] = '%' . $_GET['search'] . '%';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $stmt = $db->prepare("
        SELECT ev.*, u.username, el.list_name
        FROM email_validations ev
        JOIN users u ON ev.user_id = u.id
        LEFT JOIN email_lists el ON ev.list_id = el.id
        $whereClause
        ORDER BY ev.validation_date DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $validations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($validations);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load validations']);
}
?>