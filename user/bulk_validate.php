<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle file upload
if ($_POST && isset($_FILES['csv_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload failed';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $error = 'File too large. Maximum size: ' . format_file_size(MAX_FILE_SIZE);
        } else {
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
                $error = 'Invalid file type. Only CSV and TXT files allowed.';
            } else {
                try {
                    // Create unique filename
                    $filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $filepath = UPLOAD_PATH . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Parse emails from file
                        $emails = [];
                        $handle = fopen($filepath, 'r');
                        
                        while (($line = fgets($handle)) !== false) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            
                            // Handle CSV format
                            if ($file_extension === 'csv') {
                                $data = str_getcsv($line);
                                foreach ($data as $email) {
                                    $email = trim($email);
                                    if (validate_email_format($email)) {
                                        $emails[] = $email;
                                    }
                                }
                            } else {
                                // Handle TXT format (one email per line)
                                if (validate_email_format($line)) {
                                    $emails[] = $line;
                                }
                            }
                        }
                        fclose($handle);
                        
                        // Remove duplicates
                        $emails = array_unique($emails);
                        
                        if (empty($emails)) {
                            $error = 'No valid emails found in the file';
                            unlink($filepath);
                        } elseif (count($emails) > MAX_BULK_EMAILS) {
                            $error = 'Too many emails. Maximum allowed: ' . number_format(MAX_BULK_EMAILS);
                            unlink($filepath);
                        } else {
                            // Create bulk job
                            $stmt = $db->prepare("
                                INSERT INTO bulk_jobs (user_id, filename, total_emails, status) 
                                VALUES (?, ?, ?, 'pending')
                            ");
                            $stmt->execute([$_SESSION['user_id'], $filename, count($emails)]);
                            $job_id = $db->lastInsertId();
                            
                            log_activity($_SESSION['user_id'], 'bulk_upload_started', "Job ID: $job_id, Emails: " . count($emails));
                            
                            $success = 'File uploaded successfully. ' . count($emails) . ' emails will be validated.';
                            
                            // Store emails in session for processing
                            $_SESSION['bulk_emails_' . $job_id] = $emails;
                            $_SESSION['current_job_id'] = $job_id;
                        }
                    } else {
                        $error = 'Failed to save uploaded file';
                    }
                } catch (Exception $e) {
                    $error = 'Error processing file: ' . $e->getMessage();
                    error_log("Bulk upload error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get job history
$stmt = $db->prepare("
    SELECT * FROM bulk_jobs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$jobs = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="row">
                <!-- Upload Form -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-upload me-2"></i>Bulk Email Validation
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $success; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data" id="bulkForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="upload-area mb-3" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <h5>Drop your CSV file here</h5>
                                    <p class="text-muted">or click to browse</p>
                                    <input type="file" class="d-none" id="csv_file" name="csv_file" 
                                           accept=".csv,.txt" required>
                                </div>

                                <div class="mb-3">
                                    <h6>File Requirements:</h6>
                                    <ul class="small text-muted">
                                        <li>CSV or TXT format</li>
                                        <li>Maximum <?php echo number_format(MAX_BULK_EMAILS); ?> emails</li>
                                        <li>Maximum file size: <?php echo format_file_size(MAX_FILE_SIZE); ?></li>
                                        <li>One email per line (TXT) or comma-separated (CSV)</li>
                                    </ul>
                                </div>

                                <button type="submit" class="btn btn-primary w-100" id="uploadBtn">
                                    <i class="fas fa-upload me-2"></i>Upload and Validate
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Current Job Progress -->
                    <?php if (isset($_SESSION['current_job_id'])): ?>
                        <div class="card mt-4" id="progressCard">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-tasks me-2"></i>Validation Progress
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Progress</span>
                                        <span id="progressText">0%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="small text-muted">Processed</div>
                                        <div class="fw-bold" id="processedCount">0</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-muted">Valid</div>
                                        <div class="fw-bold text-success" id="validCount">0</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-muted">Invalid</div>
                                        <div class="fw-bold text-danger" id="invalidCount">0</div>
                                    </div>
                                </div>
                                <button class="btn btn-danger btn-sm mt-3" id="cancelBtn">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Job History -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Validation History
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($jobs)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No bulk validations yet</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>File</th>
                                                <th>Status</th>
                                                <th>Progress</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($jobs as $job): ?>
                                                <tr>
                                                    <td class="text-truncate" style="max-width: 150px;">
                                                        <?php echo htmlspecialchars($job['filename']); ?>
                                                        <div class="small text-muted">
                                                            <?php echo number_format($job['total_emails']); ?> emails
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_class = 'secondary';
                                                        switch($job['status']) {
                                                            case 'completed':
                                                                $badge_class = 'success';
                                                                break;
                                                            case 'processing':
                                                                $badge_class = 'primary';
                                                                break;
                                                            case 'failed':
                                                                $badge_class = 'danger';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                                            <?php echo ucfirst($job['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $progress = $job['total_emails'] > 0 ? 
                                                            round(($job['processed_emails'] / $job['total_emails']) * 100) : 0;
                                                        ?>
                                                        <div class="small">
                                                            <?php echo $job['processed_emails']; ?>/<?php echo $job['total_emails']; ?>
                                                        </div>
                                                        <div class="progress progress-sm">
                                                            <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                        </div>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($job['status'] === 'completed'): ?>
                                                            <a href="../api/export.php?job_id=<?php echo $job['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary" title="Download Results">
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/bulk-upload.js"></script>

<?php include '../templates/footer.php'; ?>