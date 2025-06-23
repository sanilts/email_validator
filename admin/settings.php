<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

try {
    require_once '../config/config.php';
} catch (Exception $e) {
    die('Configuration error: ' . $e->getMessage());
}

// Initialize database
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Define helper functions if not already defined
if (!function_exists('get_system_setting')) {
    function get_system_setting($key, $default = null) {
        global $db;
        try {
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting($key, $value, $description = null) {
        global $db;
        try {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?, description = COALESCE(?, description)
            ");
            $stmt->execute([$key, $value, $description, $value, $description]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $details = null) {
        global $db;
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

$error = '';
$success = '';

// Handle settings update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        try {
            $settings = [
                'cache_duration_months' => (int)($_POST['cache_duration_months'] ?? 3),
                'max_bulk_emails' => (int)($_POST['max_bulk_emails'] ?? 10000),
                'rate_limit_per_hour' => (int)($_POST['rate_limit_per_hour'] ?? 1000),
                'company_name' => trim($_POST['company_name'] ?? 'Email Validator')
            ];
            
            foreach ($settings as $key => $value) {
                set_system_setting($key, $value);
            }
            
            log_activity($_SESSION['user_id'], 'system_settings_updated');
            $success = 'Settings updated successfully';
        } catch (Exception $e) {
            $error = 'Failed to update settings: ' . $e->getMessage();
            error_log("Settings update error: " . $e->getMessage());
        }
    }
}

// Get current settings with defaults
$current_settings = [
    'cache_duration_months' => get_system_setting('cache_duration_months', 3),
    'max_bulk_emails' => get_system_setting('max_bulk_emails', 10000),
    'rate_limit_per_hour' => get_system_setting('rate_limit_per_hour', 1000),
    'company_name' => get_system_setting('company_name', 'Email Validator'),
    'company_logo' => get_system_setting('company_logo', '')
];

// Get system statistics
$stats = [];
try {
    // Database size
    $stmt = $db->prepare("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $stmt->execute();
    $stats['db_size'] = $stmt->fetch()['db_size_mb'] ?? 0;

    // Cache statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_cached,
            COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as valid_cached,
            COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_cached
        FROM email_validations
    ");
    $stmt->execute();
    $cache_stats = $stmt->fetch();
    $stats = array_merge($stats, $cache_stats);
} catch (Exception $e) {
    $stats = [
        'db_size' => 0,
        'total_cached' => 0,
        'valid_cached' => 0,
        'expired_cached' => 0
    ];
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Email Validator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #659833;
            --secondary-color: #32679B;
            --dark-color: #0F101D;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--dark-color) 0%, #1a1b2e 100%);
            color: white;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-nav .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            background-color: #f8f9fa;
        }
        
        .topbar {
            background: white;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .content-area {
            padding: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #558229;
            border-color: #558229;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-envelope-check fa-2x text-primary me-2"></i>
                    <h4 class="mb-0">Email Validator</h4>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="topbar d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0 text-muted">System Settings</h4>
                </div>
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-2" onclick="clearCache()">
                        <i class="fas fa-broom me-2"></i>Clear Cache
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-ghost d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle fa-2x me-2"></i>
                            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- System Information -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">System Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>PHP Version:</strong></td>
                                        <td><?php echo PHP_VERSION; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Database Size:</strong></td>
                                        <td><?php echo $stats['db_size']; ?> MB</td>
                                    </tr>
                                    <tr>
                                        <td><strong>App Version:</strong></td>
                                        <td>1.0.0</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Server Time:</strong></td>
                                        <td><?php echo date('Y-m-d H:i:s'); ?></td>
                                    </tr>
                                </table>
                                
                                <h6 class="mt-4">Cache Statistics</h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="h6 mb-0"><?php echo number_format($stats['total_cached']); ?></div>
                                        <div class="small text-muted">Total</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="h6 mb-0 text-success"><?php echo number_format($stats['valid_cached']); ?></div>
                                        <div class="small text-muted">Valid</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="h6 mb-0 text-danger"><?php echo number_format($stats['expired_cached']); ?></div>
                                        <div class="small text-muted">Expired</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Form -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Application Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="company_name" class="form-label">Company Name</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                                   value="<?php echo htmlspecialchars($current_settings['company_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="cache_duration_months" class="form-label">Cache Duration (Months)</label>
                                            <input type="number" class="form-control" id="cache_duration_months" name="cache_duration_months" 
                                                   value="<?php echo htmlspecialchars($current_settings['cache_duration_months']); ?>" min="1" max="12" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="max_bulk_emails" class="form-label">Max Bulk Emails</label>
                                            <input type="number" class="form-control" id="max_bulk_emails" name="max_bulk_emails" 
                                                   value="<?php echo htmlspecialchars($current_settings['max_bulk_emails']); ?>" min="100" max="50000" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="rate_limit_per_hour" class="form-label">Rate Limit (Per Hour)</label>
                                            <input type="number" class="form-control" id="rate_limit_per_hour" name="rate_limit_per_hour" 
                                                   value="<?php echo htmlspecialchars($current_settings['rate_limit_per_hour']); ?>" min="100" max="10000" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Admin Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $stmt = $db->prepare("
                                SELECT al.*, u.username 
                                FROM activity_logs al 
                                JOIN users u ON al.user_id = u.id 
                                WHERE u.role = 'admin' 
                                ORDER BY al.created_at DESC 
                                LIMIT 20
                            ");
                            $stmt->execute();
                            $admin_activities = $stmt->fetchAll();
                        } catch (Exception $e) {
                            $admin_activities = [];
                        }
                        ?>
                        
                        <?php if (empty($admin_activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No recent admin activity</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Admin</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($admin_activities as $activity): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                                <td class="text-truncate" style="max-width: 200px;">
                                                    <?php echo htmlspecialchars($activity['details'] ?? '-'); ?>
                                                </td>
                                                <td class="text-muted small">
                                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearCache() {
            if (confirm('Are you sure you want to clear all expired cache entries?')) {
                fetch('../api/maintenance.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'clear_cache'})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cache cleared successfully');
                        location.reload();
                    } else {
                        alert('Failed to clear cache: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error clearing cache');
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>