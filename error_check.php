<?php
// ===================================================================
// ERROR_CHECK.PHP - Enhanced System Diagnostics
// ===================================================================
?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔧 Email Validator System Diagnostics</h1>";
echo "<hr>";

// 1. Basic PHP Test
echo "<h3>1. PHP Environment Test</h3>";
echo "✅ PHP Version: " . PHP_VERSION . "<br>";
echo "✅ Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "✅ Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";
echo "✅ Script Path: " . __FILE__ . "<br>";
echo "✅ Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "✅ Max Execution Time: " . ini_get('max_execution_time') . "s<br>";
echo "<hr>";

// 2. Required Extensions
echo "<h3>2. PHP Extensions Check</h3>";
$extensions = ['pdo', 'pdo_mysql', 'json', 'session', 'mbstring', 'curl', 'openssl', 'fileinfo'];
foreach ($extensions as $ext) {
    $status = extension_loaded($ext) ? '✅' : '❌';
    echo "$status $ext: " . (extension_loaded($ext) ? 'Loaded' : 'Missing') . "<br>";
}
echo "<hr>";

// 3. File Structure Check
echo "<h3>3. File Structure Check</h3>";
$files = [
    'config/database.php',
    'config/config.php', 
    'config/smtp.php',
    'auth/login.php',
    'admin/index.php',
    'user/dashboard.php',
    'includes/auth.php',
    'includes/email_validator.php',
    'includes/security.php',
    'includes/functions.php',
    'api/validate_email.php',
    'api/export.php',
    'assets/css/style.css',
    'assets/js/main.js',
    'templates/header.php',
    'templates/footer.php',
    'index.php',
    'install.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "✅ $file (Size: {$size}b, Perms: $perms)<br>";
    } else {
        echo "❌ $file - Missing<br>";
    }
}
echo "<hr>";

// 4. Directory Permissions
echo "<h3>4. Directory Permissions</h3>";
$dirs = ['.', 'config', 'assets', 'assets/uploads', 'assets/uploads/logo', 'assets/uploads/bulk', 'templates', 'includes'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $writable = is_writable($dir) ? '✅ Writable' : '❌ Not Writable';
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        echo "$writable $dir (Perms: $perms)<br>";
    } else {
        echo "❌ $dir - Directory doesn't exist<br>";
        // Try to create it
        if (mkdir($dir, 0755, true)) {
            echo "✅ Created $dir successfully<br>";
        }
    }
}
echo "<hr>";

// 5. Configuration Test
echo "<h3>5. Configuration Test</h3>";
try {
    if (file_exists('config/database.php')) {
        include_once 'config/database.php';
        echo "✅ Database config loaded<br>";
        
        if (class_exists('Database')) {
            $db = new Database();
            $connection = $db->getConnection();
            if ($connection) {
                echo "✅ Database connection successful<br>";
                
                // Test basic tables
                $tables = ['users', 'system_settings', 'email_validations'];
                foreach ($tables as $table) {
                    try {
                        $stmt = $connection->prepare("SELECT COUNT(*) FROM $table");
                        $stmt->execute();
                        $count = $stmt->fetchColumn();
                        echo "✅ Table '$table': $count records<br>";
                    } catch (Exception $e) {
                        echo "❌ Table '$table': Error - " . $e->getMessage() . "<br>";
                    }
                }
            } else {
                echo "❌ Database connection failed<br>";
            }
        } else {
            echo "❌ Database class not found<br>";
        }
    } else {
        echo "❌ Database config file missing<br>";
    }
} catch (Exception $e) {
    echo "❌ Configuration error: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 6. Session Test
echo "<h3>6. Session Test</h3>";
try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    echo "✅ Session started successfully<br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Session Save Path: " . session_save_path() . "<br>";
} catch (Exception $e) {
    echo "❌ Session error: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 7. Email Functions Test
echo "<h3>7. Email Functions Test</h3>";
if (function_exists('mail')) {
    echo "✅ PHP mail() function available<br>";
} else {
    echo "❌ PHP mail() function not available<br>";
}

if (function_exists('checkdnsrr')) {
    echo "✅ DNS lookup functions available<br>";
} else {
    echo "❌ DNS lookup functions not available<br>";
}

if (function_exists('fsockopen')) {
    echo "✅ Socket functions available<br>";
} else {
    echo "❌ Socket functions not available<br>";
}
echo "<hr>";

// 8. SMTP Test
echo "<h3>8. SMTP Test</h3>";
try {
    $smtp_test = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 5);
    if ($smtp_test) {
        echo "✅ SMTP connectivity test successful (Gmail)<br>";
        fclose($smtp_test);
    } else {
        echo "❌ SMTP connectivity test failed: $errstr ($errno)<br>";
    }
} catch (Exception $e) {
    echo "❌ SMTP test error: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 9. File Upload Test
echo "<h3>9. File Upload Configuration</h3>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";
echo "Max File Uploads: " . ini_get('max_file_uploads') . "<br>";
echo "Temp Directory: " . sys_get_temp_dir() . "<br>";
echo "Upload Temp Dir: " . (ini_get('upload_tmp_dir') ?: 'Default') . "<br>";
echo "<hr>";

// 10. Error Log Check
echo "<h3>10. Recent PHP Errors</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $errors = array_slice(file($error_log), -10);
    echo "<pre style='background:#f5f5f5;padding:10px;max-height:200px;overflow:auto;'>";
    foreach ($errors as $error) {
        echo htmlspecialchars($error);
    }
    echo "</pre>";
} else {
    echo "No error log found or accessible<br>";
}

echo "<hr>";
echo "<h3>🎯 Quick Actions</h3>";
echo "<a href='install.php' style='background:#007bff;color:white;padding:10px;text-decoration:none;margin:5px;border-radius:5px;'>Run Installer</a> ";
echo "<a href='auth/login.php' style='background:#28a745;color:white;padding:10px;text-decoration:none;margin:5px;border-radius:5px;'>Go to Login</a> ";
echo "<a href='index.php' style='background:#17a2b8;color:white;padding:10px;text-decoration:none;margin:5px;border-radius:5px;'>Try Home Page</a><br><br>";

echo "<h3>📋 Installation Status</h3>";
if (file_exists('config/installed.lock')) {
    echo "<div style='background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:10px;border-radius:5px;'>
    ✅ Application is installed and ready to use!</div>";
} else {
    echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:10px;border-radius:5px;'>
    ❌ Application not installed. Please run the installer.</div>";
}

echo "<h3>📋 Next Steps</h3>";
echo "<ol>";
echo "<li>Check the red ❌ items above and fix them</li>";
echo "<li>Make sure database credentials are correct in config/database.php</li>";
echo "<li>Set proper file permissions (644 for files, 755 for directories)</li>";
echo "<li>Run the installer if you haven't already</li>";
echo "<li>Check your web server error logs</li>";
echo "<li>Ensure all required PHP extensions are installed</li>";
echo "</ol>";
?>