<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle list creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_list') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $name = sanitize_input($_POST['name']);
        $description = sanitize_input($_POST['description']);
        
        if (empty($name)) {
            $error = 'List name is required';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO email_lists (user_id, name, description) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $name, $description]);
                
                log_activity($_SESSION['user_id'], 'email_list_created', "List: $name");
                $success = 'Email list created successfully';
            } catch (Exception $e) {
                $error = 'Failed to create list';
                error_log("List creation error: " . $e->getMessage());
            }
        }
    }
}

// Handle list deletion
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_list') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $list_id = (int)$_POST['list_id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM email_lists WHERE id = ? AND user_id = ?");
            $stmt->execute([$list_id, $_SESSION['user_id']]);
            
            log_activity($_SESSION['user_id'], 'email_list_deleted', "List ID: $list_id");
            $success = 'Email list deleted successfully';
        } catch (Exception $e) {
            $error = 'Failed to delete list';
            error_log("List deletion error: " . $e->getMessage());
        }
    }
}

// Get user's email lists with counts
$stmt = $db->prepare("
    SELECT 
        el.*,
        COUNT(eli.id) as email_count,
        SUM(CASE WHEN ev.status = 'valid' THEN 1 ELSE 0 END) as valid_count,
        SUM(CASE WHEN ev.status = 'invalid' THEN 1 ELSE 0 END) as invalid_count
    FROM email_lists el
    LEFT JOIN email_list_items eli ON el.id = eli.list_id
    LEFT JOIN email_validations ev ON eli.validation_id = ev.id
    WHERE el.user_id = ?
    GROUP BY el.id
    ORDER BY el.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$lists = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Email Lists</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createListModal">
                    <i class="fas fa-plus me-2"></i>Create List
                </button>
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

            <?php if (empty($lists)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-list fa-4x text-muted mb-4"></i>
                        <h4>No Email Lists Yet</h4>
                        <p class="text-muted mb-4">Organize your validated emails into lists for better management</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createListModal">
                            <i class="fas fa-plus me-2"></i>Create Your First List
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($lists as $list): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($list['name']); ?></h6>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="lists.php?view=<?php echo $list['id']; ?>">
                                                <i class="fas fa-eye me-2"></i>View Emails
                                            </a>
                                            <a class="dropdown-item" href="../api/export.php?list_id=<?php echo $list['id']; ?>">
                                                <i class="fas fa-download me-2"></i>Export
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item text-danger" onclick="deleteList(<?php echo $list['id']; ?>, '<?php echo htmlspecialchars($list['name']); ?>')">
                                                <i class="fas fa-trash me-2"></i>Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if ($list['description']): ?>
                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($list['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="h5 mb-0"><?php echo number_format($list['email_count']); ?></div>
                                            <div class="small text-muted">Total</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="h5 mb-0 text-success"><?php echo number_format($list['valid_count']); ?></div>
                                            <div class="small text-muted">Valid</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="h5 mb-0 text-danger"><?php echo number_format($list['invalid_count']); ?></div>
                                            <div class="small text-muted">Invalid</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Success Rate</div>
                                        <?php 
                                        $success_rate = $list['email_count'] > 0 ? 
                                            round(($list['valid_count'] / $list['email_count']) * 100, 1) : 0;
                                        ?>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: <?php echo $success_rate; ?>%"></div>
                                        </div>
                                        <div class="text-end small text-muted"><?php echo $success_rate; ?>%</div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <small class="text-muted">
                                        Created <?php echo time_ago($list['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create List Modal -->
<div class="modal fade" id="createListModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Email List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create_list">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">List Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create List</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirmation form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete_list">
    <input type="hidden" name="list_id" id="deleteListId">
</form>

<script>
function deleteList(listId, listName) {
    if (confirm(`Are you sure you want to delete the list "${listName}"? This action cannot be undone.`)) {
        document.getElementById('deleteListId').value = listId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include '../templates/footer.php'; ?>