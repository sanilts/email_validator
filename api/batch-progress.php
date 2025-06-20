<?php
// =============================================================================
// BATCH PROGRESS API (api/batch-progress.php)
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
    
    $batchId = $_GET['batch_id'] ?? '';
    
    if (empty($batchId)) {
        throw new Exception('Batch ID required');
    }
    
    // Get batch info
    $stmt = $db->prepare("SELECT * FROM email_batches WHERE batch_id = ? AND user_id = ?");
    $stmt->execute([$batchId, $_SESSION['user_id']]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        throw new Exception('Batch not found');
    }
    
    // Get validation progress
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN format_valid IS NOT NULL THEN 1 ELSE 0 END) as processed,
            SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid
        FROM email_validations 
        WHERE batch_id = ?
    ");
    $stmt->execute([$batchId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'batch_id' => $batchId,
        'status' => $batch['status'],
        'total' => (int)$progress['total'],
        'processed' => (int)$progress['processed'],
        'valid' => (int)$progress['valid'],
        'invalid' => (int)$progress['processed'] - (int)$progress['valid'],
        'percentage' => $progress['total'] > 0 ? round(($progress['processed'] / $progress['total']) * 100, 1) : 0
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>