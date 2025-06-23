<?php
// ===================================================================
// FIXED ADMIN/LOGS.PHP - SQL Syntax Error Fix
// ===================================================================
?>
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

// Handle log cleanup
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'cleanup_logs') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $days = (int)($_POST['days'] ?? 90);
        $deleted = cleanup_old_logs($days);
        log_activity($_SESSION['user_id'], 'logs_cleaned', "Deleted: $deleted records older than $days days");
        $success = "Cleaned up $deleted old log entries";
    }
}

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($user_filter) {
    $where_conditions[] = "u.username LIKE ?";
    $params[] = "%$user_filter%";
}

if ($action_filter) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%$action_filter%";
}

if ($date_filter) {
    $where_conditions[] = "DATE(al.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    $where_clause
";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get logs - FIX: Use integers for LIMIT and OFFSET
$query = "
    SELECT al.*, u.username, u.role 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    $where_clause 
    ORDER BY al.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get action types for filter
$stmt = $db->prepare("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$stmt->execute();
$action_types = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
$page_title = 'Activity Logs';
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Activity Logs</h2>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                    <i class="fas fa-broom me-2"></i>Cleanup Logs
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

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <input type="text" class="form-control" name="user" 
                                   value="<?php echo htmlspecialchars($user_filter); ?>" 
                                   placeholder="Search by username">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Action</label>
                            <select class="form-select" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($action_types as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                            <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action['action']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" 
                                   value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                                <a href="logs.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Activity Logs 
                        <span class="badge bg-secondary"><?php echo number_format($total_records); ?> total</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No activity logs found</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="text-nowrap">
                                                <?php echo date('M j, Y', strtotime($log['created_at'])); ?><br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($log['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['username']): ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                                        <span class="badge bg-<?php echo $log['role'] === 'admin' ? 'primary' : 'secondary'; ?> small">
                                                            <?php echo ucfirst($log['role']); ?>
                                                        </span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($log['action']); ?></code>
                                            </td>
                                            <td class="text-truncate" style="max-width: 200px;" 
                                                title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                                            </td>
                                            <td class="text-muted small">
                                                <?php echo htmlspecialchars($log['ip_address']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Logs pagination" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cleanup Old Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="cleanup_logs">
                    
                    <div class="mb-3">
                        <label for="days" class="form-label">Delete logs older than</label>
                        <select class="form-select" id="days" name="days" required>
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                            <option value="90" selected>90 days</option>
                            <option value="180">180 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. Old activity logs will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-broom me-2"></i>Cleanup Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>