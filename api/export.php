<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? 'all';
$list_id = isset($_GET['list_id']) ? (int)$_GET['list_id'] : null;
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;

try {
    $filename = 'email_validation_export_' . date('Y-m-d_H-i-s') . '.csv';
    $query = '';
    $params = [$_SESSION['user_id']];
    
    if ($job_id) {
        // Export specific bulk job results
        $query = "
            SELECT ev.email, ev.status, ev.format_valid, ev.dns_valid, ev.smtp_valid, 
                   ev.is_disposable, ev.is_role_based, ev.is_catch_all, ev.risk_score, 
                   ev.created_at
            FROM email_validations ev
            JOIN bulk_jobs bj ON ev.user_id = bj.user_id
            WHERE bj.id = ? AND bj.user_id = ?
            AND ev.created_at BETWEEN bj.started_at AND COALESCE(bj.completed_at, NOW())
            ORDER BY ev.created_at DESC
        ";
        $params = [$job_id, $_SESSION['user_id']];
        $filename = "bulk_job_{$job_id}_export_" . date('Y-m-d_H-i-s') . '.csv';
        
    } elseif ($list_id) {
        // Export specific list
        $query = "
            SELECT ev.email, ev.status, ev.format_valid, ev.dns_valid, ev.smtp_valid, 
                   ev.is_disposable, ev.is_role_based, ev.is_catch_all, ev.risk_score, 
                   ev.created_at, el.name as list_name
            FROM email_validations ev
            JOIN email_list_items eli ON ev.id = eli.validation_id
            JOIN email_lists el ON eli.list_id = el.id
            WHERE el.id = ? AND el.user_id = ?
            ORDER BY ev.created_at DESC
        ";
        $params = [$list_id, $_SESSION['user_id']];
        $filename = "list_export_" . date('Y-m-d_H-i-s') . '.csv';
        
    } else {
        // Export user's validations based on type
        $base_query = "
            SELECT email, status, format_valid, dns_valid, smtp_valid, 
                   is_disposable, is_role_based, is_catch_all, risk_score, created_at
            FROM email_validations 
            WHERE user_id = ?
        ";
        
        switch ($type) {
            case 'valid':
                $query = $base_query . " AND status = 'valid'";
                $filename = 'valid_emails_' . date('Y-m-d_H-i-s') . '.csv';
                break;
            case 'invalid':
                $query = $base_query . " AND status = 'invalid'";
                $filename = 'invalid_emails_' . date('Y-m-d_H-i-s') . '.csv';
                break;
            case 'risky':
                $query = $base_query . " AND status = 'risky'";
                $filename = 'risky_emails_' . date('Y-m-d_H-i-s') . '.csv';
                break;
            default:
                $query = $base_query;
                break;
        }
        $query .= " ORDER BY created_at DESC";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    if (empty($results)) {
        die('No data to export');
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    $headers = [
        'Email', 'Status', 'Format Valid', 'DNS Valid', 'SMTP Valid',
        'Disposable', 'Role Based', 'Catch All', 'Risk Score', 'Validated Date'
    ];
    
    if ($list_id) {
        $headers[] = 'List Name';
    }
    
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($results as $row) {
        $data = [
            $row['email'],
            ucfirst($row['status']),
            $row['format_valid'] ? 'Yes' : 'No',
            $row['dns_valid'] ? 'Yes' : 'No',
            $row['smtp_valid'] ? 'Yes' : 'No',
            $row['is_disposable'] ? 'Yes' : 'No',
            $row['is_role_based'] ? 'Yes' : 'No',
            $row['is_catch_all'] ? 'Yes' : 'No',
            $row['risk_score'],
            date('Y-m-d H:i:s', strtotime($row['created_at']))
        ];
        
        if ($list_id) {
            $data[] = $row['list_name'] ?? '';
        }
        
        fputcsv($output, $data);
    }
    
    fclose($output);
    
    // Log the export
    log_activity($_SESSION['user_id'], 'data_exported', "Type: $type, Records: " . count($results));
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    die('Export failed');
}
?>