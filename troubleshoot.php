<?php
// Simple troubleshooting page for debugging issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Validator - Troubleshooting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="fas fa-tools me-2"></i>Email Validator - System Diagnostics</h3>
                    </div>
                    <div class="card-body">
                        
                        <h5>1. PHP Environment</h5>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>PHP Version: <?php echo PHP_VERSION; ?></li>
                            <li><i class="fas fa-<?php echo extension_loaded('pdo') ? 'check text-success' : 'times text-danger'; ?> me-2"></i>PDO Extension: <?php echo extension_loaded('pdo') ? 'Loaded' : 'Missing'; ?></li>
                            <li><i class="fas fa-<?php echo extension_loaded('pdo_mysql') ? 'check text-success' : 'times text-danger'; ?> me-2"></i>PDO MySQL: <?php echo extension_loaded('pdo_mysql') ? 'Loaded' : 'Missing'; ?></li>
                            <li><i class="fas fa-<?php echo extension_loaded('json') ? 'check text-success' : 'times text-danger'; ?> me-2"></i>JSON Extension: <?php echo extension_loaded('json') ? 'Loaded' : 'Missing'; ?></li>
                            <li><i class="fas fa-<?php echo function_exists('session_start') ? 'check text-success' : 'times text-danger'; ?> me-2"></i>Sessions: <?php echo function_exists('session_start') ? 'Available' : 'Not Available'; ?></li>
                        </ul>

                        <h5>2. File System</h5>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-<?php echo file_exists('config/database.php') ? 'check text-success' : 'times text-danger'; ?> me-2"></i>Database Config: <?php echo file_exists('config/database.php') ? 'Found' : 'Missing'; ?></li>
                            <li><i class="fas fa-<?php echo is_writable('.') ? 'check text-success' : 'times text-warning'; ?> me-2"></i>Directory Writable: <?php echo is_writable('.') ? 'Yes' : 'No'; ?></li>
                            <li><i class="fas fa-<?php echo file_exists('install.php') ? 'check text-success' : 'times text-info'; ?> me-2"></i>Install Script: <?php echo file_exists('install.php') ? 'Available' : 'Not Found'; ?></li>
                            <li><i class="fas fa-<?php echo file_exists('config/installed.lock') ? 'check text-success' : 'times text-warning'; ?> me-2"></i>Installation Lock: <?php echo file_exists('config/installed.lock') ? 'Found' : 'Not Found'; ?></li>
                        </ul>

                        <h5>3. Database Connection</h5>
                        <?php
                        try {
                            require_once 'config/database.php';
                            $database = new Database();
                            $db = $database->getConnection();
                            echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Database connection successful!</div>';
                            
                            // Check tables
                            $tables = ['users', 'system_settings', 'email_validations', 'activity_logs'];
                            echo '<ul class="list-unstyled">';
                            foreach ($tables as $table) {
                                try {
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM $table");
                                    $stmt->execute();
                                    $count = $stmt->fetchColumn();
                                    echo "<li><i class='fas fa-check text-success me-2'></i>Table '$table': $count records</li>";
                                } catch (Exception $e) {
                                    echo "<li><i class='fas fa-times text-danger me-2'></i>Table '$table': Missing or error</li>";
                                }
                            }
                            echo '</ul>';
                            
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        ?>

                        <h5>4. Quick Actions</h5>
                        <div class="d-grid gap-2 d-md-flex">
                            <?php if (file_exists('install.php')): ?>
                                <a href="install.php" class="btn btn-primary">
                                    <i class="fas fa-play me-2"></i>Run Installation
                                </a>
                            <?php endif; ?>
                            
                            <?php if (file_exists('auth/login.php')): ?>
                                <a href="auth/login.php" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                </a>
                            <?php endif; ?>
                            
                            <button onclick="location.reload()" class="btn btn-secondary">
                                <i class="fas fa-refresh me-2"></i>Refresh
                            </button>
                        </div>

                        <h5 class="mt-4">5. Common Solutions</h5>
                        <div class="accordion" id="solutionsAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#solution1">
                                        500 Internal Server Error
                                    </button>
                                </h2>
                                <div id="solution1" class="accordion-collapse collapse" data-bs-parent="#solutionsAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Check file permissions (755 for directories, 644 for files)</li>
                                            <li>Verify database credentials in <code>config/database.php</code></li>
                                            <li>Ensure all required PHP extensions are installed</li>
                                            <li>Check PHP error logs</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#solution2">
                                        Database Connection Issues
                                    </button>
                                </h2>
                                <div id="solution2" class="accordion-collapse collapse" data-bs-parent="#solutionsAccordion">
                                    <div class="accordion-body">
                                        <ol>
                                            <li>Update <code>config/database.php</code> with correct credentials</li>
                                            <li>Ensure MySQL server is running</li>
                                            <li>Create database manually: <code>CREATE DATABASE email_validator;</code></li>
                                            <li>Grant permissions to your user</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Still having issues?</h6>
                            <p class="mb-0">
                                1. Check your web server error logs<br>
                                2. Ensure PHP 7.4+ is installed<br>
                                3. Verify file permissions<br>
                                4. Run the installation script
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>