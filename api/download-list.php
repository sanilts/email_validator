<?php
// =============================================================================
// DOWNLOAD LIST API (api/download-list.php)
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
    
    $list_id = $_GET['list_id'] ?? null;
    $type = $_GET['type'] ?? 'all'; // all, valid, invalid
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    if (!$list_id) {
        throw new Exception('List ID required');
    }
    
    // Check if user owns this list or is admin
    $stmt = $db->prepare("SELECT list_name, user_id FROM email_lists WHERE id = ?");
    $stmt->execute([$list_id]);
    $list = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$list || ($list['user_id'] != $user_id && $role !== 'admin')) {
        throw new Exception('List not found or access denied');
    }
    
    // Build query based on download type
    $whereClause = 'WHERE lea.list_id = ?';
    $params = [$list_id];
    
    switch ($type) {
        case 'valid':
            $whereClause .= ' AND ev.is_valid = 1';
            $filename_suffix = '_valid';
            break;
        case 'invalid':
            $whereClause .= ' AND ev.is_valid = 0';
            $filename_suffix = '_invalid';
            break;
        default:
            $filename_suffix = '_all';
            break;
    }
    
    $stmt = $db->prepare("
        SELECT ev.email, ev.is_valid, ev.format_valid, ev.domain_valid, ev.smtp_valid, ev.validation_date
        FROM email_validations ev
        JOIN list_email_assignments lea ON ev.id = lea.validation_id
        $whereClause
        ORDER BY ev.validation_date DESC
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $list['list_name']) . $filename_suffix . '_' . date('Y-m-d') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV header based on type
    if ($type === 'valid' || $type === 'invalid') {
        fputcsv($output, ['Email', 'Validation Date']);
        foreach ($results as $result) {
            fputcsv($output, [$result['email'], $result['validation_date']]);
        }
    } else {
        fputcsv($output, [
            'Email',
            'Status',
            'Format Valid',
            'Domain Valid',
            'SMTP Valid',
            'Validation Date'
        ]);
        foreach ($results as $result) {
            fputcsv($output, [
                $result['email'],
                $result['is_valid'] ? 'Valid' : 'Invalid',
                $result['format_valid'] ? 'Yes' : 'No',
                $result['domain_valid'] ? 'Yes' : 'No',
                $result['smtp_valid'] ? 'Yes' : 'No',
                $result['validation_date']
            ]);
        }
    }
    
    fclose($output);
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, 'list_downloaded', ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        json_encode(['list_id' => $list_id, 'list_name' => $list['list_name'], 'type' => $type, 'record_count' => count($results)]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error downloading list: ' . $e->getMessage();
}
?>