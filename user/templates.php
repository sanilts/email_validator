<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle template creation/update
if ($_POST && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $name = sanitize_input($_POST['name']);
        $subject = sanitize_input($_POST['subject']);
        $body = $_POST['body']; // Don't sanitize HTML content too aggressively
        $template_type = sanitize_input($_POST['template_type']);
        
        if (empty($name) || empty($subject) || empty($body)) {
            $error = 'All fields are required';
        } else {
            try {
                if ($_POST['action'] === 'create_template') {
                    $stmt = $db->prepare("
                        INSERT INTO email_templates (user_id, name, subject, body, template_type) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $name, $subject, $body, $template_type]);
                    
                    log_activity($_SESSION['user_id'], 'email_template_created', "Template: $name");
                    $success = 'Template created successfully';
                } elseif ($_POST['action'] === 'update_template') {
                    $template_id = (int)$_POST['template_id'];
                    $stmt = $db->prepare("
                        UPDATE email_templates 
                        SET name = ?, subject = ?, body = ?, template_type = ? 
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$name, $subject, $body, $template_type, $template_id, $_SESSION['user_id']]);
                    
                    log_activity($_SESSION['user_id'], 'email_template_updated', "Template: $name");
                    $success = 'Template updated successfully';
                }
            } catch (Exception $e) {
                $error = 'Failed to save template';
                error_log("Template save error: " . $e->getMessage());
            }
        }
    }
}

// Handle template deletion
if ($_GET && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $template_id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ? AND user_id = ?");
        $stmt->execute([$template_id, $_SESSION['user_id']]);
        
        log_activity($_SESSION['user_id'], 'email_template_deleted', "Template ID: $template_id");
        $success = 'Template deleted successfully';
    } catch (Exception $e) {
        $error = 'Failed to delete template';
    }
}

// Get editing template
$editing_template = null;
if (isset($_GET['edit'])) {
    $template_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ? AND user_id = ?");
    $stmt->execute([$template_id, $_SESSION['user_id']]);
    $editing_template = $stmt->fetch();
}

// Get user's templates
$stmt = $db->prepare("
    SELECT * FROM email_templates 
    WHERE user_id = ? 
    ORDER BY template_type, name
");
$stmt->execute([$_SESSION['user_id']]);
$templates = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="row">
                <!-- Template Form -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-file-alt me-2"></i>
                                <?php echo $editing_template ? 'Edit Template' : 'Create Template'; ?>
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

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="<?php echo $editing_template ? 'update_template' : 'create_template'; ?>">
                                <?php if ($editing_template): ?>
                                    <input type="hidden" name="template_id" value="<?php echo $editing_template['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Template Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($editing_template['name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="template_type" class="form-label">Template Type *</label>
                                    <select class="form-select" id="template_type" name="template_type" required>
                                        <option value="verification" <?php echo ($editing_template['template_type'] ?? '') === 'verification' ? 'selected' : ''; ?>>
                                            Email Verification
                                        </option>
                                        <option value="notification" <?php echo ($editing_template['template_type'] ?? '') === 'notification' ? 'selected' : ''; ?>>
                                            Notification
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject Line *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" 
                                           value="<?php echo htmlspecialchars($editing_template['subject'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="body" class="form-label">Template Body *</label>
                                    <textarea class="form-control" id="body" name="body" rows="10" required><?php echo htmlspecialchars($editing_template['body'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Available Variables:</h6>
                                    <div class="small text-muted">
                                        <code>{{email}}</code> - Recipient email address<br>
                                        <code>{{verification_code}}</code> - Verification code<br>
                                        <code>{{date}}</code> - Current date<br>
                                        <code>{{time}}</code> - Current time<br>
                                        <code>{{company_name}}</code> - Company name
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $editing_template ? 'Update Template' : 'Create Template'; ?>
                                    </button>
                                    <?php if ($editing_template): ?>
                                        <a href="templates.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Templates List -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>My Templates
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($templates)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No templates created yet</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                $grouped_templates = [];
                                foreach ($templates as $template) {
                                    $grouped_templates[$template['template_type']][] = $template;
                                }
                                ?>
                                
                                <?php foreach ($grouped_templates as $type => $type_templates): ?>
                                    <h6 class="text-uppercase text-muted mb-3"><?php echo ucfirst($type); ?> Templates</h6>
                                    
                                    <?php foreach ($type_templates as $template): ?>
                                        <div class="card border mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($template['name']); ?></h6>
                                                        <p class="small text-muted mb-2"><?php echo htmlspecialchars($template['subject']); ?></p>
                                                        <div class="small text-muted">
                                                            Created <?php echo time_ago($template['created_at']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <button class="dropdown-item" onclick="previewTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)">
                                                                <i class="fas fa-eye me-2"></i>Preview
                                                            </button>
                                                            <a class="dropdown-item" href="?edit=<?php echo $template['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Edit
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $template['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to delete this template?')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Subject:</strong>
                    <div id="previewSubject" class="mt-1"></div>
                </div>
                <div class="mb-3">
                    <strong>Body:</strong>
                    <div id="previewBody" class="mt-1 border p-3" style="white-space: pre-wrap;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function previewTemplate(template) {
    // Replace variables with sample data
    const sampleData = {
        '{{email}}': 'user@example.com',
        '{{verification_code}}': '123456',
        '{{date}}': new Date().toLocaleDateString(),
        '{{time}}': new Date().toLocaleTimeString(),
        '{{company_name}}': '<?php echo get_system_setting('company_name', APP_NAME); ?>'
    };
    
    let subject = template.subject;
    let body = template.body;
    
    for (const [variable, value] of Object.entries(sampleData)) {
        subject = subject.replace(new RegExp(variable.replace(/[.*+?^${}()|[\]\\]/g, '\\```'), 'g'), value);
        body = body.replace(new RegExp(variable.replace(/[.*+?^${}()|[\]\\]/g, '\\```'), 'g'), value);
    }
    
    document.getElementById('previewSubject').textContent = subject;
    document.getElementById('previewBody').textContent = body;
    
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
}
</script>

<?php include '../templates/footer.php'; ?>