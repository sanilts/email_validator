<?php
// =============================================================================
// TEST SMTP API (api/test-smtp.php)
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
        throw new Exception('Provider type required');
    }
    
    $success = false;
    $message = '';
    
    switch ($providerType) {
        case 'php_mail':
            $success = testPhpMail($input);
            break;
            
        case 'smtp':
            $success = testSmtp($input);
            break;
            
        case 'postmark':
            $success = testPostmark($input);
            break;
            
        case 'sendgrid':
            $success = testSendGrid($input);
            break;
            
        case 'mailgun':
            $success = testMailgun($input);
            break;
            
        default:
            throw new Exception('Unsupported provider type');
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'SMTP test successful' : 'SMTP test failed'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function testPhpMail($config) {
    $to = $config['from_email'];
    $subject = 'SMTP Test';
    $message = 'This is a test email from Email Validator.';
    $headers = 'From: ' . $config['from_email'];
    
    return mail($to, $subject, $message, $headers);
}

function testSmtp($config) {
    try {
        $socket = fsockopen($config['smtp_host'], $config['smtp_port'], $errno, $errstr, 10);
        
        if (!$socket) {
            return false;
        }
        
        $response = fgets($socket);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return false;
        }
        
        // Basic SMTP handshake
        fputs($socket, "EHLO localhost\r\n");
        $response = fgets($socket);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return substr($response, 0, 3) == '250';
        
    } catch (Exception $e) {
        return false;
    }
}

function testPostmark($config) {
    $apiKey = $config['api_key'] ?? '';
    
    if (empty($apiKey)) {
        return false;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.postmarkapp.com/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Postmark-Server-Token: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'From' => $config['from_email'],
        'To' => $config['from_email'],
        'Subject' => 'SMTP Test',
        'TextBody' => 'This is a test email from Email Validator.'
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}

function testSendGrid($config) {
    $apiKey = $config['api_key'] ?? '';
    
    if (empty($apiKey)) {
        return false;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'personalizations' => [[
            'to' => [['email' => $config['from_email']]]
        ]],
        'from' => ['email' => $config['from_email']],
        'subject' => 'SMTP Test',
        'content' => [[
            'type' => 'text/plain',
            'value' => 'This is a test email from Email Validator.'
        ]]
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 202;
}

function testMailgun($config) {
    $apiKey = $config['api_key'] ?? '';
    $domain = $config['smtp_host'] ?? ''; // Use smtp_host as domain for Mailgun
    
    if (empty($apiKey) || empty($domain)) {
        return false;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, "api:$apiKey");
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'from' => $config['from_email'],
        'to' => $config['from_email'],
        'subject' => 'SMTP Test',
        'text' => 'This is a test email from Email Validator.'
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}
?>