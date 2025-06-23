<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    redirect('../user/dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle logo upload
if ($_POST && isset($_FILES['company_logo'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        try {
            require_once '../includes/security.php';
            $security = new SecurityManager($db);
            $file = $security->sanitize_file_upload($_FILES['company_logo']);
            
            // Additional checks for image files
            $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_image_types)) {
                throw new Exception('Please upload a valid image file (JPG, PNG, GIF)');
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = '../assets/uploads/logo/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['safe_name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Remove old logo
                $old_logo = get_system_setting('company_logo');
                if ($old_logo && file_exists('../' . $old_logo)) {
                    unlink('../' . $old_logo);
                }
                
                // Update setting
                $logo_path = 'assets/uploads/logo/' . $filename;
                set_system_setting('company_logo', $logo_path);
                
                log_activity($_SESSION['user_id'], 'company_logo_updated');
                $success = 'Company logo updated successfully';
            } else {
                $error = 'Failed to upload logo';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Handle logo removal
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'remove_logo') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $old_logo = get_system_setting('company_logo');
        if ($old_logo && file_exists('../' . $old_logo)) {
            unlink('../' . $old_logo);
        }
        
        set_system_setting('company_logo', '');
        log_activity($_SESSION['user_id'], 'company_logo_removed');
        $success = 'Company logo removed successfully';
    }
}

$current_logo = get_system_setting('company_logo');
$company_name = get_system_setting('company_name', APP_NAME);

$csrf_token = generate_csrf_token();
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Company Branding</h2>
            </div>

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

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Company Logo</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($current_logo): ?>
                                <div class="mb-4">
                                    <h6>Current Logo:</h6>
                                    <img src="<?php echo BASE_URL . $current_logo; ?>" 
                                         alt="Company Logo" 
                                         class="img-thumbnail" 
                                         style="max-height: 150px;">
                                    
                                    <form method="POST" class="d-inline ms-3">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="remove_logo">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Are you sure you want to remove the logo?')">
                                            <i class="fas fa-trash me-1"></i>Remove Logo
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="mb-3">
                                    <label for="company_logo" class="form-label">
                                        <?php echo $current_logo ? 'Replace Logo' : 'Upload Logo'; ?>
                                    </label>
                                    <input type="file" class="form-control" id="company_logo" name="company_logo" 
                                           accept="image/jpeg,image/png,image/gif" required>
                                    <div class="form-text">
                                        Supported formats: JPG, PNG, GIF. Maximum size: 2MB. 
                                        Recommended size: 200x60 pixels.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Upload Logo
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Preview</h5>
                        </div>
                        <div class="card-body">
                            <h6>Sidebar Preview:</h6>
                            <div class="bg-dark text-white p-3 rounded">
                                <div class="d-flex align-items-center">
                                    <?php if ($current_logo): ?>
                                        <img src="<?php echo BASE_URL . $current_logo; ?>" 
                                             alt="Logo" 
                                             class="me-2" 
                                             style="height: 40px;">
                                    <?php else: ?>
                                        <i class="fas fa-envelope-check fa-2x text-primary me-2"></i>
                                    <?php endif; ?>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($company_name); ?></h6>
                                </div>
                            </div>
                            
                            <h6 class="mt-4">Login Page Preview:</h6>
                            <div class="bg-light p-3 rounded text-center">
                                <?php if ($current_logo): ?>
                                    <img src="<?php echo BASE_URL . $current_logo; ?>" 
                                         alt="Logo" 
                                         class="mb-2" 
                                         style="height: 60px;">
                                <?php else: ?>
                                    <i class="fas fa-envelope-check fa-3x text-primary mb-2"></i>
                                <?php endif; ?>
                                <h5><?php echo htmlspecialchars($company_name); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>