<?php
// =============================================================================
// VALIDATION DETAILS API (api/get-validation-details.php)
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
    
    $validationId = $_GET['id'] ?? '';
    
    if (empty($validationId)) {
        throw new Exception('Validation ID required');
    }
    
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Build query based on user role
    $whereClause = $role === 'admin' ? 'WHERE ev.id = ?' : 'WHERE ev.id = ? AND ev.user_id = ?';
    $params = $role === 'admin' ? [$validationId] : [$validationId, $user_id];
    
    $stmt = $db->prepare("
        SELECT ev.*, u.username, eb.filename
        FROM email_validations ev
        LEFT JOIN users u ON ev.user_id = u.id
        LEFT JOIN email_batches eb ON ev.batch_id = eb.batch_id
        $whereClause
    ");
    $stmt->execute($params);
    $validation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$validation) {
        throw new Exception('Validation not found');
    }
    
    // Parse validation details if available
    if ($validation['validation_details']) {
        $validation['validation_details'] = json_decode($validation['validation_details'], true);
    }
    
    echo json_encode($validation);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>