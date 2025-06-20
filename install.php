<?php
// =============================================================================
// INSTALLATION SCRIPT (install.php)
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
                                
                                // Create tables
                                $sql = "
                                CREATE TABLE IF NOT EXISTS users (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    username VARCHAR(50) UNIQUE NOT NULL,
                                    email VARCHAR(100) UNIQUE NOT NULL,
                                    password VARCHAR(255) NOT NULL,
                                    role ENUM('admin', 'user') DEFAULT 'user',
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    last_login TIMESTAMP NULL,
                                    is_active BOOLEAN DEFAULT 1
                                );

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
                                    FOREIGN KEY (user_id) REFERENCES users(id),
                                    FOREIGN KEY (list_id) REFERENCES email_lists(id),
                                    FOREIGN KEY (template_id) REFERENCES email_templates(id),
                                    INDEX idx_email (email),
                                    INDEX idx_validation_date (validation_date),
                                    INDEX idx_batch (batch_id),
                                    INDEX idx_list (list_id)
                                );

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
                                    FOREIGN KEY (user_id) REFERENCES users(id)
                                );

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
                                    FOREIGN KEY (user_id) REFERENCES users(id)
                                );

                                CREATE TABLE IF NOT EXISTS activity_logs (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    user_id INT NOT NULL,
                                    action VARCHAR(100) NOT NULL,
                                    details TEXT,
                                    ip_address VARCHAR(45),
                                    user_agent TEXT,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (user_id) REFERENCES users(id),
                                    INDEX idx_user_action (user_id, action),
                                    INDEX idx_created_at (created_at)
                                );

                                CREATE TABLE IF NOT EXISTS system_settings (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                                    setting_value TEXT,
                                    description TEXT,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                );

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
                                    FOREIGN KEY (user_id) REFERENCES users(id),
                                    INDEX idx_user_list (user_id, list_name)
                                );

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
                                    FOREIGN KEY (user_id) REFERENCES users(id)
                                );

                                CREATE TABLE IF NOT EXISTS list_email_assignments (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    list_id INT NOT NULL,
                                    validation_id INT NOT NULL,
                                    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (list_id) REFERENCES email_lists(id) ON DELETE CASCADE,
                                    FOREIGN KEY (validation_id) REFERENCES email_validations(id) ON DELETE CASCADE,
                                    UNIQUE KEY unique_assignment (list_id, validation_id)
                                );
                                ";
                                
                                $pdo->exec($sql);
                                
                                // Insert default admin user
                                $admin_password = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
                                $stmt->execute([$_POST['admin_username'], $_POST['admin_email'], $admin_password]);
                                
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
                                $admin_id = $pdo->lastInsertId();
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
                                echo '<a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Go to Application</a>';
                                
                            } catch (Exception $e) {
                                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' . $e->getMessage() . '</div>';
                            }
                        } else {
                        ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Database Host</label>
                                <input type="text" class="form-control" name="host" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Database Username</label>
                                <input type="text" class="form-control" name="username" value="root" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Database Password</label>
                                <input type="password" class="form-control" name="password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Database Name</label>
                                <input type="text" class="form-control" name="dbname" value="email_validator" required>
                            </div>
                            <hr>
                            <h5>Admin Account</h5>
                            <div class="mb-3">
                                <label class="form-label">Admin Username</label>
                                <input type="text" class="form-control" name="admin_username" value="admin" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin Email</label>
                                <input type="email" class="form-control" name="admin_email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin Password</label>
                                <input type="password" class="form-control" name="admin_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-download"></i> Install</button>
                        </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>