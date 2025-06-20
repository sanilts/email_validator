<?php
// =============================================================================
// EXPORT RESULTS API (api/export-results.php)
// =============================================================================

require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Build query based on user role
    $whereClause = $role === 'admin' ? '' : 'WHERE user_id = ?';
    $params = $role === 'admin' ? [] : [$user_id];
    
    $stmt = $db->prepare("
        SELECT email, is_valid, format_valid, domain_valid, smtp_valid, validation_date, batch_id
        FROM email_validations 
        $whereClause
        ORDER BY validation_date DESC
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="email_validation_results_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, [
        'Email',
        'Valid',
        'Format Valid',
        'Domain Valid',
        'SMTP Valid',
        'Validation Date',
        'Batch ID'
    ]);
    
    // Write data
    foreach ($results as $result) {
        fputcsv($output, [
            $result['email'],
            $result['is_valid'] ? 'Yes' : 'No',
            $result['format_valid'] ? 'Yes' : 'No',
            $result['domain_valid'] ? 'Yes' : 'No',
            $result['smtp_valid'] ? 'Yes' : 'No',
            $result['validation_date'],
            $result['batch_id']
        ]);
    }
    
    fclose($output);
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, 'export_results', ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        json_encode(['record_count' => count($results)]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error exporting results';
}
?>