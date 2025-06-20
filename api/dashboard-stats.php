<?php
// =============================================================================
// DASHBOARD STATS API (api/dashboard-stats.php)
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
    
    // Prepare base query - admins see all data, users see only their data
    $whereClause = $role === 'admin' ? '' : 'WHERE user_id = ?';
    $params = $role === 'admin' ? [] : [$user_id];
    
    // Get total validations
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM email_validations $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Get valid emails
    $stmt = $db->prepare("SELECT COUNT(*) as valid FROM email_validations $whereClause " . 
                        ($whereClause ? 'AND' : 'WHERE') . " is_valid = 1");
    $stmt->execute($params);
    $valid = $stmt->fetchColumn();
    
    // Get invalid emails
    $invalid = $total - $valid;
    
    // Calculate success rate
    $successRate = $total > 0 ? round(($valid / $total) * 100, 1) : 0;
    
    echo json_encode([
        'total' => (int)$total,
        'valid' => (int)$valid,
        'invalid' => (int)$invalid,
        'successRate' => $successRate
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load dashboard stats']);
}
?>