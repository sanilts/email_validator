<?php
// =============================================================================
// DIAGNOSTIC TOOL (diagnostic.php)
// =============================================================================
// Save this as diagnostic.php in your root directory and run it

session_start();
require_once 'config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Validator - Diagnostic Tool</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .diagnostic-result { font-family: monospace; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin: 1rem 0; }
        .pass { color: #198754; font-weight: bold; }
        .fail { color: #dc3545; font-weight: bold; }
        .warning { color: #fd7e14; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="fas fa-tools"></i> Email Validator Diagnostic Tool</h1>
        
        <?php
        echo "<div class='diagnostic-result'>";
        echo "<h3>System Diagnostic Report</h3>";
        echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Test 1: PHP Environment
        echo "=== PHP ENVIRONMENT ===\n";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Extensions:\n";
        $required_extensions = ['pdo', 'pdo_mysql', 'curl', 'openssl', 'json'];
        foreach ($required_extensions as $ext) {
            $loaded = extension_loaded($ext);
            echo "  - $ext: " . ($loaded ? "[PASS]" : "[FAIL]") . "\n";
        }
        
        echo "Functions:\n";
        $required_functions = ['checkdnsrr', 'getmxrr', 'fsockopen', 'filter_var'];
        foreach ($required_functions as $func) {
            $exists = function_exists($func);
            echo "  - $func(): " . ($exists ? "[PASS]" : "[FAIL]") . "\n";
        }
        echo "\n";
        
        // Test 2: Database Connection
        echo "=== DATABASE CONNECTION ===\n";
        try {
            $database = new Database();
            $db = $database->getConnection();
            if ($db) {
                echo "Database connection: [PASS]\n";
                
                // Check tables
                $tables = ['users', 'email_validations', 'system_settings'];
                foreach ($tables as $table) {
                    $stmt = $db->query("SHOW TABLES LIKE '$table'");
                    $exists = $stmt->rowCount() > 0;
                    echo "  - Table $table: " . ($exists ? "[PASS]" : "[FAIL]") . "\n";
                }
            } else {
                echo "Database connection: [FAIL]\n";
            }
        } catch (Exception $e) {
            echo "Database connection: [FAIL] - " . $e->getMessage() . "\n";
        }
        echo "\n";
        
        // Test 3: File Structure
        echo "=== FILE STRUCTURE ===\n";
        $required_files = [
            'api/validate-email.php',
            'config/database.php',
            'classes/EmailSender.php',
            'index.php',
            'login.php'
        ];
        foreach ($required_files as $file) {
            $exists = file_exists($file);
            echo "  - $file: " . ($exists ? "[PASS]" : "[FAIL]") . "\n";
        }
        echo "\n";
        
        // Test 4: API Endpoint Test
        echo "=== API ENDPOINT TEST ===\n";
        
        // Simulate a direct API call
        $_POST = []; // Clear any POST data
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = 1; // Simulate logged in user
        
        $test_email = 'test@gmail.com';
        $test_data = json_encode(['email' => $test_email]);
        
        // Capture the API response
        ob_start();
        
        // Simulate the API call
        $input = json_decode($test_data, true);
        $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
        
        echo "Testing email: $email\n";
        echo "Email sanitization: " . ($email === $test_email ? "[PASS]" : "[FAIL]") . "\n";
        
        // Test email format validation
        $format_valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        echo "Format validation: " . ($format_valid ? "[PASS]" : "[FAIL]") . "\n";
        
        // Test domain validation
        $domain = substr(strrchr($email, "@"), 1);
        echo "Extracted domain: $domain\n";
        
        if (function_exists('checkdnsrr')) {
            $dns_valid = checkdnsrr($domain, "MX") || checkdnsrr($domain, "A");
            echo "DNS validation: " . ($dns_valid ? "[PASS]" : "[FAIL]") . "\n";
        } else {
            echo "DNS validation: [SKIP] - checkdnsrr not available\n";
        }
        
        // Test SMTP validation (basic connectivity)
        if (function_exists('getmxrr') && function_exists('fsockopen')) {
            $mxRecords = [];
            if (getmxrr($domain, $mxRecords)) {
                echo "MX records found: " . implode(', ', array_slice($mxRecords, 0, 3)) . "\n";
                
                $mx = $mxRecords[0];
                $ports_to_test = [587, 25, 465];
                $smtp_success = false;
                
                foreach ($ports_to_test as $port) {
                    $socket = @fsockopen($mx, $port, $errno, $errstr, 5);
                    if ($socket) {
                        echo "SMTP connection to $mx:$port: [PASS]\n";
                        fclose($socket);
                        $smtp_success = true;
                        break;
                    } else {
                        echo "SMTP connection to $mx:$port: [FAIL] ($errno: $errstr)\n";
                    }
                }
                
                if (!$smtp_success) {
                    echo "SMTP validation: [FAIL] - No ports accessible\n";
                } else {
                    echo "SMTP validation: [PASS]\n";
                }
            } else {
                echo "MX records: [FAIL] - No MX records found\n";
            }
        } else {
            echo "SMTP validation: [SKIP] - Required functions not available\n";
        }
        
        $api_output = ob_get_clean();
        echo $api_output;
        echo "\n";
        
        // Test 5: JavaScript/Frontend Test
        echo "=== FRONTEND TEST ===\n";
        echo "This section requires browser testing:\n";
        echo "  1. Open browser console (F12)\n";
        echo "  2. Go to your main application\n";
        echo "  3. Run: debugEmailValidation('test@gmail.com')\n";
        echo "  4. Check console output for errors\n\n";
        
        // Test 6: Configuration Check
        echo "=== CONFIGURATION CHECK ===\n";
        if (isset($db)) {
            try {
                $stmt = $db->query("SELECT * FROM system_settings");
                $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($settings) > 0) {
                    echo "System settings: [PASS]\n";
                    foreach ($settings as $setting) {
                        echo "  - {$setting['setting_key']}: {$setting['setting_value']}\n";
                    }
                } else {
                    echo "System settings: [WARNING] - No settings found\n";
                }
            } catch (Exception $e) {
                echo "System settings: [FAIL] - " . $e->getMessage() . "\n";
            }
        }
        echo "\n";
        
        // Test 7: Recommendations
        echo "=== RECOMMENDATIONS ===\n";
        
        if (!function_exists('checkdnsrr')) {
            echo "[ACTION REQUIRED] Install missing PHP extensions or enable dns functions\n";
        }
        
        if (!function_exists('fsockopen')) {
            echo "[ACTION REQUIRED] Enable fsockopen function for SMTP validation\n";
        }
        
        echo "[INFO] If validation still fails:\n";
        echo "  1. Check if your hosting provider blocks outbound SMTP (port 25/587)\n";
        echo "  2. Verify DNS resolution is working\n";
        echo "  3. Check error logs for detailed error messages\n";
        echo "  4. Try using the debug interface (debug.php)\n";
        echo "  5. Consider using API-based validation services\n\n";
        
        echo "=== NEXT STEPS ===\n";
        echo "1. If all tests PASS but validation still fails:\n";
        echo "   - Replace JavaScript in index.php with the enhanced version\n";
        echo "   - Clear browser cache and try again\n";
        echo "   - Check browser console for JavaScript errors\n\n";
        echo "2. If tests FAIL:\n";
        echo "   - Fix the failing components first\n";
        echo "   - Contact your hosting provider about restrictions\n";
        echo "   - Consider using the enhanced validator API\n\n";
        
        echo "Diagnostic completed.\n";
        echo "</div>";
        ?>
        
        <div class="mt-4">
            <h3>Quick Actions</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Test API Directly</h5>
                            <button class="btn btn-primary" onclick="testAPI()">Test Email Validation API</button>
                            <div id="api-test-result" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Test System Functions</h5>
                            <button class="btn btn-info" onclick="testSystemFunctions()">Test System Functions</button>
                            <div id="system-test-result" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function testAPI() {
            const resultDiv = document.getElementById('api-test-result');
            resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Testing...';
            
            fetch('api/validate-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: 'test@gmail.com' })
            })
            .then(response => response.text())
            .then(text => {
                console.log('API Response:', text);
                try {
                    const data = JSON.parse(text);
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>API Test Result:</strong><br>
                            Format: ${data.format_valid ? 'PASS' : 'FAIL'}<br>
                            Domain: ${data.domain_valid ? 'PASS' : 'FAIL'}<br>
                            SMTP: ${data.smtp_valid ? 'PASS' : 'FAIL'}<br>
                            Overall: ${data.is_valid ? 'PASS' : 'FAIL'}
                        </div>
                    `;
                } catch (e) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>API Error:</strong> Invalid JSON response<br>
                            <small>Raw response: ${text.substring(0, 200)}</small>
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Network Error:</strong> ${error.message}
                    </div>
                `;
            });
        }
        
        function testSystemFunctions() {
            const resultDiv = document.getElementById('system-test-result');
            resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Testing...';
            
            // Test if we can reach the system info endpoint
            fetch('api/validate-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ get_system_info: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.system_info) {
                    const info = data.system_info;
                    let html = '<div class="alert alert-info"><strong>System Functions:</strong><br>';
                    
                    Object.entries(info.functions || {}).forEach(([func, available]) => {
                        html += `${func}: ${available ? 'AVAILABLE' : 'MISSING'}<br>`;
                    });
                    
                    html += '</div>';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-warning">No system info available</div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            });
        }
    </script>
</body>
</html>