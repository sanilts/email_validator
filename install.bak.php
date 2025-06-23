<?php
// install.php - Complete installation script
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if already installed
if (file_exists('config/installed.lock')) {
    die('
    <div style="text-align: center; margin: 50px; font-family: Arial;">
        <h2>Already Installed</h2>
        <p>The application is already installed. Delete <code>config/installed.lock</code> to reinstall.</p>
        <a href="auth/login.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Login</a>
    </div>
    ');
}

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

// Handle form submissions
if ($_POST) {
    if ($step === 1) {
        // Database configuration
        $host = trim($_POST['host'] ?? 'localhost');
        $dbname = trim($_POST['dbname'] ?? 'email_validator');
        $username = trim($_POST['username'] ?? 'root');
        $password = $_POST['password'] ?? '';
        
        try {
            // Test connection
            $dsn = "mysql:host=$host;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
            $pdo->exec("USE `$dbname`");
            
            // Save database config
            $config_content = "<?php
// config/database.php - Database configuration
class Database {
    private \$host = '$host';
    private \$db_name = '$dbname';
    private \$username = '$username';
    private \$password = '$password';
    private \$conn;

    public function getConnection() {
        if (\$this->conn !== null) {
            return \$this->conn;
        }
        
        try {
            \$dsn = \"mysql:host={\$this->host};dbname={\$this->db_name};charset=utf8mb4\";
            \$this->conn = new PDO(\$dsn, \$this->username, \$this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            return \$this->conn;
        } catch (PDOException \$e) {
            throw new Exception(\"Database connection failed: \" . \$e->getMessage());
        }
    }
}
?>";
            
            if (!is_dir('config')) {
                mkdir('config', 0755, true);
            }
            
            file_put_contents('config/database.php', $config_content);
            
            $success = 'Database configuration saved successfully!';
            $step = 2;
            
        } catch (Exception $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
        
    } elseif ($step === 2) {
        // Create tables and admin user
        try {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            // Read and execute schema
            $schema = file_get_contents('config/schema.sql');
            
            // Split by semicolon and execute each statement
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $db->exec($statement);
                }
            }
            
            // Create admin user
            $admin_username = trim($_POST['admin_username'] ?? 'admin');
            $admin_email = trim($_POST['admin_email'] ?? 'admin@example.com');
            $admin_password = $_POST['admin_password'] ?? '';
            
            if (empty($admin_username) || empty($admin_email) || empty($admin_password)) {
                throw new Exception('All admin fields are required');
            }
            
            if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid admin email format');
            }
            
            if (strlen($admin_password) < 6) {
                throw new Exception('Admin password must be at least 6 characters');
            }
            
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, role, created_at) 
                VALUES (?, ?, ?, 'admin', NOW())
            ");
            $stmt->execute([$admin_username, $admin_email, $hashed_password]);
            
            // Create installation lock file
            file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
            
            // Create uploads directory
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }
            
            // Create assets/uploads directory
            if (!is_dir('assets/uploads')) {
                mkdir('assets/uploads', 0755, true);
            }
            if (!is_dir('assets/uploads/logo')) {
                mkdir('assets/uploads/logo', 0755, true);
            }
            
            $success = 'Installation completed successfully!';
            $step = 3;
            
        } catch (Exception $e) {
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Validator - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #659833, #32679B);
            min-height: 100vh;
        }
        .install-card {
            max-width: 600px;
            margin: 50px auto;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            background: rgba(255,255,255,0.3);
            color: white;
            font-weight: bold;
        }
        .step.active {
            background: white;
            color: #659833;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-card">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white text-center">
                    <h3><i class="fas fa-envelope-check me-2"></i>Email Validator Installation</h3>
                </div>
                <div class="card-body">
                    
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                        <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                        <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step === 1): ?>
                        <!-- Step 1: Database Configuration -->
                        <h5>Step 1: Database Configuration</h5>
                        <p class="text-muted mb-4">Configure your database connection settings.</p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="host" class="form-label">Database Host</label>
                                <input type="text" class="form-control" id="host" name="host" value="localhost" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="dbname" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="dbname" name="dbname" value="email_validator" required>
                                <div class="form-text">Database will be created if it doesn't exist</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Database Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="root" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Database Password</label>
                                <input type="password" class="form-control" id="password" name="password">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-database me-2"></i>Test Connection & Continue
                            </button>
                        </form>
                        
                    <?php elseif ($step === 2): ?>
                        <!-- Step 2: Admin User Creation -->
                        <h5>Step 2: Create Admin User</h5>
                        <p class="text-muted mb-4">Create the administrator account for your Email Validator.</p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="admin_username" class="form-label">Admin Username</label>
                                <input type="text" class="form-control" id="admin_username" name="admin_username" value="admin" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="admin_email" class="form-label">Admin Email</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="admin_password" class="form-label">Admin Password</label>
                                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-rocket me-2"></i>Complete Installation
                            </button>
                        </form>
                        
                    <?php elseif ($step === 3): ?>
                        <!-- Step 3: Installation Complete -->
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                            <h4>Installation Complete!</h4>
                            <p class="text-muted mb-4">Your Email Validator is now ready to use.</p>
                            
                            <div class="d-grid gap-2">
                                <a href="auth/login.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Admin Panel
                                </a>
                                <a href="user/dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                </a>
                            </div>
                            
                            <div class="alert alert-info mt-4">
                                <strong>Important:</strong> For security, please delete this install.php file after installation.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($step < 3): ?>
                <div class="card-footer text-center text-muted">
                    <small>Step <?php echo $step; ?> of 3</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>