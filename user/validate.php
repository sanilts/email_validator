<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/email_validator.php';

$database = new Database();
$db = $database->getConnection();
$validator = new EmailValidator($db);

$result = null;
$error = '';

if ($_POST && isset($_POST['email'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $email = sanitize_input($_POST['email']);
        
        if (!check_rate_limit($_SESSION['user_id'], 'email_validation')) {
            $error = 'Rate limit exceeded. Please try again later.';
        } else {
            try {
                $result = $validator->validate_email($email, $_SESSION['user_id']);
                log_activity($_SESSION['user_id'], 'email_validation', "Email: $email, Status: {$result['status']}");
            } catch (Exception $e) {
                $error = 'Validation failed. Please try again.';
                error_log("Validation error: " . $e->getMessage());
            }
        }
    }
}

// Get validation history
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM email_validations 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

$stmt = $db->prepare("
    SELECT * FROM email_validations 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$_SESSION['user_id'], $limit, $offset]);
$validations = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="row">
                <!-- Validation Form -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-envelope-check me-2"></i>Validate Email
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Enter email to validate" required
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-check me-2"></i>Validate Email
                                </button>
                            </form>

                            <?php if ($result): ?>
                                <div class="mt-4">
                                    <h6>Validation Result</h6>
                                    <div class="validation-result <?php echo $result['status']; ?>">
                                        <div class="d-flex align-items-center mb-2">
                                            <strong>Status: </strong>
                                            <span class="ms-2 badge bg-<?php echo $result['status'] === 'valid' ? 'success' : ($result['status'] === 'invalid' ? 'danger' : 'warning'); ?>">
                                                <?php echo strtoupper($result['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="row mb-2">
                                            <div class="col-4"><strong>Format:</strong></div>
                                            <div class="col-8">
                                                <i class="fas fa-<?php echo $result['format_valid'] ? 'check text-success' : 'times text-danger'; ?>"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-2">
                                            <div class="col-4"><strong>DNS:</strong></div>
                                            <div class="col-8">
                                                <i class="fas fa-<?php echo $result['dns_valid'] ? 'check text-success' : 'times text-danger'; ?>"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-2">
                                            <div class="col-4"><strong>SMTP:</strong></div>
                                            <div class="col-8">
                                                <i class="fas fa-<?php echo $result['smtp_valid'] ? 'check text-success' : 'times text-danger'; ?>"></i>
                                            </div>
                                        </div>
                                        
                                        <?php if ($result['risk_score'] > 0): ?>
                                            <div class="row mb-2">
                                                <div class="col-4"><strong>Risk Score:</strong></div>
                                                <div class="col-8"><?php echo $result['risk_score']; ?>/100</div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($result['is_disposable']): ?>
                                            <div class="small text-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Disposable email provider
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($result['is_role_based']): ?>
                                            <div class="small text-info">
                                                <i class="fas fa-info-circle me-1"></i>Role-based email
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($result['is_catch_all']): ?>
                                            <div class="small text-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Catch-all domain
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($result['details'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <?php echo implode(', ', $result['details']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Validation History -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Validation History
                            </h5>
                            <div>
                                <a href="../api/export.php?type=all" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="fas fa-download me-1"></i>Export All
                                </a>
                                <a href="../api/export.php?type=valid" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-download me-1"></i>Valid Only
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($validations)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No validation history yet</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Email</th>
                                                <th>Status</th>
                                                <th>Details</th>
                                                <th>Risk Score</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($validations as $validation): ?>
                                                <tr>
                                                    <td class="text-truncate" style="max-width: 200px;">
                                                        <?php echo htmlspecialchars($validation['email']); ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_class = 'secondary';
                                                        $icon = 'question';
                                                        switch($validation['status']) {
                                                            case 'valid':
                                                                $badge_class = 'success';
                                                                $icon = 'check';
                                                                break;
                                                            case 'invalid':
                                                                $badge_class = 'danger';
                                                                $icon = 'times';
                                                                break;
                                                            case 'risky':
                                                                $badge_class = 'warning';
                                                                $icon = 'exclamation-triangle';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                                            <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                                            <?php echo ucfirst($validation['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="small">
                                                            F: <?php echo $validation['format_valid'] ? '✓' : '✗'; ?> |
                                                            D: <?php echo $validation['dns_valid'] ? '✓' : '✗'; ?> |
                                                            S: <?php echo $validation['smtp_valid'] ? '✓' : '✗'; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($validation['risk_score'] > 0): ?>
                                                            <span class="badge bg-warning"><?php echo $validation['risk_score']; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?php echo date('M j, Y g:i A', strtotime($validation['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary btn-sm" 
                                                                    onclick="viewDetails(<?php echo htmlspecialchars(json_encode($validation)); ?>)"
                                                                    title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Validation history pagination">
                                        <ul class="pagination justify-content-center">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
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
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Validation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewDetails(validation) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const body = document.getElementById('modalBody');
    
    const details = JSON.parse(validation.validation_details || '[]');
    
    body.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Email Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Email:</strong></td><td>${validation.email}</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-${validation.status === 'valid' ? 'success' : validation.status === 'invalid' ? 'danger' : 'warning'}">${validation.status.toUpperCase()}</span></td></tr>
                    <tr><td><strong>Risk Score:</strong></td><td>${validation.risk_score}/100</td></tr>
                    <tr><td><strong>Validated:</strong></td><td>${new Date(validation.created_at).toLocaleString()}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Validation Checks</h6>
                <table class="table table-sm">
                    <tr><td><strong>Format Valid:</strong></td><td>${validation.format_valid ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td></tr>
                    <tr><td><strong>DNS Valid:</strong></td><td>${validation.dns_valid ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td></tr>
                    <tr><td><strong>SMTP Valid:</strong></td><td>${validation.smtp_valid ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td></tr>
                    <tr><td><strong>Disposable:</strong></td><td>${validation.is_disposable ? '<i class="fas fa-exclamation-triangle text-warning"></i>' : '<i class="fas fa-check text-success"></i>'}</td></tr>
                    <tr><td><strong>Role-based:</strong></td><td>${validation.is_role_based ? '<i class="fas fa-info-circle text-info"></i>' : '<i class="fas fa-check text-success"></i>'}</td></tr>
                    <tr><td><strong>Catch-all:</strong></td><td>${validation.is_catch_all ? '<i class="fas fa-exclamation-triangle text-warning"></i>' : '<i class="fas fa-check text-success"></i>'}</td></tr>
                </table>
            </div>
        </div>
        ${details.length > 0 ? `
        <div class="mt-3">
            <h6>Additional Details</h6>
            <ul class="list-unstyled">
                ${details.map(detail => `<li><i class="fas fa-info-circle text-info me-2"></i>${detail}</li>`).join('')}
            </ul>
        </div>
        ` : ''}
    `;
    
    modal.show();
}
</script>

<?php include '../templates/footer.php'; ?>