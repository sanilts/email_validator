<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
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

$error = '';
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

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

function time_ago($datetime) {
    if (!$datetime) return 'Never';
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    return date('M j, Y', strtotime($datetime));
}

// Handle user creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $role = sanitize_input($_POST['role']);
        
        if (empty($username) || empty($email) || empty($password)) {
            $error = 'All fields are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            try {
                // Check if username/email exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        INSERT INTO users (username, email, password, role) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $email, $hashed_password, $role]);
                    
                    log_activity($_SESSION['user_id'], 'user_created', "Username: $username, Role: $role");
                    $success = 'User created successfully';
                }
            } catch (Exception $e) {
                $error = 'Failed to create user: ' . $e->getMessage();
                error_log("User creation error: " . $e->getMessage());
            }
        }
    }
}

// Handle user status toggle
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $user_id = (int)$_POST['user_id'];
        $new_status = (int)$_POST['new_status'];
        
        try {
            $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND id != ?");
            $stmt->execute([$new_status, $user_id, $_SESSION['user_id']]); // Can't deactivate self
            
            $action = $new_status ? 'user_activated' : 'user_deactivated';
            log_activity($_SESSION['user_id'], $action, "User ID: $user_id");
            $success = 'User status updated successfully';
        } catch (Exception $e) {
            $error = 'Failed to update user status: ' . $e->getMessage();
        }
    }
}

// Get users with statistics
try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            COALESCE(COUNT(ev.id), 0) as total_validations,
            COALESCE(SUM(CASE WHEN ev.status = 'valid' THEN 1 ELSE 0 END), 0) as valid_validations,
            COALESCE(SUM(CASE WHEN DATE(ev.created_at) = CURDATE() THEN 1 ELSE 0 END), 0) as today_validations
        FROM users u
        LEFT JOIN email_validations ev ON u.id = ev.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
    $error = 'Failed to load users: ' . $e->getMessage();
}

$page_title = 'User Management';
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
        
        .progress-sm {
            height: 4px;
        }
        
        .badge {
            font-weight: 500;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
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
                        <a class="nav-link active" href="users.php">
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
                    <h2 class="mb-0">User Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-user-plus me-2"></i>Create User
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Users</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-4x text-muted mb-4"></i>
                                <h4>No Users Found</h4>
                                <p class="text-muted mb-4">Create your first user to get started</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                    <i class="fas fa-user-plus me-2"></i>Create First User
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Validations</th>
                                            <th>Success Rate</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($user['email']); ?></div>
                                                        <div class="small text-muted">Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo number_format($user['total_validations']); ?></strong> total
                                                        <?php if ($user['today_validations'] > 0): ?>
                                                            <br><span class="text-success"><?php echo number_format($user['today_validations']); ?> today</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $success_rate = $user['total_validations'] > 0 ? 
                                                        round(($user['valid_validations'] / $user['total_validations']) * 100, 1) : 0;
                                                    ?>
                                                    <div class="small">
                                                        <?php echo $success_rate; ?>%
                                                        <div class="progress progress-sm mt-1">
                                                            <div class="progress-bar bg-success" style="width: <?php echo $success_rate; ?>%"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-muted small">
                                                    <?php echo time_ago($user['last_login']); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <button class="btn btn-outline-secondary" 
                                                                    onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)"
                                                                    title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                                <i class="fas fa-<?php echo $user['is_active'] ? 'user-slash' : 'user-check'; ?>"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-outline-primary" 
                                                                onclick="viewUserDetails(<?php echo htmlspecialchars(json_encode($user)); ?>)"
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status toggle form -->
    <form method="POST" id="statusForm" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" id="statusUserId">
        <input type="hidden" name="new_status" id="statusNewStatus">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleUserStatus(userId, newStatus) {
            const action = newStatus ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                document.getElementById('statusUserId').value = userId;
                document.getElementById('statusNewStatus').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }

        function viewUserDetails(user) {
            const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
            const content = document.getElementById('userDetailsContent');
            
            const successRate = user.total_validations > 0 ? 
                Math.round((user.valid_validations / user.total_validations) * 100) : 0;
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Username:</strong></td><td>${user.username}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${user.email}</td></tr>
                            <tr><td><strong>Role:</strong></td><td><span class="badge bg-${user.role === 'admin' ? 'primary' : 'secondary'}">${user.role.toUpperCase()}</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-${user.is_active ? 'success' : 'danger'}">${user.is_active ? 'Active' : 'Inactive'}</span></td></tr>
                            <tr><td><strong>Member Since:</strong></td><td>${new Date(user.created_at).toLocaleDateString()}</td></tr>
                            <tr><td><strong>Last Login:</strong></td><td>${user.last_login ? new Date(user.last_login).toLocaleString() : 'Never'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Validation Statistics</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Total Validations:</strong></td><td>${user.total_validations.toLocaleString()}</td></tr>
                            <tr><td><strong>Valid Emails:</strong></td><td>${user.valid_validations.toLocaleString()}</td></tr>
                            <tr><td><strong>Success Rate:</strong></td><td>${successRate}%</td></tr>
                            <tr><td><strong>Today's Validations:</strong></td><td>${user.today_validations.toLocaleString()}</td></tr>
                        </table>
                    </div>
                </div>
            `;
            
            modal.show();
        }
        
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-success')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    </script>
</body>
</html>