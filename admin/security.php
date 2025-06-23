<?php
// ===================================================================
// ADMIN/SECURITY.PHP - Security Management Dashboard
// ===================================================================
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    redirect('../user/dashboard.php');
}

$database = new Database();
$db = $database->getConnection();
$security = new SecurityManager($db);

$error = '';
$success = '';

// Handle security actions
if ($_POST && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'block_ip':
                    $ip_address = sanitize_input($_POST['ip_address']);
                    $reason = sanitize_input($_POST['reason']);
                    
                    if (empty($ip_address)) {
                        $error = 'IP address is required';
                    } else {
                        $security->block_ip($ip_address, $reason);
                        $success = "IP address $ip_address has been blocked";
                        log_activity($_SESSION['user_id'], 'ip_blocked_manually', "IP: $ip_address, Reason: $reason");
                    }
                    break;
                    
                case 'unblock_ip':
                    $ip_address = sanitize_input($_POST['ip_address']);
                    $stmt = $db->prepare("DELETE FROM blocked_ips WHERE ip_address = ?");
                    $stmt->execute([$ip_address]);
                    $success = "IP address $ip_address has been unblocked";
                    log_activity($_SESSION['user_id'], 'ip_unblocked', "IP: $ip_address");
                    break;
                    
                case 'update_security_settings':
                    $max_login_attempts = (int)$_POST['max_login_attempts'];
                    $lockout_time = (int)$_POST['lockout_time'];
                    $session_timeout = (int)$_POST['session_timeout'];
                    $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
                    $password_min_length = (int)$_POST['password_min_length'];
                    
                    set_system_setting('max_login_attempts', $max_login_attempts);
                    set_system_setting('lockout_time', $lockout_time);
                    set_system_setting('session_timeout', $session_timeout);
                    set_system_setting('enable_2fa', $enable_2fa);
                    set_system_setting('password_min_length', $password_min_length);
                    
                    $success = 'Security settings updated successfully';
                    log_activity($_SESSION['user_id'], 'security_settings_updated');
                    break;
                    
                case 'clear_security_logs':
                    $days = (int)$_POST['days'];
                    $stmt = $db->prepare("DELETE FROM activity_logs WHERE action LIKE '%security%' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                    $stmt->execute([$days]);
                    $deleted = $stmt->rowCount();
                    $success = "Cleared $deleted security log entries";
                    log_activity($_SESSION['user_id'], 'security_logs_cleared', "Deleted: $deleted entries older than $days days");
                    break;
                    
                default:
                    $error = 'Invalid action';
            }
        } catch (Exception $e) {
            $error = 'Failed to perform action: ' . $e->getMessage();
            error_log("Security action error: " . $e->getMessage());
        }
    }
}

// Get security statistics
$security_stats = [];

try {
    // Failed login attempts (last 24 hours)
    $stmt = $db->prepare("
        SELECT COUNT(*) as failed_logins 
        FROM activity_logs 
        WHERE action = 'login_failed' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $security_stats['failed_logins_24h'] = $stmt->fetch()['failed_logins'];
    
    // Blocked IPs
    $stmt = $db->prepare("SELECT COUNT(*) as blocked_ips FROM blocked_ips");
    $stmt->execute();
    $security_stats['blocked_ips'] = $stmt->fetch()['blocked_ips'];
    
    // Suspicious activities (last 7 days)
    $stmt = $db->prepare("
        SELECT COUNT(*) as suspicious_activities 
        FROM activity_logs 
        WHERE action LIKE '%suspicious%' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $security_stats['suspicious_activities'] = $stmt->fetch()['suspicious_activities'];
    
    // Active sessions
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT user_id) as active_sessions 
        FROM activity_logs 
        WHERE action = 'user_login' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute();
    $security_stats['active_sessions'] = $stmt->fetch()['active_sessions'];
    
} catch (Exception $e) {
    error_log("Security stats error: " . $e->getMessage());
    $security_stats = [
        'failed_logins_24h' => 0,
        'blocked_ips' => 0,
        'suspicious_activities' => 0,
        'active_sessions' => 0
    ];
}

// Get blocked IPs
$stmt = $db->prepare("SELECT * FROM blocked_ips ORDER BY blocked_at DESC LIMIT 50");
$stmt->execute();
$blocked_ips = $stmt->fetchAll();

// Get recent security events
$stmt = $db->prepare("
    SELECT al.*, u.username 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    WHERE al.action IN ('login_failed', 'suspicious_activity_detected', 'ip_blocked', 'user_locked', 'password_changed')
    ORDER BY al.created_at DESC 
    LIMIT 100
");
$stmt->execute();
$security_events = $stmt->fetchAll();

// Get current security settings
$current_settings = [
    'max_login_attempts' => get_system_setting('max_login_attempts', 5),
    'lockout_time' => get_system_setting('lockout_time', 900),
    'session_timeout' => get_system_setting('session_timeout', 3600),
    'enable_2fa' => get_system_setting('enable_2fa', 0),
    'password_min_length' => get_system_setting('password_min_length', 6)
];

$csrf_token = generate_csrf_token();
$page_title = 'Security Management';
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Security Management</h2>
                <div>
                    <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#blockIpModal">
                        <i class="fas fa-ban me-2"></i>Block IP
                    </button>
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                        <i class="fas fa-broom me-2"></i>Clear Old Logs
                    </button>
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

            <!-- Security Overview -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-danger"><?php echo number_format($security_stats['failed_logins_24h']); ?></div>
                                    <div class="stats-label">Failed Logins (24h)</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-exclamation-triangle fa-2x text-danger opacity-50"></i>
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
                                    <div class="stats-number text-warning"><?php echo number_format($security_stats['blocked_ips']); ?></div>
                                    <div class="stats-label">Blocked IPs</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-ban fa-2x text-warning opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-warning"><?php echo number_format($security_stats['suspicious_activities']); ?></div>
                                    <div class="stats-label">Suspicious Activities (7d)</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-shield-alt fa-2x text-warning opacity-50"></i>
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
                                    <div class="stats-number text-success"><?php echo number_format($security_stats['active_sessions']); ?></div>
                                    <div class="stats-label">Active Sessions</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-users fa-2x text-success opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Security Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="update_security_settings">
                                
                                <div class="mb-3">
                                    <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                    <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                           value="<?php echo $current_settings['max_login_attempts']; ?>" min="3" max="10" required>
                                    <div class="form-text">Number of failed attempts before account lockout</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="lockout_time" class="form-label">Lockout Duration (minutes)</label>
                                    <input type="number" class="form-control" id="lockout_time" name="lockout_time" 
                                           value="<?php echo $current_settings['lockout_time'] / 60; ?>" min="5" max="1440" required>
                                    <div class="form-text">How long to lock accounts after max attempts</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                    <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                           value="<?php echo $current_settings['session_timeout'] / 60; ?>" min="15" max="480" required>
                                    <div class="form-text">Auto-logout inactive users after this time</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                    <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                           value="<?php echo $current_settings['password_min_length']; ?>" min="6" max="32" required>
                                    <div class="form-text">Minimum characters required for passwords</div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable_2fa" name="enable_2fa" 
                                               <?php echo $current_settings['enable_2fa'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_2fa">
                                            Enable Two-Factor Authentication
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Blocked IPs -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Blocked IP Addresses</h5>
                            <span class="badge bg-danger"><?php echo count($blocked_ips); ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($blocked_ips)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-shield-alt fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No blocked IPs</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>IP Address</th>
                                                <th>Reason</th>
                                                <th>Blocked At</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($blocked_ips as $blocked_ip): ?>
                                                <tr>
                                                    <td class="font-monospace"><?php echo htmlspecialchars($blocked_ip['ip_address']); ?></td>
                                                    <td class="text-truncate" style="max-width: 120px;" title="<?php echo htmlspecialchars($blocked_ip['reason']); ?>">
                                                        <?php echo htmlspecialchars($blocked_ip['reason'] ?: 'Automated block'); ?>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?php echo date('M j, g:i A', strtotime($blocked_ip['blocked_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="unblockIp('<?php echo htmlspecialchars($blocked_ip['ip_address']); ?>')"
                                                                title="Unblock">
                                                            <i class="fas fa-unlock"></i>
                                                        </button>
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

            <!-- Security Events -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Security Events</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($security_events)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No security events recorded</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>User</th>
                                                <th>Details</th>
                                                <th>IP Address</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($security_events as $event): ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                        $icon = 'info-circle';
                                                        $class = 'text-info';
                                                        switch($event['action']) {
                                                            case 'login_failed':
                                                                $icon = 'times-circle';
                                                                $class = 'text-danger';
                                                                break;
                                                            case 'suspicious_activity_detected':
                                                                $icon = 'exclamation-triangle';
                                                                $class = 'text-warning';
                                                                break;
                                                            case 'ip_blocked':
                                                                $icon = 'ban';
                                                                $class = 'text-danger';
                                                                break;
                                                            case 'password_changed':
                                                                $icon = 'key';
                                                                $class = 'text-success';
                                                                break;
                                                        }
                                                        ?>
                                                        <i class="fas fa-<?php echo $icon; ?> <?php echo $class; ?> me-2"></i>
                                                        <span class="small"><?php echo ucwords(str_replace('_', ' ', $event['action'])); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($event['username']): ?>
                                                            <?php echo htmlspecialchars($event['username']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Unknown</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($event['details']); ?>">
                                                        <?php echo htmlspecialchars($event['details'] ?: '-'); ?>
                                                    </td>
                                                    <td class="font-monospace small">
                                                        <?php echo htmlspecialchars($event['ip_address']); ?>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?>
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

<!-- Block IP Modal -->
<div class="modal fade" id="blockIpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Block IP Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="block_ip">
                    
                    <div class="mb-3">
                        <label for="ip_address" class="form-label">IP Address *</label>
                        <input type="text" class="form-control" id="ip_address" name="ip_address" 
                               pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required>
                        <div class="form-text">Enter a valid IPv4 address (e.g., 192.168.1.100)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <input type="text" class="form-control" id="reason" name="reason" 
                               placeholder="Manual block by administrator">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban me-2"></i>Block IP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Clear Security Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="clear_security_logs">
                    
                    <div class="mb-3">
                        <label for="days" class="form-label">Delete security logs older than</label>
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
                        This will permanently delete security log entries older than the selected period. This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-broom me-2"></i>Clear Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unblock IP form -->
<form method="POST" id="unblockForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="unblock_ip">
    <input type="hidden" name="ip_address" id="unblockIpAddress">
</form>

<script>
function unblockIp(ipAddress) {
    if (confirm(`Are you sure you want to unblock IP address ${ipAddress}?`)) {
        document.getElementById('unblockIpAddress').value = ipAddress;
        document.getElementById('unblockForm').submit();
    }
}

// Auto-refresh security events every 2 minutes
setInterval(() => {
    // Only refresh if there are no modals open
    if (!document.querySelector('.modal.show')) {
        location.reload();
    }
}, 120000);
</script>

<?php include '../templates/footer.php'; ?>