<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

// Get user statistics
$stats = [];

// Total validations
$stmt = $db->prepare("SELECT COUNT(*) as total FROM email_validations WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['total'] = $stmt->fetch()['total'];

// Valid emails
$stmt = $db->prepare("SELECT COUNT(*) as valid FROM email_validations WHERE user_id = ? AND status = 'valid'");
$stmt->execute([$_SESSION['user_id']]);
$stats['valid'] = $stmt->fetch()['valid'];

// Invalid emails
$stmt = $db->prepare("SELECT COUNT(*) as invalid FROM email_validations WHERE user_id = ? AND status = 'invalid'");
$stmt->execute([$_SESSION['user_id']]);
$stats['invalid'] = $stmt->fetch()['invalid'];

// Success rate
$stats['success_rate'] = $stats['total'] > 0 ? round(($stats['valid'] / $stats['total']) * 100, 1) : 0;

// Recent validations
$stmt = $db->prepare("
    SELECT email, status, created_at 
    FROM email_validations 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_validations = $stmt->fetchAll();

// Recent activity
$stmt = $db->prepare("
    SELECT action, details, created_at 
    FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_activity = $stmt->fetchAll();

include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Dashboard</h2>
                <div>
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#quickValidateModal">
                        <i class="fas fa-envelope-check me-2"></i>Quick Validate
                    </button>
                    <a href="bulk_validate.php" class="btn btn-secondary">
                        <i class="fas fa-upload me-2"></i>Bulk Upload
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo number_format($stats['total']); ?></div>
                                    <div class="stats-label">Total Validations</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-envelope fa-2x text-primary opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stats-card success h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-success"><?php echo number_format($stats['valid']); ?></div>
                                    <div class="stats-label">Valid Emails</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stats-card danger h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-danger"><?php echo number_format($stats['invalid']); ?></div>
                                    <div class="stats-label">Invalid Emails</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-times-circle fa-2x text-danger opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stats-card secondary h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-info"><?php echo $stats['success_rate']; ?>%</div>
                                    <div class="stats-label">Success Rate</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-chart-line fa-2x text-info opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Validations -->
                <div class="col-lg-8 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Validations</h5>
                            <a href="validate.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_validations)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No validations yet. Start by validating your first email!</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickValidateModal">
                                        Validate Email
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Email</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_validations as $validation): ?>
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
                                                    <td class="text-muted">
                                                        <?php echo time_ago($validation['created_at']); ?>
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

                <!-- Recent Activity -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_activity)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="timeline-item mb-3">
                                            <div class="d-flex">
                                                <div class="timeline-marker me-3">
                                                    <i class="fas fa-circle text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-medium">
                                                        <?php echo htmlspecialchars($activity['action']); ?>
                                                    </div>
                                                    <?php if ($activity['details']): ?>
                                                        <div class="text-muted small">
                                                            <?php echo htmlspecialchars($activity['details']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="text-muted small">
                                                        <?php echo time_ago($activity['created_at']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Validate Modal -->
<div class="modal fade" id="quickValidateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Email Validation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickValidateForm">
                    <div class="mb-3">
                        <label for="quick_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="quick_email" name="email" required>
                    </div>
                    <div id="quick_result" class="mt-3" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="validateBtn">
                    <i class="fas fa-check me-2"></i>Validate
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('validateBtn').addEventListener('click', function() {
    const email = document.getElementById('quick_email').value;
    const resultDiv = document.getElementById('quick_result');
    const btn = this;
    
    if (!email) {
        alert('Please enter an email address');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Validating...';
    resultDiv.style.display = 'none';
    
    fetch('../api/validate_email.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({email: email})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const result = data.result;
            let statusClass = 'info';
            let statusIcon = 'question';
            
            switch(result.status) {
                case 'valid':
                    statusClass = 'success';
                    statusIcon = 'check-circle';
                    break;
                case 'invalid':
                    statusClass = 'danger';
                    statusIcon = 'times-circle';
                    break;
                case 'risky':
                    statusClass = 'warning';
                    statusIcon = 'exclamation-triangle';
                    break;
            }
            
            resultDiv.innerHTML = `
                <div class="alert alert-${statusClass}">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-${statusIcon} fa-2x me-3"></i>
                        <div>
                            <h6 class="mb-1">Status: ${result.status.toUpperCase()}</h6>
                            <div class="small">
                                Format: ${result.format_valid ? '✓' : '✗'} | 
                                DNS: ${result.dns_valid ? '✓' : '✗'} | 
                                SMTP: ${result.smtp_valid ? '✓' : '✗'}
                            </div>
                            ${result.risk_score > 0 ? `<div class="small">Risk Score: ${result.risk_score}/100</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
            resultDiv.style.display = 'block';
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            resultDiv.style.display = 'block';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger">An error occurred during validation</div>';
        resultDiv.style.display = 'block';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-2"></i>Validate';
    });
});
</script>

<?php include '../templates/footer.php'; ?>