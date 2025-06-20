<?php
// =============================================================================
// FIXED INSTALLATION SCRIPT (install.php)
// =============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Validator - Installation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cog"></i> Email Validator Installation</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                            // Database creation and setup
                            $host = $_POST['host'];
                            $username = $_POST['username'];
                            $password = $_POST['password'];
                            $dbname = $_POST['dbname'];
                            
                            try {
                                $pdo = new PDO("mysql:host=$host", $username, $password);
                                $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
                                $pdo->exec("USE $dbname");
                                
                                // Set foreign key checks to 0 temporarily
                                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                                
                                // Create tables in correct order (dependencies first)
                                
                                // 1. Users table (no dependencies)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS users (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    username VARCHAR(50) UNIQUE NOT NULL,
                                    email VARCHAR(100) UNIQUE NOT NULL,
                                    password VARCHAR(255) NOT NULL,
                                    role ENUM('admin', 'user') DEFAULT 'user',
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    last_login TIMESTAMP NULL,
                                    is_active BOOLEAN DEFAULT 1
                                )");

                                // 2. System settings table (no dependencies)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS system_settings (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                                    setting_value TEXT,
                                    description TEXT,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                )");

                                // 3. Email lists table (depends on users)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS email_lists (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    list_name VARCHAR(255) NOT NULL,
                                    description TEXT,
                                    user_id INT NOT NULL,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    is_active BOOLEAN DEFAULT 1,
                                    total_emails INT DEFAULT 0,
                                    valid_emails INT DEFAULT 0,
                                    invalid_emails INT DEFAULT 0,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                                    INDEX idx_user_list (user_id, list_name)
                                )");

                                // 4. Email templates table (depends on users)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS email_templates (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    template_name VARCHAR(255) NOT NULL,
                                    subject VARCHAR(500) NOT NULL,
                                    message TEXT NOT NULL,
                                    template_type ENUM('verification', 'notification') DEFAULT 'verification',
                                    user_id INT NOT NULL,
                                    is_default BOOLEAN DEFAULT 0,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                                )");

                                // 5. Email validations table (depends on users, email_lists, email_templates)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS email_validations (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    email VARCHAR(255) NOT NULL,
                                    is_valid BOOLEAN DEFAULT 0,
                                    validation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    format_valid BOOLEAN DEFAULT 0,
                                    domain_valid BOOLEAN DEFAULT 0,
                                    smtp_valid BOOLEAN DEFAULT 0,
                                    user_id INT,
                                    batch_id VARCHAR(50),
                                    list_id INT NULL,
                                    template_id INT NULL,
                                    validation_details JSON,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                                    FOREIGN KEY (list_id) REFERENCES email_lists(id) ON DELETE SET NULL,
                                    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE SET NULL,
                                    INDEX idx_email (email),
                                    INDEX idx_validation_date (validation_date),
                                    INDEX idx_batch (batch_id),
                                    INDEX idx_list (list_id),
                                    INDEX idx_user (user_id)
                                )");

                                // 6. Email batches table (depends on users)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS email_batches (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    batch_id VARCHAR(50) UNIQUE NOT NULL,
                                    user_id INT NOT NULL,
                                    filename VARCHAR(255),
                                    total_emails INT DEFAULT 0,
                                    valid_emails INT DEFAULT 0,
                                    invalid_emails INT DEFAULT 0,
                                    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    completed_at TIMESTAMP NULL,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                                )");

                                // 7. List email assignments (depends on email_lists, email_validations)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS list_email_assignments (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    list_id INT NOT NULL,
                                    validation_id INT NOT NULL,
                                    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (list_id) REFERENCES email_lists(id) ON DELETE CASCADE,
                                    FOREIGN KEY (validation_id) REFERENCES email_validations(id) ON DELETE CASCADE,
                                    UNIQUE KEY unique_assignment (list_id, validation_id)
                                )");

                                // 8. SMTP settings table (depends on users)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS smtp_settings (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    user_id INT NOT NULL,
                                    provider_type ENUM('php_mail', 'smtp', 'postmark', 'sendgrid', 'mailgun') NOT NULL,
                                    smtp_host VARCHAR(255),
                                    smtp_port INT,
                                    smtp_username VARCHAR(255),
                                    smtp_password VARCHAR(255),
                                    api_key VARCHAR(255),
                                    from_email VARCHAR(255) NOT NULL,
                                    from_name VARCHAR(255),
                                    is_active BOOLEAN DEFAULT 1,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                                )");

                                // 9. Activity logs table (depends on users)
                                $pdo->exec("
                                CREATE TABLE IF NOT EXISTS activity_logs (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    user_id INT NOT NULL,
                                    action VARCHAR(100) NOT NULL,
                                    details TEXT,
                                    ip_address VARCHAR(45),
                                    user_agent TEXT,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                                    INDEX idx_user_action (user_id, action),
                                    INDEX idx_created_at (created_at)
                                )");
                                
                                // Re-enable foreign key checks
                                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                                
                                // Insert default admin user
                                $admin_password = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
                                $stmt->execute([$_POST['admin_username'], $_POST['admin_email'], $admin_password]);
                                
                                $admin_id = $pdo->lastInsertId();
                                
                                // Insert default settings
                                $default_settings = [
                                    ['validation_cache_days', '90', 'Days to cache email validation results'],
                                    ['max_bulk_emails', '10000', 'Maximum emails per bulk upload'],
                                    ['verification_timeout', '30', 'SMTP verification timeout in seconds'],
                                    ['daily_validation_limit', '1000', 'Daily validation limit per user']
                                ];
                                
                                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
                                foreach ($default_settings as $setting) {
                                    $stmt->execute($setting);
                                }

                                // Insert default email template
                                $default_template = [
                                    'Email Verification - Standard',
                                    'Please verify your email address',
                                    "Hello,\n\nWe are verifying the deliverability of this email address.\n\nVerification Code: {{verification_code}}\n\nThis is an automated email from our Email Validation System.\n\nIf you did not request this verification, please ignore this email.\n\nBest regards,\nEmail Validation Team",
                                    'verification',
                                    $admin_id,
                                    1
                                ];
                                
                                $stmt = $pdo->prepare("INSERT INTO email_templates (template_name, subject, message, template_type, user_id, is_default) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt->execute($default_template);
                                
                                echo '<div class="alert alert-success"><i class="fas fa-check"></i> Installation completed successfully!</div>';
                                echo '<p class="mb-3">Your email validation system is now ready to use.</p>';
                                echo '<div class="d-grid gap-2">';
                                echo '<a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Go to Application</a>';
                                echo '<a href="login.php" class="btn btn-outline-primary"><i class="fas fa-sign-in-alt"></i> Login Page</a>';
                                echo '</div>';
                                
                            } catch (Exception $e) {
                                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' . $e->getMessage() . '</div>';
                                echo '<p class="text-muted">Please check your database credentials and try again.</p>';
                            }
                        } else {
                        ?>
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Installation Requirements</h5>
                            <ul class="mb-0">
                                <li>PHP 7.4 or higher</li>
                                <li>MySQL 5.7 or higher</li>
                                <li>PDO, cURL, and OpenSSL extensions</li>
                                <li>Write permissions for the application directory</li>
                            </ul>
                        </div>
                        
                        <form method="post">
                            <h5>Database Configuration</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Database Host</label>
                                        <input type="text" class="form-control" name="host" value="localhost" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Database Name</label>
                                        <input type="text" class="form-control" name="dbname" value="email_validator" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Database Username</label>
                                        <input type="text" class="form-control" name="username" value="root" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Database Password</label>
                                        <input type="password" class="form-control" name="password" placeholder="Leave empty if no password">
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            <h5>Administrator Account</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Admin Username</label>
                                        <input type="text" class="form-control" name="admin_username" value="admin" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Admin Email</label>
                                        <input type="email" class="form-control" name="admin_email" placeholder="admin@example.com" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Admin Password</label>
                                <input type="password" class="form-control" name="admin_password" placeholder="Enter a strong password" required>
                                <div class="form-text">Use a strong password with at least 8 characters</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-download"></i> Install Email Validator
                                </button>
                            </div>
                        </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>