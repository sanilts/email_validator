<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$tab = $_GET['tab'] ?? 'profile';

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle profile update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        
        if (empty($username) || empty($email)) {
            $error = 'Username and email are required';
        } elseif (!validate_email_format($email)) {
            $error = 'Invalid email format';
        } else {
            try {
                // Check if username/email already exists for other users
                $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists';
                } else {
                    $stmt = $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $_SESSION['user_id']]);
                    
                    $_SESSION['username'] = $username;
                    log_activity($_SESSION['user_id'], 'profile_updated');
                    $success = 'Profile updated successfully';
                    
                    // Refresh user data
                    $user['username'] = $username;
                    $user['email'] = $email;
                }
            } catch (Exception $e) {
                $error = 'Failed to update profile';
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    }
}

// Handle password change
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                log_activity($_SESSION['user_id'], 'password_changed');
                $success = 'Password changed successfully';
            } catch (Exception $e) {
                $error = 'Failed to change password';
                error_log("Password change error: " . $e->getMessage());
            }
        }
    }
}

// Get user's SMTP configuration
$stmt = $db->prepare("SELECT * FROM smtp_configs WHERE user_id = ? AND is_active = 1");
$stmt->execute([$_SESSION['user_id']]);
$smtp_config = $stmt->fetch();

// Handle SMTP configuration
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_smtp') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $provider = sanitize_input($_POST['provider']);
        $host = sanitize_input($_POST['host']);
        $port = (int)$_POST['port'];
        $smtp_username = sanitize_input($_POST['smtp_username']);
        $smtp_password = $_POST['smtp_password'];
        $encryption = sanitize_input($_POST['encryption']);
        
        if (empty($provider) || empty($host) || empty($port) || empty($smtp_username)) {
            $error = 'All SMTP fields are required';
        } else {
            try {
                if ($smtp_config) {
                    // Update existing
                    $stmt = $db->prepare("
                        UPDATE smtp_configs 
                        SET provider = ?, host = ?, port = ?, username = ?, password = ?, encryption = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$provider, $host, $port, $smtp_username, $smtp_password, $encryption, $_SESSION['user_id']]);
                } else {
                    // Create new
                    $stmt = $db->prepare("
                        INSERT INTO smtp_configs (user_id, provider, host, port, username, password, encryption) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $provider, $host, $port, $smtp_username, $smtp_password, $encryption]);
                }
                
                log_activity($_SESSION['user_id'], 'smtp_config_updated');
                $success = 'SMTP configuration updated successfully';
                
                // Refresh SMTP config
                $stmt = $db->prepare("SELECT * FROM smtp_configs WHERE user_id = ? AND is_active = 1");
                $stmt->execute([$_SESSION['user_id']]);
                $smtp_config = $stmt->fetch();
                
            } catch (Exception $e) {
                $error = 'Failed to update SMTP configuration';
                error_log("SMTP config error: " . $e->getMessage());
            }
        }
    }
}

$csrf_token = generate_csrf_token();
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <!-- Profile Navigation -->
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <i class="fas fa-user-circle fa-4x text-muted"></i>
                                <h5 class="mt-2"><?php echo htmlspecialchars($user['username']); ?></h5>
                                <small class="text-muted"><?php echo ucfirst($user['role']); ?></small>
                            </div>
                            
                            <nav class="nav nav-pills flex-column">
                                <a class="nav-link <?php echo $tab === 'profile' ? 'active' : ''; ?>" href="?tab=profile">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a>
                                <a class="nav-link <?php echo $tab === 'password' ? 'active' : ''; ?>" href="?tab=password">
                                    <i class="fas fa-lock me-2"></i>Password
                                </a>
                                <a class="nav-link <?php echo $tab === 'smtp' ? 'active' : ''; ?>" href="?tab=smtp">
                                    <i class="fas fa-envelope me-2"></i>SMTP Settings
                                </a>
                                <a class="nav-link <?php echo $tab === 'activity' ? 'active' : ''; ?>" href="?tab=activity">
                                    <i class="fas fa-history me-2"></i>Activity
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-9">
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

                    <?php if ($tab === 'profile'): ?>
                        <!-- Profile Tab -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Member Since</label>
                                            <input type="text" class="form-control" 
                                                   value="<?php echo date('M j, Y', strtotime($user['created_at'])); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Last Login</label>
                                            <input type="text" class="form-control" 
                                                   value="<?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                    <?php elseif ($tab === 'password'): ?>
                        <!-- Password Tab -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Password must be at least 6 characters long</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-lock me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>

                    <?php elseif ($tab === 'smtp'): ?>
                        <!-- SMTP Tab -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">SMTP Configuration</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="update_smtp">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="provider" class="form-label">Provider</label>
                                            <select class="form-select" id="provider" name="provider" required>
                                                <option value="">Select Provider</option>
                                                <option value="gmail" <?php echo ($smtp_config['provider'] ?? '') === 'gmail' ? 'selected' : ''; ?>>Gmail</option>
                                                <option value="outlook" <?php echo ($smtp_config['provider'] ?? '') === 'outlook' ? 'selected' : ''; ?>>Outlook</option>
                                                <option value="yahoo" <?php echo ($smtp_config['provider'] ?? '') === 'yahoo' ? 'selected' : ''; ?>>Yahoo</option>
                                                <option value="custom" <?php echo ($smtp_config['provider'] ?? '') === 'custom' ? 'selected' : ''; ?>>Custom</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="host" class="form-label">SMTP Host</label>
                                            <input type="text" class="form-control" id="host" name="host" 
                                                   value="<?php echo htmlspecialchars($smtp_config['host'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="port" class="form-label">Port</label>
                                            <input type="number" class="form-control" id="port" name="port" 
                                                   value="<?php echo $smtp_config['port'] ?? '587'; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="encryption" class="form-label">Encryption</label>
                                            <select class="form-select" id="encryption" name="encryption" required>
                                                <option value="none" <?php echo ($smtp_config['encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                                                <option value="ssl" <?php echo ($smtp_config['encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="tls" <?php echo ($smtp_config['encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_username" class="form-label">SMTP Username</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($smtp_config['username'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_password" class="form-label">SMTP Password</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                   placeholder="<?php echo $smtp_config ? 'Leave blank to keep current' : 'Enter SMTP password'; ?>">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-save me-2"></i>Save Configuration
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="testSMTP()">
                                        <i class="fas fa-vial me-2"></i>Test Connection
                                    </button>
                                </form>
                            </div>
                        </div>

                    <?php elseif ($tab === 'activity'): ?>
                        <!-- Activity Tab -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $stmt = $db->prepare("
                                    SELECT action, details, created_at, ip_address 
                                    FROM activity_logs 
                                    WHERE user_id = ? 
                                    ORDER BY created_at DESC 
                                    LIMIT 50
                                ");
                                $stmt->execute([$_SESSION['user_id']]);
                                $activities = $stmt->fetchAll();
                                ?>
                                
                                <?php if (empty($activities)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No activity recorded yet</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Action</th>
                                                    <th>Details</th>
                                                    <th>IP Address</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($activities as $activity): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                                        <td class="text-truncate" style="max-width: 200px;">
                                                            <?php echo htmlspecialchars($activity['details'] ?? '-'); ?>
                                                        </td>
                                                        <td class="text-muted"><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                                        <td class="text-muted"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Provider preset configurations
document.getElementById('provider').addEventListener('change', function() {
    const provider = this.value;
    const hostField = document.getElementById('host');
    const portField = document.getElementById('port');
    const encryptionField = document.getElementById('encryption');
    
    const presets = {
        'gmail': { host: 'smtp.gmail.com', port: 587, encryption: 'tls' },
        'outlook': { host: 'smtp-mail.outlook.com', port: 587, encryption: 'tls' },
        'yahoo': { host: 'smtp.mail.yahoo.com', port: 587, encryption: 'tls' }
    };
    
    if (presets[provider]) {
        hostField.value = presets[provider].host;
        portField.value = presets[provider].port;
        encryptionField.value = presets[provider].encryption;
    }
});

function testSMTP() {
    const formData = new FormData();
    formData.append('action', 'test_smtp');
    formData.append('host', document.getElementById('host').value);
    formData.append('port', document.getElementById('port').value);
    formData.append('username', document.getElementById('smtp_username').value);
    formData.append('password', document.getElementById('smtp_password').value);
    formData.append('encryption', document.getElementById('encryption').value);
    
    fetch('../api/test_smtp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('SMTP connection successful!', 'success');
        } else {
            showAlert('SMTP connection failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error testing SMTP connection', 'danger');
    });
}
</script>

<?php include '../templates/footer.php'; ?>