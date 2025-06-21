<?php
// =============================================================================
// FIXED SMTP TEST API (api/test-smtp.php)
// =============================================================================

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $providerType = $input['provider_type'] ?? '';
    
    if (empty($providerType)) {
        throw new Exception('Provider type is required');
    }
    
    $result = testSmtpConfiguration($input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function testSmtpConfiguration($config) {
    $providerType = $config['provider_type'];
    $testResults = [
        'success' => false,
        'provider' => $providerType,
        'tests' => [],
        'message' => '',
        'details' => []
    ];
    
    try {
        switch ($providerType) {
            case 'php_mail':
                $result = testPhpMail($config);
                break;
                
            case 'smtp':
                $result = testCustomSmtp($config);
                break;
                
            case 'postmark':
                $result = testPostmark($config);
                break;
                
            case 'sendgrid':
                $result = testSendGrid($config);
                break;
                
            case 'mailgun':
                $result = testMailgun($config);
                break;
                
            default:
                throw new Exception('Unsupported provider type: ' . $providerType);
        }
        
        return array_merge($testResults, $result);
        
    } catch (Exception $e) {
        $testResults['error'] = $e->getMessage();
        return $testResults;
    }
}

function testPhpMail($config) {
    $tests = [];
    $details = [];
    
    // Test 1: Check if mail function exists
    $tests['mail_function'] = function_exists('mail');
    $details[] = 'mail() function: ' . ($tests['mail_function'] ? 'Available' : 'Not available');
    
    // Test 2: Check configuration
    $hasFromEmail = !empty($config['from_email']);
    $tests['from_email'] = $hasFromEmail;
    $details[] = 'From email: ' . ($hasFromEmail ? 'Configured' : 'Not configured');
    
    // Test 3: Validate from email
    $validFromEmail = filter_var($config['from_email'], FILTER_VALIDATE_EMAIL);
    $tests['valid_from_email'] = $validFromEmail !== false;
    $details[] = 'From email format: ' . ($validFromEmail ? 'Valid' : 'Invalid');
    
    $success = $tests['mail_function'] && $tests['from_email'] && $tests['valid_from_email'];
    
    if ($success) {
        // Try to send a test email
        try {
            $to = $config['from_email'];
            $subject = 'SMTP Test - PHP Mail';
            $message = 'This is a test email using PHP mail() function.';
            $headers = 'From: ' . $config['from_email'];
            
            if ($config['from_name']) {
                $headers = 'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>';
            }
            
            $mailSent = mail($to, $subject, $message, $headers);
            $tests['mail_sent'] = $mailSent;
            $details[] = 'Test email: ' . ($mailSent ? 'Sent successfully' : 'Failed to send');
            
            $success = $mailSent;
        } catch (Exception $e) {
            $tests['mail_sent'] = false;
            $details[] = 'Test email error: ' . $e->getMessage();
            $success = false;
        }
    }
    
    return [
        'success' => $success,
        'tests' => $tests,
        'details' => $details,
        'message' => $success ? 'PHP Mail test successful' : 'PHP Mail test failed'
    ];
}

function testCustomSmtp($config) {
    $tests = [];
    $details = [];
    
    // Test 1: Check required fields
    $hasHost = !empty($config['smtp_host']);
    $hasPort = !empty($config['smtp_port']);
    $hasFromEmail = !empty($config['from_email']);
    
    $tests['smtp_host'] = $hasHost;
    $tests['smtp_port'] = $hasPort;
    $tests['from_email'] = $hasFromEmail;
    
    $details[] = 'SMTP Host: ' . ($hasHost ? $config['smtp_host'] : 'Not configured');
    $details[] = 'SMTP Port: ' . ($hasPort ? $config['smtp_port'] : 'Not configured');
    $details[] = 'From Email: ' . ($hasFromEmail ? $config['from_email'] : 'Not configured');
    
    if (!($hasHost && $hasPort && $hasFromEmail)) {
        return [
            'success' => false,
            'tests' => $tests,
            'details' => $details,
            'message' => 'Missing required SMTP configuration'
        ];
    }
    
    // Test 2: Check if fsockopen is available
    $tests['fsockopen'] = function_exists('fsockopen');
    $details[] = 'fsockopen function: ' . ($tests['fsockopen'] ? 'Available' : 'Not available');
    
    if (!$tests['fsockopen']) {
        return [
            'success' => false,
            'tests' => $tests,
            'details' => $details,
            'message' => 'fsockopen function is not available'
        ];
    }
    
    // Test 3: Try to connect to SMTP server
    try {
        $host = $config['smtp_host'];
        $port = (int)$config['smtp_port'];
        
        $socket = fsockopen($host, $port, $errno, $errstr, 10);
        $tests['connection'] = $socket !== false;
        
        if ($socket) {
            $details[] = "Connection to $host:$port: Successful";
            
            // Test 4: Check SMTP greeting
            $response = fgets($socket, 1024);
            $greeting = substr($response, 0, 3) === '220';
            $tests['greeting'] = $greeting;
            $details[] = 'SMTP Greeting: ' . ($greeting ? 'Valid (' . trim($response) . ')' : 'Invalid');
            
            if ($greeting) {
                // Test 5: Try EHLO/HELO
                fputs($socket, "EHLO test.local\r\n");
                $response = fgets($socket, 1024);
                $ehlo = substr($response, 0, 3) === '250';
                $tests['ehlo'] = $ehlo;
                $details[] = 'EHLO Command: ' . ($ehlo ? 'Successful' : 'Failed');
                
                // Test 6: Check authentication if credentials provided
                if (!empty($config['smtp_username']) && !empty($config['smtp_password'])) {
                    fputs($socket, "AUTH LOGIN\r\n");
                    $response = fgets($socket, 1024);
                    $authSupported = substr($response, 0, 3) === '334';
                    $tests['auth_supported'] = $authSupported;
                    $details[] = 'Authentication: ' . ($authSupported ? 'Supported' : 'Not supported or failed');
                }
            }
            
            fputs($socket, "QUIT\r\n");
            fclose($socket);
        } else {
            $details[] = "Connection to $host:$port: Failed ($errno: $errstr)";
        }
    } catch (Exception $e) {
        $tests['connection'] = false;
        $details[] = 'Connection error: ' . $e->getMessage();
    }
    
    $success = $tests['connection'] && $tests['greeting'];
    
    return [
        'success' => $success,
        'tests' => $tests,
        'details' => $details,
        'message' => $success ? 'SMTP connection test successful' : 'SMTP connection test failed'
    ];
}

function testPostmark($config) {
    $tests = [];
    $details = [];
    
    // Test 1: Check API key
    $hasApiKey = !empty($config['api_key']);
    $tests['api_key'] = $hasApiKey;
    $details[] = 'API Key: ' . ($hasApiKey ? 'Configured' : 'Not configured');
    
    // Test 2: Check from email
    $hasFromEmail = !empty($config['from_email']);
    $tests['from_email'] = $hasFromEmail;
    $details[] = 'From Email: ' . ($hasFromEmail ? $config['from_email'] : 'Not configured');
    
    if (!($hasApiKey && $hasFromEmail)) {
        return [
            'success' => false,
            'tests' => $tests,
            'details' => $details,
            'message' => 'Missing required Postmark configuration'
        ];
    }
    
    // Test 3: Check cURL availability
    $tests['curl'] = function_exists('curl_init');
    $details[] = 'cURL: ' . ($tests['curl'] ? 'Available' : 'Not available');
    
    if (!$tests['curl']) {
        return [
            'success' => false,
            'tests' => $tests,
            'details' => $details,
            'message' => 'cURL is not available'
        ];
    }
    
    // Test 4: Test API connection
    try {
        $data = [
            'From' => $config['from_email'],
            'To' => $config['from_email'],
            'Subject' => 'Postmark SMTP Test',
            'TextBody' => 'This is a test email from Postmark API.'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.postmarkapp.com/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Postmark-Server-Token: ' . $config['api_key']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $tests['api_call'] = false;
            $details[] = 'API Call: Failed (cURL error: ' . $error . ')';
        } else {
            $tests['api_call'] = $httpCode === 200;
            $details[] = 'API Call: ' . ($httpCode === 200 ? 'Successful' : 'Failed (HTTP ' . $httpCode . ')');
            
            if ($httpCode !== 200) {
                $responseData = json_decode($response, true);
                if (isset($responseData['Message'])) {
                    $details[] = 'Error: ' . $responseData['Message'];
                }
            } else {
                $details[] = 'Test email sent successfully';
            }
        }
    } catch (Exception $e) {
        $tests['api_call'] = false;
        $details[] = 'API Call: Failed (' . $e->getMessage() . ')';
    }
    
    $success = $tests['api_call'] ?? false;
    
    return [
        'success' => $success,
        'tests' => $tests,
        'details' => $details,
        'message' => $success ? 'Postmark test successful' : 'Postmark test failed'
    ];
}

function testSendGrid($config) {
    $tests = [];
    $details = [];
    
    // Test 1: Check API key
    $hasApiKey = !empty($config['api_key']);
    $tests['api_key'] = $hasApiKey;
    $details[] = 'API Key: ' . ($hasApiKey ? 'Configured' : 'Not configured');
    
    // Test 2: Check from email
    $hasFromEmail = !empty($config['from_email']);
    $tests['from_email'] = $hasFromEmail;
    $details[] = 'From Email: ' . ($hasFromEmail ? $config['from_email'] : 'Not configured');
    
    if (!($hasApiKey && $hasFromEmail)) {
        return [
            'success' => false,
            'tests' => $tests,
            'details' => $details,
            'message' => 'Missing required SendGrid configuration'
        ];
    }
    
    // Test 3: Check cURL availability
    $tests['curl'] = function_exists('curl_init');
    $details[] = 'cURL: ' . ($tests['curl'] ? 'Available' : 'Not available');
    
    if (!$tests['curl']) {
        return [
            'success' => false,
            'tests' => $tests,
            'details' => $details,
            'message' => 'cURL is not available'
        ];
    }
    
    // Test 4: Test API connection
    try {
        $data = [
            'personalizations' => [[
                'to' => [['email' => $config['from_email']]]
            ]],
            'from' => ['email' => $config['from_email']],
            'subject' => 'SendGrid SMTP Test',
            'content' => [[
                'type' => 'text/plain',
                'value' => 'This is a test email from SendGrid API.'
            ]]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $tests['api_call'] = false;
            $details[] = 'API Call: Failed (cURL error: ' . $error . ')';
        } else {
            $tests['api_call'] = $httpCode === 202;
            $details[] = 'API Call: ' . ($httpCode === 202 ? 'Successful' : 'Failed (HTTP ' . $httpCode . ')');
            
            if ($httpCode !== 202) {
                $responseData = json_decode($response, true);
                if (isset($responseData['errors'])) {
                    $errors = array_column($responseData['errors'], 'message');
                    $details[] = 'Errors: ' . implode(', ', $errors);
                }
            } else {
                $details[] = 'Test email queued successfully';
            }
        }
    } catch (Exception $e) {
        $tests['api_call'] = false;
        $details[] = 'API Call: Failed (' . $e->getMessage() . ')';
    }
    
    $success = $tests['api_call'] ?? false;
    
    return [
        'success' => $success,
        'tests' => $tests,
        'details' => $details,
        'message' => $success ? 'SendGrid test successful' : 'SendGrid test failed'
    ];
}

function testMailgun($config) {
    $tests = [];
    $details = [];
    
    // Test 1: Check API key and domain
    $hasApiKey = !empty($config['api_key']);
    $hasDomain = !empty($config['smtp_host']); // Using smtp_host as domain
    $hasFromEmail = !empty($config['from_email']);
    
    $tests['api_key'] = $hasApiKey;
    $tests['domain'] = $hasDomain;
    $tests['from_email'] = $hasFromEmail;
    
    $details[] = 'API Key: ' . ($hasApiKey ? 'Configured' : 'Not configured');
    $details[] = 'Domain: ' . ($hasDomain ? $config['smtp_host'] : 'Not configured');
    $details[] = 'From Email: ' . ($hasFromEmail ? $config['from_email'] : 'Not configured');
    
    if (!($hasApiKey && $hasDomain && $hasFromEmail)) {
        return [
            'success' => false,
            'tests' => $tests,
            'details' => $details,
            'message' => 'Missing required Mailgun configuration'
        ];
    }
    
    // Test 2: Check cURL availability
    $tests['curl'] = function_exists('curl_init');
    $details[] = 'cURL: ' . ($tests['curl'] ? 'Available' : 'Not available');
    
    if (!$tests['curl']) {
        return [
            'success' => false,
            'tests' => $tests,
            'details' => $details,
            'message' => 'cURL is not available'
        ];
    }
    
    // Test 3: Test API connection
    try {
        $domain = $config['smtp_host'];
        
        $data = [
            'from' => $config['from_email'],
            'to' => $config['from_email'],
            'subject' => 'Mailgun SMTP Test',
            'text' => 'This is a test email from Mailgun API.'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "api:" . $config['api_key']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $tests['api_call'] = false;
            $details[] = 'API Call: Failed (cURL error: ' . $error . ')';
        } else {
            $tests['api_call'] = $httpCode === 200;
            $details[] = 'API Call: ' . ($httpCode === 200 ? 'Successful' : 'Failed (HTTP ' . $httpCode . ')');
            
            if ($httpCode !== 200) {
                $responseData = json_decode($response, true);
                if (isset($responseData['message'])) {
                    $details[] = 'Error: ' . $responseData['message'];
                }
            } else {
                $details[] = 'Test email sent successfully';
            }
        }
    } catch (Exception $e) {
        $tests['api_call'] = false;
        $details[] = 'API Call: Failed (' . $e->getMessage() . ')';
    }
    
    $success = $tests['api_call'] ?? false;
    
    return [
        'success' => $success,
        'tests' => $tests,
        'details' => $details,
        'message' => $success ? 'Mailgun test successful' : 'Mailgun test failed'
    ];
}
?>