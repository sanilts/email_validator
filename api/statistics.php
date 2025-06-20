<?php
// =============================================================================
// STATISTICS API (api/statistics.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/StatsManager.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $stats = new StatsManager($db);
    
    $type = $_GET['type'] ?? 'user';
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    
    $result = [];
    
    switch ($type) {
        case 'user':
            $result = $stats->getUserStats($_SESSION['user_id'], $days);
            break;
            
        case 'system':
            if ($_SESSION['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            $result = $stats->getSystemStats($days);
            break;
            
        case 'trends':
            $userId = $_SESSION['role'] === 'admin' ? null : $_SESSION['user_id'];
            $result = $stats->getValidationTrends($userId, $days);
            break;
            
        case 'domains':
            $userId = $_SESSION['role'] === 'admin' ? null : $_SESSION['user_id'];
            $result = $stats->getTopDomains($userId, 10);
            break;
            
        default:
            throw new Exception('Invalid statistics type');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>