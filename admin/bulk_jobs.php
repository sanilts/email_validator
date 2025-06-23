<?php
// ===================================================================
// ADMIN/BULK_JOBS.PHP - System-wide Bulk Jobs Management
// ===================================================================
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

// Handle job actions
if ($_POST && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'];
        $job_id = (int)($_POST['job_id'] ?? 0);
        
        try {
            switch ($action) {
                case 'cancel_job':
                    $stmt = $db->prepare("UPDATE bulk_jobs SET status = 'failed' WHERE id = ? AND status = 'processing'");
                    $stmt->execute([$job_id]);
                    $success = 'Job cancelled successfully';
                    log_activity($_SESSION['user_id'], 'bulk_job_cancelled', "Job ID: $job_id");
                    break;
                    
                case 'restart_job':
                    $stmt = $db->prepare("UPDATE bulk_jobs SET status = 'pending', processed_emails = 0, valid_emails = 0, invalid_emails = 0 WHERE id = ?");
                    $stmt->execute([$job_id]);
                    $success = 'Job restarted successfully';
                    log_activity($_SESSION['user_id'], 'bulk_job_restarted', "Job ID: $job_id");
                    break;
                    
                case 'delete_job':
                    $stmt = $db->prepare("DELETE FROM bulk_jobs WHERE id = ?");
                    $stmt->execute([$job_id]);
                    $success = 'Job deleted successfully';
                    log_activity($_SESSION['user_id'], 'bulk_job_deleted', "Job ID: $job_id");
                    break;
                    
                case 'cleanup_old':
                    $days = (int)($_POST['days'] ?? 30);
                    $stmt = $db->prepare("DELETE FROM bulk_jobs WHERE status IN ('completed', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                    $stmt->execute([$days]);
                    $deleted = $stmt->rowCount();
                    $success = "Cleaned up $deleted old bulk jobs";
                    log_activity($_SESSION['user_id'], 'bulk_jobs_cleanup', "Deleted: $deleted jobs older than $days days");
                    break;
                    
                default:
                    $error = 'Invalid action';
            }
        } catch (Exception $e) {
            $error = 'Failed to perform action: ' . $e->getMessage();
            error_log("Bulk job action error: " . $e->getMessage());
        }
    }
}

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

// Filters
$user_filter = $_GET['user'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

if ($user_filter) {
    $where_conditions[] = "u.username LIKE ?";
    $params[] = "%$user_filter%";
}

if ($status_filter) {
    $where_conditions[] = "bj.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(bj.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM bulk_jobs bj 
    LEFT JOIN users u ON bj.user_id = u.id 
    $where_clause
";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get bulk jobs
$query = "
    SELECT bj.*, u.username, u.email, u.role 
    FROM bulk_jobs bj 
    LEFT JOIN users u ON bj.user_id = u.id 
    $where_clause 
    ORDER BY bj.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Get summary statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_jobs,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_jobs,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_jobs,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_jobs,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_jobs,
        SUM(total_emails) as total_emails_processed,
        SUM(valid_emails) as total_valid_emails,
        SUM(invalid_emails) as total_invalid_emails
    FROM bulk_jobs
");
$stmt->execute();
$stats = $stmt->fetch();

// Get status options for filter
$stmt = $db->prepare("SELECT DISTINCT status FROM bulk_jobs ORDER BY status");
$stmt->execute();
$status_options = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
$page_title = 'Bulk Jobs Management';
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Bulk Jobs Management</h2>
                <div>
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                        <i class="fas fa-broom me-2"></i>Cleanup Old Jobs
                    </button>
                    <a href="../api/export.php?type=bulk_jobs&admin=1" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Export Jobs Report
                    </a>
                </div>
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

            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo number_format($stats['total_jobs']); ?></div>
                                    <div class="stats-label">Total Jobs</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-tasks fa-2x text-primary opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-warning"><?php echo number_format($stats['processing_jobs']); ?></div>
                                    <div class="stats-label">Processing</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-spinner fa-2x text-warning opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card success h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-success"><?php echo number_format($stats['completed_jobs']); ?></div>
                                    <div class="stats-label">Completed</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card danger h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-danger"><?php echo number_format($stats['failed_jobs']); ?></div>
                                    <div class="stats-label">Failed</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-times-circle fa-2x text-danger opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card secondary h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-info"><?php echo number_format($stats['total_emails_processed']); ?></div>
                                    <div class="stats-label">Total Emails</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-envelope fa-2x text-info opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <?php 
                                    $success_rate = $stats['total_emails_processed'] > 0 ? 
                                        round(($stats['total_valid_emails'] / $stats['total_emails_processed']) * 100, 1) : 0;
                                    ?>
                                    <div class="stats-number"><?php echo $success_rate; ?>%</div>
                                    <div class="stats-label">Success Rate</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-chart-line fa-2x text-primary opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <input type="text" class="form-control" name="user" 
                                   value="<?php echo htmlspecialchars($user_filter); ?>" 
                                   placeholder="Username">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <?php foreach ($status_options as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status['status']); ?>" 
                                            <?php echo $status_filter === $status['status'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($status['status']); ?>
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
                                <a href="bulk_jobs.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Jobs Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Bulk Validation Jobs 
                        <span class="badge bg-secondary"><?php echo number_format($total_records); ?> total</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($jobs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No bulk validation jobs found</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Job ID</th>
                                        <th>User</th>
                                        <th>Filename</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th>Results</th>
                                        <th>Success Rate</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jobs as $job): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo $job['id']; ?></strong>
                                                <div class="small text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($job['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($job['username']): ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($job['username']); ?></strong>
                                                        <span class="badge bg-<?php echo $job['role'] === 'admin' ? 'primary' : 'secondary'; ?> small">
                                                            <?php echo ucfirst($job['role']); ?>
                                                        </span>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($job['email']); ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Unknown User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($job['filename']); ?>">
                                                <i class="fas fa-file-csv text-primary me-1"></i>
                                                <?php echo htmlspecialchars($job['filename']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badge_class = 'secondary';
                                                $icon = 'question';
                                                switch($job['status']) {
                                                    case 'pending':
                                                        $badge_class = 'secondary';
                                                        $icon = 'clock';
                                                        break;
                                                    case 'processing':
                                                        $badge_class = 'primary';
                                                        $icon = 'spinner fa-spin';
                                                        break;
                                                    case 'completed':
                                                        $badge_class = 'success';
                                                        $icon = 'check';
                                                        break;
                                                    case 'failed':
                                                        $badge_class = 'danger';
                                                        $icon = 'times';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                                    <?php echo ucfirst($job['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $progress = $job['total_emails'] > 0 ? 
                                                    round(($job['processed_emails'] / $job['total_emails']) * 100) : 0;
                                                ?>
                                                <div class="small mb-1">
                                                    <?php echo number_format($job['processed_emails']); ?>/<?php echo number_format($job['total_emails']); ?>
                                                </div>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                                <div class="small text-muted"><?php echo $progress; ?>%</div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <span class="text-success">✓ <?php echo number_format($job['valid_emails']); ?></span><br>
                                                    <span class="text-danger">✗ <?php echo number_format($job['invalid_emails']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $total_processed = $job['valid_emails'] + $job['invalid_emails'];
                                                $success_rate = $total_processed > 0 ? 
                                                    round(($job['valid_emails'] / $total_processed) * 100, 1) : 0;
                                                ?>
                                                <span class="badge bg-<?php echo $success_rate >= 80 ? 'success' : ($success_rate >= 60 ? 'warning' : 'danger'); ?>">
                                                    <?php echo $success_rate; ?>%
                                                </span>
                                            </td>
                                            <td class="text-muted small">
                                                <?php 
                                                if ($job['started_at']) {
                                                    $end_time = $job['completed_at'] ?: date('Y-m-d H:i:s');
                                                    $duration = strtotime($end_time) - strtotime($job['started_at']);
                                                    if ($duration > 3600) {
                                                        echo gmdate('H:i:s', $duration);
                                                    } else {
                                                        echo gmdate('i:s', $duration);
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <?php if ($job['status'] === 'completed'): ?>
                                                            <a class="dropdown-item" href="../api/export.php?job_id=<?php echo $job['id']; ?>">
                                                                <i class="fas fa-download me-2"></i>Download Results
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($job['status'] === 'processing'): ?>
                                                            <button class="dropdown-item text-warning" onclick="performJobAction('cancel_job', <?php echo $job['id']; ?>)">
                                                                <i class="fas fa-stop me-2"></i>Cancel Job
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($job['status'], ['failed', 'completed'])): ?>
                                                            <button class="dropdown-item" onclick="performJobAction('restart_job', <?php echo $job['id']; ?>)">
                                                                <i class="fas fa-redo me-2"></i>Restart Job
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button class="dropdown-item text-danger" onclick="performJobAction('delete_job', <?php echo $job['id']; ?>)">
                                                            <i class="fas fa-trash me-2"></i>Delete Job
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Jobs pagination" class="mt-3">
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
                                    <?php endforeach; ?>
                                    
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
                    <h5 class="modal-title">Cleanup Old Bulk Jobs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="cleanup_old">
                    
                    <div class="mb-3">
                        <label for="days" class="form-label">Delete jobs older than</label>
                        <select class="form-select" id="days" name="days" required>
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="60">60 days</option>
                            <option value="90">90 days</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This will permanently delete completed and failed bulk jobs older than the selected period. This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-broom me-2"></i>Cleanup Jobs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Action form for individual job actions -->
<form method="POST" id="jobActionForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" id="jobAction">
    <input type="hidden" name="job_id" id="jobId">
</form>

<script>
function performJobAction(action, jobId) {
    let confirmMessage = '';
    
    switch(action) {
        case 'cancel_job':
            confirmMessage = 'Are you sure you want to cancel this job?';
            break;
        case 'restart_job':
            confirmMessage = 'Are you sure you want to restart this job? This will reset all progress.';
            break;
        case 'delete_job':
            confirmMessage = 'Are you sure you want to delete this job? This action cannot be undone.';
            break;
    }
    
    if (confirm(confirmMessage)) {
        document.getElementById('jobAction').value = action;
        document.getElementById('jobId').value = jobId;
        document.getElementById('jobActionForm').submit();
    }
}

// Auto-refresh for processing jobs
document.addEventListener('DOMContentLoaded', function() {
    const processingJobs = document.querySelectorAll('.badge.bg-primary');
    if (processingJobs.length > 0) {
        // Refresh page every 30 seconds if there are processing jobs
        setTimeout(() => {
            location.reload();
        }, 30000);
    }
});
</script>

<?php include '../templates/footer.php'; ?>