<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$job_id = (int)($_GET['job_id'] ?? 0);

if (!$job_id) {
    json_response(['success' => false, 'message' => 'Job ID required']);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("
        SELECT * FROM bulk_jobs 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $job = $stmt->fetch();
    
    if (!$job) {
        json_response(['success' => false, 'message' => 'Job not found']);
    }
    
    $progress = $job['total_emails'] > 0 ? 
        round(($job['processed_emails'] / $job['total_emails']) * 100, 2) : 0;
    
    json_response([
        'success' => true,
        'job' => [
            'id' => $job['id'],
            'status' => $job['status'],
            'total_emails' => $job['total_emails'],
            'processed_emails' => $job['processed_emails'],
            'valid_emails' => $job['valid_emails'],
            'invalid_emails' => $job['invalid_emails'],
            'progress' => $progress
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Progress check error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to get progress'], 500);
}
?>