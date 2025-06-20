<?php
// =============================================================================
// BULK UPLOAD API (api/bulk-upload.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    if (!isset($_FILES['csv_file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error');
    }
    
    $listId = $_POST['list_id'] ?? null;
    $templateId = $_POST['template_id'] ?? null;
    
    // Handle new list creation
    if ($listId === 'new' || (!$listId && !empty($_POST['new_list_name']))) {
        $newListName = $_POST['new_list_name'] ?? '';
        $newListDescription = $_POST['new_list_description'] ?? '';
        
        if (empty($newListName)) {
            throw new Exception('New list name is required');
        }
        
        // Check if list name already exists for this user
        $stmt = $db->prepare("SELECT id FROM email_lists WHERE user_id = ? AND list_name = ? AND is_active = 1");
        $stmt->execute([$user_id, $newListName]);
        if ($stmt->fetch()) {
            throw new Exception('List name already exists');
        }
        
        // Create new list
        $stmt = $db->prepare("
            INSERT INTO email_lists (list_name, description, user_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$newListName, $newListDescription, $user_id]);
        $listId = $db->lastInsertId();
    }
    
    if (!$listId) {
        throw new Exception('List ID is required for bulk upload');
    }
    
    // Verify list ownership
    $stmt = $db->prepare("SELECT user_id FROM email_lists WHERE id = ? AND is_active = 1");
    $stmt->execute([$listId]);
    $listOwner = $stmt->fetchColumn();
    
    if (!$listOwner || $listOwner != $user_id) {
        throw new Exception('Invalid list or access denied');
    }
    
    // Read file content
    $content = file_get_contents($file['tmp_name']);
    
    // Parse CSV content
    $lines = array_filter(array_map('trim', explode("\n", $content)));
    $emails = [];
    
    foreach ($lines as $line) {
        // Handle both comma-separated and line-separated emails
        if (strpos($line, ',') !== false) {
            $lineEmails = array_map('trim', explode(',', $line));
        } else {
            $lineEmails = [$line];
        }
        
        foreach ($lineEmails as $email) {
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
    }
    
    // Remove duplicates
    $emails = array_unique($emails);
    
    if (empty($emails)) {
        throw new Exception('No valid emails found in file');
    }
    
    // Check system limit
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_bulk_emails'");
    $stmt->execute();
    $maxEmails = (int)$stmt->fetchColumn();
    
    if (count($emails) > $maxEmails) {
        throw new Exception("Maximum $maxEmails emails allowed per upload");
    }
    
    // Create batch
    $batchId = uniqid('batch_');
    $stmt = $db->prepare("
        INSERT INTO email_batches (batch_id, user_id, filename, total_emails, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$batchId, $user_id, $file['name'], count($emails)]);
    
    // Store emails for processing
    $stmt = $db->prepare("
        INSERT INTO email_validations (email, user_id, batch_id, list_id, template_id, validation_date) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $assignmentStmt = $db->prepare("
        INSERT INTO list_email_assignments (list_id, validation_id) 
        VALUES (?, ?)
    ");
    
    foreach ($emails as $email) {
        $stmt->execute([$email, $user_id, $batchId, $listId, $templateId]);
        $validationId = $db->lastInsertId();
        
        // Assign to list
        $assignmentStmt->execute([$listId, $validationId]);
    }
    
    // Update list statistics
    $updateStmt = $db->prepare("
        UPDATE email_lists SET 
            total_emails = total_emails + ?
        WHERE id = ?
    ");
    $updateStmt->execute([count($emails), $listId]);
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, 'bulk_upload', ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        json_encode(['batch_id' => $batchId, 'email_count' => count($emails)]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    echo json_encode([
        'success' => true,
        'batch_id' => $batchId,
        'email_count' => count($emails),
        'message' => 'File uploaded successfully. Validation will begin shortly.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>