<?php
// ===================================================================
// API/BACKUP.PHP - Database Backup API
// ===================================================================
?>
<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    json_response(['success' => false, 'message' => 'Access denied'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($action) {
        case 'create_backup':
            $backup_data = createDatabaseBackup($db);
            
            $backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_path = UPLOAD_PATH . 'backups/';
            
            if (!is_dir($backup_path)) {
                mkdir($backup_path, 0755, true);
            }
            
            file_put_contents($backup_path . $backup_filename, $backup_data);
            
            log_activity($_SESSION['user_id'], 'database_backup_created', "File: $backup_filename");
            
            json_response([
                'success' => true, 
                'message' => 'Backup created successfully',
                'filename' => $backup_filename,
                'size' => format_file_size(strlen($backup_data))
            ]);
            break;
            
        case 'list_backups':
            $backup_path = UPLOAD_PATH . 'backups/';
            $backups = [];
            
            if (is_dir($backup_path)) {
                $files = array_diff(scandir($backup_path), ['.', '..']);
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                        $filepath = $backup_path . $file;
                        $backups[] = [
                            'filename' => $file,
                            'size' => format_file_size(filesize($filepath)),
                            'created' => date('Y-m-d H:i:s', filemtime($filepath))
                        ];
                    }
                }
            }
            
            json_response(['success' => true, 'backups' => $backups]);
            break;
            
        case 'download_backup':
            $filename = $input['filename'] ?? '';
            $backup_path = UPLOAD_PATH . 'backups/' . $filename;
            
            if (!file_exists($backup_path) || pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
                json_response(['success' => false, 'message' => 'Backup file not found'], 404);
            }
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($backup_path));
            readfile($backup_path);
            exit();
            break;
            
        case 'delete_backup':
            $filename = $input['filename'] ?? '';
            $backup_path = UPLOAD_PATH . 'backups/' . $filename;
            
            if (!file_exists($backup_path) || pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
                json_response(['success' => false, 'message' => 'Backup file not found'], 404);
            }
            
            unlink($backup_path);
            log_activity($_SESSION['user_id'], 'database_backup_deleted', "File: $filename");
            
            json_response(['success' => true, 'message' => 'Backup deleted successfully']);
            break;
            
        default:
            json_response(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log("Backup API error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Backup operation failed'], 500);
}

function createDatabaseBackup($db) {
    $backup = "-- Email Validator Database Backup\n";
    $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Get all tables
    $stmt = $db->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Get table structure
        $stmt = $db->prepare("SHOW CREATE TABLE `$table`");
        $stmt->execute();
        $row = $stmt->fetch();
        
        $backup .= "-- Table structure for table `$table`\n";
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup .= $row['Create Table'] . ";\n\n";
        
        // Get table data
        $stmt = $db->prepare("SELECT * FROM `$table`");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $backup .= "-- Dumping data for table `$table`\n";
            
            foreach ($rows as $row) {
                $values = array_map(function($value) use ($db) {
                    return $value === null ? 'NULL' : $db->quote($value);
                }, $row);
                
                $backup .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            $backup .= "\n";
        }
    }
    
    $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    return $backup;
}
?>