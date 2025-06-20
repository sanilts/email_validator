<?php
// =============================================================================
// BACKUP & MAINTENANCE (utils/backup.php)
// =============================================================================

require_once '../config/database.php';

class BackupManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function createBackup($outputPath = null) {
        if (!$outputPath) {
            $outputPath = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        }
        
        $tables = $this->getTables();
        $sql = '';
        
        foreach ($tables as $table) {
            $sql .= $this->dumpTable($table);
        }
        
        file_put_contents($outputPath, $sql);
        return $outputPath;
    }
    
    private function getTables() {
        $stmt = $this->db->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function dumpTable($table) {
        $sql = "\n-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Get table structure
        $stmt = $this->db->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql .= $createTable['Create Table'] . ";\n\n";
        
        // Get table data
        $stmt = $this->db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $sql .= "INSERT INTO `$table` VALUES\n";
            $values = [];
            
            foreach ($rows as $row) {
                $escapedValues = array_map([$this->db, 'quote'], $row);
                $values[] = '(' . implode(', ', $escapedValues) . ')';
            }
            
            $sql .= implode(",\n", $values) . ";\n\n";
        }
        
        return $sql;
    }
    
    public function cleanupOldData($days = 365) {
        // Remove old validation records
        $stmt = $this->db->prepare("
            DELETE FROM email_validations 
            WHERE validation_date < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $deletedValidations = $stmt->execute([$days]);
        
        // Remove old activity logs
        $stmt = $this->db->prepare("
            DELETE FROM activity_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $deletedLogs = $stmt->execute([$days]);
        
        return [
            'validations_deleted' => $stmt->rowCount(),
            'logs_deleted' => $stmt->rowCount()
        ];
    }
    
    public function optimizeTables() {
        $tables = $this->getTables();
        $results = [];
        
        foreach ($tables as $table) {
            $stmt = $this->db->query("OPTIMIZE TABLE `$table`");
            $results[$table] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $results;
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $backup = new BackupManager();
    
    $command = $argv[1] ?? 'help';
    
    switch ($command) {
        case 'backup':
            $file = $backup->createBackup();
            echo "Backup created: $file\n";
            break;
            
        case 'cleanup':
            $days = isset($argv[2]) ? (int)$argv[2] : 365;
            $result = $backup->cleanupOldData($days);
            echo "Cleanup completed:\n";
            echo "- Validations deleted: {$result['validations_deleted']}\n";
            echo "- Logs deleted: {$result['logs_deleted']}\n";
            break;
            
        case 'optimize':
            $results = $backup->optimizeTables();
            echo "Tables optimized:\n";
            foreach ($results as $table => $result) {
                echo "- $table: {$result['Msg_text']}\n";
            }
            break;
            
        default:
            echo "Usage: php backup.php [backup|cleanup|optimize]\n";
            echo "  backup   - Create database backup\n";
            echo "  cleanup  - Remove old data (default: 365 days)\n";
            echo "  optimize - Optimize database tables\n";
            break;
    }
}
?>