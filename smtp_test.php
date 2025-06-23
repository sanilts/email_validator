<?php
// ===================================================================
// SMTP_TEST.PHP - Standalone SMTP Testing Script
// ===================================================================
?>
<?php
// SMTP Testing Script - Place this in your root directory
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the configuration
require_once 'config/config.php';
require_once 'config/smtp.php';

// Start session for testing
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$test_result = '';
$error = '';

if ($_POST) {
    $smtp_config = [
        'host' => trim($_POST['host']),
        'port' => (int)$_POST['port'],
        'username' => trim($_POST['username']),
        'password' => $_POST['password'],
        'encryption' => $_POST['encryption']
    ];
    
    $test_email = trim($_POST['test_email']);
    
    if (empty($smtp_config['host']) || empty($smtp_config['username']) || empty($test_email)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            $smtp_handler = new SMTPHandler($smtp_config);
            
            // Test connection first
            $connection_test = $smtp_handler->testConnection();
            
            if ($connection_test['success']) {
                // Try to send a test email
                $subject = 'SMTP Test Email from Email Validator';
                $message = '
                    <h2>SMTP Configuration Test</h2>
                    <p>This is a test email to verify your SMTP configuration is working correctly.</p>
                    <p><strong>Test Details:</strong></p>
                    <ul>
                        <li>SMTP Host: ' . htmlspecialchars($smtp_config['host']) . '</li>
                        <li>Port: ' . $smtp_config['port'] . '</li>
                        <li>Encryption: ' . strtoupper($smtp_config['encryption']) . '</li>
                        <li>Test Time: ' . date('Y-m-d H:i:s') . '</li>
                    </ul>
                    <p>If you received this email, your SMTP configuration is working correctly!</p>
                ';
                
                $sent = $smtp_handler->send($test_email, $subject, $message, $smtp_config['username']);
                
                if ($sent) {
                    $test_result = "✅ SUCCESS: Test email sent successfully to $test_email";
                } else {
                    $error = "❌ ERROR: SMTP connection successful but failed to send email";
                }
            } else {
                $error = "❌ CONNECTION ERROR: " . $connection_test['message'];
            }
        } catch (Exception $e) {
            $error = "❌ EXCEPTION: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Configuration Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #659833;
            --secondary-color: #32679B;
            --dark-color: #0F101D;
        }
        body { background-color: #f8f9fa; }
        .test-card { max-width: 600px; margin: 50px auto; }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background-color: #558229; border-color: #558229; }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-card">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3><i class="fas fa-envelope me-2"></i>SMTP Configuration Test</h3>
                </div>
                <div class="card-body">
                    <?php if ($test_result): ?>
                        <div class="alert alert-success">
                            <h5><?php echo $test_result; ?></h5>
                            <p class="mb-0">Check your email inbox to confirm delivery.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <h5><?php echo $error; ?></h5>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="host" class="form-label">SMTP Host *</label>
                                <input type="text" class="form-control" id="host" name="host" 
                                       value="<?php echo htmlspecialchars($_POST['host'] ?? ''); ?>" 
                                       placeholder="smtp.gmail.com" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="port" class="form-label">Port *</label>
                                <input type="number" class="form-control" id="port" name="port" 
                                       value="<?php echo $_POST['port'] ?? '587'; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username (Email) *</label>
                            <input type="email" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">For Gmail, use an App Password, not your regular password.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="encryption" class="form-label">Encryption</label>
                            <select class="form-select" id="encryption" name="encryption">
                                <option value="tls" <?php echo ($_POST['encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                                <option value="ssl" <?php echo ($_POST['encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($_POST['encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Test Email Address *</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" 
                                   value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>" 
                                   placeholder="your-email@example.com" required>
                            <div class="form-text">Where to send the test email</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                📧 Test SMTP Configuration
                            </button>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>📋 Common SMTP Settings</h6>
                            <small>
                                <strong>Gmail:</strong><br>
                                Host: smtp.gmail.com<br>
                                Port: 587 | Encryption: TLS<br><br>
                                
                                <strong>Outlook:</strong><br>
                                Host: smtp-mail.outlook.com<br>
                                Port: 587 | Encryption: TLS<br><br>
                                
                                <strong>Yahoo:</strong><br>
                                Host: smtp.mail.yahoo.com<br>
                                Port: 587 | Encryption: TLS
                            </small>
                        </div>
                        <div class="col-md-6">
                            <h6>🔒 Security Notes</h6>
                            <small>
                                <strong>Gmail Users:</strong><br>
                                • Enable 2-Factor Authentication<br>
                                • Generate an App Password<br>
                                • Use App Password, not your regular password<br><br>
                                
                                <strong>General:</strong><br>
                                • TLS encryption is recommended<br>
                                • Never share SMTP credentials<br>
                                • Test in a secure environment
                            </small>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <a href="auth/login.php" class="btn btn-outline-secondary">← Back to Application</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>