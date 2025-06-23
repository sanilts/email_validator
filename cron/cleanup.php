<?php
// ===================================================================
// CRON/CLEANUP.PHP - Maintenance Cron Job
// ===================================================================
?>
<?php
// This file should be run via cron job periodically
// Example: 0 2 * * * /usr/bin/php /path/to/email-validator/cron/cleanup.php

require_once dirname(__DIR__) . '/config/config.php';

echo "Starting maintenance cleanup...\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // 1. Clean up expired email validations
    echo "Cleaning up expired validations...\n";
    $stmt = $db->prepare("DELETE FROM email_validations WHERE expires_at <= NOW()");
    $stmt->execute();
    $expired_count = $stmt->rowCount();
    echo "Deleted $expired_count expired validations\n";
    
    // 2. Clean up old activity logs (older than 6 months)
    echo "Cleaning up old activity logs...\n";
    $stmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
    $stmt->execute();
    $logs_count = $stmt->rowCount();
    echo "Deleted $logs_count old log entries\n";
    
    // 3. Clean up completed bulk jobs (older than 30 days)
    echo "Cleaning up old bulk jobs...\n";
    $stmt = $db->prepare("DELETE FROM bulk_jobs WHERE status = 'completed' AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $jobs_count = $stmt->rowCount();
    echo "Deleted $jobs_count old bulk jobs\n";
    
    // 4. Clean up expired verification codes
    echo "Cleaning up expired verification codes...\n";
    $stmt = $db->prepare("DELETE FROM email_verifications WHERE expires_at <= NOW()");
    $stmt->execute();
    $verifications_count = $stmt->rowCount();
    echo "Deleted $verifications_count expired verification codes\n";
    
    // 5. Optimize database tables
    echo "Optimizing database tables...\n";
    $tables = ['users', 'email_validations', 'email_lists', 'email_list_items', 
               'email_templates', 'smtp_configs', 'activity_logs', 'bulk_jobs'];
    
    foreach ($tables as $table) {
        try {
            $db->exec("OPTIMIZE TABLE $table");
            echo "Optimized table: $table\n";
        } catch (Exception $e) {
            echo "Failed to optimize table $table: " . $e->getMessage() . "\n";
        }
    }
    
    // 6. Log cleanup activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, created_at) 
        VALUES (NULL, 'system_cleanup', ?, NOW())
    ");
    $details = "Expired validations: $expired_count, Old logs: $logs_count, Old jobs: $jobs_count, Verifications: $verifications_count";
    $stmt->execute([$details]);
    
    echo "Maintenance cleanup completed successfully!\n";
    echo "Summary: $expired_count validations, $logs_count logs, $jobs_count jobs, $verifications_count verifications cleaned\n";
    
} catch (Exception $e) {
    echo "Maintenance cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>