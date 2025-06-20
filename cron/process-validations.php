<?php
// =============================================================================
// EMAIL VERIFICATION PROCESSOR (cron/process-validations.php)
// =============================================================================

require_once '../config/database.php';

class EmailValidationProcessor {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function processPendingValidations() {
        // Get pending validations
        $stmt = $this->db->prepare("
            SELECT ev.*, eb.batch_id
            FROM email_validations ev
            JOIN email_batches eb ON ev.batch_id = eb.batch_id
            WHERE eb.status = 'pending'
            ORDER BY ev.id
            LIMIT 100
        ");
        $stmt->execute();
        $validations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($validations)) {
            return;
        }
        
        // Update batch status to processing
        $batchIds = array_unique(array_column($validations, 'batch_id'));
        foreach ($batchIds as $batchId) {
            $stmt = $this->db->prepare("UPDATE email_batches SET status = 'processing' WHERE batch_id = ?");
            $stmt->execute([$batchId]);
        }
        
        $validator = new EmailValidator($this->db, null);
        
        foreach ($validations as $validation) {
            try {
                $result = $validator->validateEmail($validation['email'], false);
                
                // Update validation record
                $stmt = $this->db->prepare("
                    UPDATE email_validations 
                    SET format_valid = ?, domain_valid = ?, smtp_valid = ?, is_valid = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $result['format_valid'],
                    $result['domain_valid'],
                    $result['smtp_valid'],
                    $result['is_valid'],
                    $validation['id']
                ]);
                
            } catch (Exception $e) {
                error_log("Validation error for {$validation['email']}: " . $e->getMessage());
            }
        }
        
        // Update batch completion status
        foreach ($batchIds as $batchId) {
            $this->updateBatchStatus($batchId);
        }
    }
    
    private function updateBatchStatus($batchId) {
        // Check if all emails in batch are processed
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid,
                SUM(CASE WHEN format_valid IS NOT NULL THEN 1 ELSE 0 END) as processed
            FROM email_validations 
            WHERE batch_id = ?
        ");
        $stmt->execute([$batchId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats['processed'] == $stats['total']) {
            // All emails processed
            $stmt = $this->db->prepare("
                UPDATE email_batches 
                SET status = 'completed', valid_emails = ?, invalid_emails = ?, completed_at = NOW()
                WHERE batch_id = ?
            ");
            $stmt->execute([
                $stats['valid'],
                $stats['total'] - $stats['valid'],
                $batchId
            ]);
        }
    }
}

// Run the processor (this would typically be called by a cron job)
if (php_sapi_name() === 'cli') {
    $processor = new EmailValidationProcessor();
    $processor->processPendingValidations();
    echo "Validation processing completed.\n";
}
?>