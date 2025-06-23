<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once '../config/config.php';
} catch (Exception $e) {
    die('Configuration error: ' . $e->getMessage() . '. Please check your config files.');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}

// Initialize database
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get system statistics
$stats = [];

try {
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch()['total'];

    // Active users
    $stmt = $db->prepare("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
    $stmt->execute();
    $stats['active_users'] = $stmt->fetch()['active'];

    // Total validations
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM email_validations");
    $stmt->execute();
    $stats['total_validations'] = $stmt->fetch()['total'];

    // Today's validations
    $stmt = $db->prepare("SELECT COUNT(*) as today FROM email_validations WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $stats['today_validations'] = $stmt->fetch()['today'];

    // Recent users
    $stmt = $db->prepare("
        SELECT username, email, created_at, last_login, is_active 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();

    // System activity
    $stmt = $db->prepare("
        SELECT al.action, al.details, al.created_at, u.username 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 15
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();

} catch (Exception $e) {
    // Set default values if queries fail
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'total_validations' => 0,
        'today_validations' => 0
    ];
    $recent_users = [];
    $recent_activity = [];
    error_log("Admin dashboard error: " . $e->getMessage());
}

// Helper function for time ago
function time_ago($datetime) {
    if (!$datetime) return 'Never';
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    return date('M j, Y', strtotime($datetime));
}

$page_title = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Email Validator</title>
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
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
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
            transition: transform 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .stats-card.secondary {
            border-left-color: var(--secondary-color);
        }
        
        .stats-card.success {
            border-left-color: #28a745;
        }
        
        .stats-card.danger {
            border-left-color: #dc3545;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .timeline-item {
            border-left: 2px solid #dee2e6;
            padding-left: 1rem;
            margin-left: 0.5rem;
        }
        
        .timeline-marker {
            position: relative;
            left: -1.5rem;
            top: 0.5rem;
        }
        
        .timeline-marker i {
            background: white;
            padding: 0.2rem;
            border-radius: 50%;
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
                        <a class="nav-link active" href="index.php">
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
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logs.php">
                            <i class="fas fa-list-alt"></i>
                            <span>Activity Logs</span>
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <h6 class="sidebar-heading text-muted px-3 mb-2">USER TOOLS</h6>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../user/dashboard.php">
                            <i class="fas fa-user-circle"></i>
                            <span>User View</span>
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
                    <h4 class="mb-0 text-muted"><?php echo $page_title; ?></h4>
                </div>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-ghost d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                            <div class="avatar me-2">
                                <i class="fas fa-user-circle fa-2x"></i>
                            </div>
                            <div class="text-start">
                                <div class="fw-medium"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                                <div class="small text-muted">Administrator</div>
                            </div>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">System Dashboard</h2>
                    <div>
                        <a href="users.php" class="btn btn-primary me-2">
                            <i class="fas fa-users me-2"></i>Manage Users
                        </a>
                        <a href="settings.php" class="btn btn-secondary">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </div>
                </div>

                <!-- System Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="stats-number"><?php echo number_format($stats['total_users']); ?></div>
                                        <div class="stats-label">Total Users</div>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-users fa-2x text-primary" style="opacity: 0.5;"></i>
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
                                        <div class="stats-number text-success"><?php echo number_format($stats['active_users']); ?></div>
                                        <div class="stats-label">Active Users</div>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-user-check fa-2x text-success" style="opacity: 0.5;"></i>
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
                                        <div class="stats-number text-info"><?php echo number_format($stats['total_validations']); ?></div>
                                        <div class="stats-label">Total Validations</div>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-envelope-check fa-2x text-info" style="opacity: 0.5;"></i>
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
                                        <div class="stats-number"><?php echo number_format($stats['today_validations']); ?></div>
                                        <div class="stats-label">Today's Validations</div>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-calendar-day fa-2x text-primary" style="opacity: 0.5;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Users -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Users</h5>
                                <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_users)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No users yet</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Status</th>
                                                    <th>Last Login</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_users as $user): ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                                <div class="small text-muted"><?php echo htmlspecialchars($user['email']); ?></div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-muted small">
                                                            <?php echo time_ago($user['last_login']); ?>
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

                    <!-- System Activity -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">System Activity</h5>
                                <a href="logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activity)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
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
                                                        <div class="text-muted small">
                                                            by <?php echo htmlspecialchars($activity['username'] ?? 'System'); ?>
                                                            <?php if ($activity['details']): ?>
                                                                <br><?php echo htmlspecialchars($activity['details']); ?>
                                                            <?php endif; ?>
                                                        </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
