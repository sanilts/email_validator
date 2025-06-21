<?php
// =============================================================================
// FIXED EMAIL SENDER CLASS (classes/EmailSender.php)
// =============================================================================

class EmailSender {
    private $db;
    private $config;
    private $userId;
    
    public function __construct($database, $userId) {
        $this->db = $database;
        $this->userId = $userId;
        $this->loadConfig($userId);
    }
    
    private function loadConfig($userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM smtp_settings WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to load SMTP config: " . $e->getMessage());
            $this->config = null;
        }
    }
    
    public function sendVerificationEmail($email, $verificationCode = null) {
        if (!$this->config) {
            throw new Exception('SMTP configuration not found. Please configure SMTP settings first.');
        }
        
        if (!$verificationCode) {
            $verificationCode = bin2hex(random_bytes(16));
        }
        
        $subject = 'Email Verification - Email Validator';
        $message = $this->buildVerificationMessage($email, $verificationCode);
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    public function sendVerificationEmailWithTemplate($email, $templateId) {
        if (!$this->config) {
            throw new Exception('SMTP configuration not found. Please configure SMTP settings first.');
        }
        
        // Load template
        $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE id = ? AND user_id = ?");
        $stmt->execute([$templateId, $this->userId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            throw new Exception('Email template not found');
        }
        
        $verificationCode = bin2hex(random_bytes(16));
        
        // Replace template variables
        $subject = $this->replaceTemplateVariables($template['subject'], $email, $verificationCode);
        $message = $this->replaceTemplateVariables($template['message'], $email, $verificationCode);
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    private function replaceTemplateVariables($text, $email, $verificationCode) {
        $replacements = [
            '{{verification_code}}' => $verificationCode,
            '{{email}}' => $email,
            '{{date}}' => date('Y-m-d'),
            '{{time}}' => date('H:i:s'),
            '{{datetime}}' => date('Y-m-d H:i:s')
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    private function buildVerificationMessage($email, $verificationCode) {
        return "Hello,\n\n" .
               "We are verifying the deliverability of this email address: $email\n\n" .
               "Verification Code: $verificationCode\n\n" .
               "This is an automated email from our Email Validation System.\n\n" .
               "If you did not request this verification, please ignore this email.\n\n" .
               "Best regards,\n" .
               "Email Validation Team\n\n" .
               "Generated on: " . date('Y-m-d H:i:s');
    }
    
    private function sendEmail($to, $subject, $message) {
        try {
            switch ($this->config['provider_type']) {
                case 'php_mail':
                    return $this->sendWithPhpMail($to, $subject, $message);
                    
                case 'smtp':
                    return $this->sendWithSmtp($to, $subject, $message);
                    
                case 'postmark':
                    return $this->sendWithPostmark($to, $subject, $message);
                    
                case 'sendgrid':
                    return $this->sendWithSendGrid($to, $subject, $message);
                    
                case 'mailgun':
                    return $this->sendWithMailgun($to, $subject, $message);
                    
                default:
                    throw new Exception('Unsupported email provider: ' . $this->config['provider_type']);
            }
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendWithPhpMail($to, $subject, $message) {
        $headers = [];
        
        if ($this->config['from_name']) {
            $headers[] = 'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>';
        } else {
            $headers[] = 'From: ' . $this->config['from_email'];
        }
        
        $headers[] = 'Reply-To: ' . $this->config['from_email'];
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'X-Mailer: Email Validator System';
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    private function sendWithSmtp($to, $subject, $message) {
        if (!function_exists('fsockopen')) {
            throw new Exception('fsockopen function is not available');
        }
        
        $host = $this->config['smtp_host'];
        $port = $this->config['smtp_port'] ?: 587;
        $username = $this->config['smtp_username'];
        $password = $this->config['smtp_password'];
        
        try {
            // Connect to SMTP server
            $socket = fsockopen($host, $port, $errno, $errstr, 30);
            if (!$socket) {
                throw new Exception("Cannot connect to SMTP server: $errno - $errstr");
            }
            
            // Read greeting
            $this->smtpRead($socket, '220');
            
            // EHLO
            $this->smtpCommand($socket, 'EHLO ' . $host, '250');
            
            // STARTTLS (if port 587)
            if ($port == 587) {
                $this->smtpCommand($socket, 'STARTTLS', '220');
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Failed to enable TLS encryption');
                }
                $this->smtpCommand($socket, 'EHLO ' . $host, '250');
            }
            
            // Authentication
            if ($username && $password) {
                $this->smtpCommand($socket, 'AUTH LOGIN', '334');
                $this->smtpCommand($socket, base64_encode($username), '334');
                $this->smtpCommand($socket, base64_encode($password), '235');
            }
            
            // Send email
            $this->smtpCommand($socket, 'MAIL FROM: <' . $this->config['from_email'] . '>', '250');
            $this->smtpCommand($socket, 'RCPT TO: <' . $to . '>', '250');
            $this->smtpCommand($socket, 'DATA', '354');
            
            // Email headers and body
            $emailData = $this->buildEmailData($to, $subject, $message);
            $this->smtpCommand($socket, $emailData . "\r\n.", '250');
            
            // Quit
            $this->smtpCommand($socket, 'QUIT', '221');
            
            fclose($socket);
            return true;
            
        } catch (Exception $e) {
            if (isset($socket) && is_resource($socket)) {
                fclose($socket);
            }
            throw new Exception('SMTP Error: ' . $e->getMessage());
        }
    }
    
    private function smtpCommand($socket, $command, $expectedCode) {
        fputs($socket, $command . "\r\n");
        return $this->smtpRead($socket, $expectedCode);
    }
    
    private function smtpRead($socket, $expectedCode) {
        $response = fgets($socket, 1024);
        $responseCode = substr($response, 0, 3);
        
        if ($responseCode != $expectedCode) {
            throw new Exception("SMTP Error: Expected $expectedCode, got $responseCode - $response");
        }
        
        return $response;
    }
    
    private function buildEmailData($to, $subject, $message) {
        $headers = [];
        
        if ($this->config['from_name']) {
            $headers[] = 'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>';
        } else {
            $headers[] = 'From: ' . $this->config['from_email'];
        }
        
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'Message-ID: <' . time() . '.' . rand() . '@' . $this->config['smtp_host'] . '>';
        $headers[] = 'X-Mailer: Email Validator System';
        
        return implode("\r\n", $headers) . "\r\n\r\n" . $message;
    }
    
    private function sendWithPostmark($to, $subject, $message) {
        if (!$this->config['api_key']) {
            throw new Exception('Postmark API key is required');
        }
        
        $data = [
            'From' => $this->config['from_email'],
            'To' => $to,
            'Subject' => $subject,
            'TextBody' => $message
        ];
        
        if ($this->config['from_name']) {
            $data['From'] = $this->config['from_name'] . ' <' . $this->config['from_email'] . '>';
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.postmarkapp.com/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Postmark-Server-Token: ' . $this->config['api_key']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Postmark cURL error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            $responseData = json_decode($response, true);
            $errorMessage = isset($responseData['Message']) ? $responseData['Message'] : 'Unknown error';
            throw new Exception('Postmark API error: ' . $errorMessage . ' (HTTP ' . $httpCode . ')');
        }
        
        return true;
    }
    
    private function sendWithSendGrid($to, $subject, $message) {
        if (!$this->config['api_key']) {
            throw new Exception('SendGrid API key is required');
        }
        
        $fromEmail = ['email' => $this->config['from_email']];
        if ($this->config['from_name']) {
            $fromEmail['name'] = $this->config['from_name'];
        }
        
        $data = [
            'personalizations' => [[
                'to' => [['email' => $to]]
            ]],
            'from' => $fromEmail,
            'subject' => $subject,
            'content' => [[
                'type' => 'text/plain',
                'value' => $message
            ]]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['api_key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('SendGrid cURL error: ' . $error);
        }
        
        if ($httpCode !== 202) {
            $responseData = json_decode($response, true);
            $errorMessage = 'Unknown error';
            if (isset($responseData['errors']) && is_array($responseData['errors'])) {
                $errorMessage = implode(', ', array_column($responseData['errors'], 'message'));
            }
            throw new Exception('SendGrid API error: ' . $errorMessage . ' (HTTP ' . $httpCode . ')');
        }
        
        return true;
    }
    
    private function sendWithMailgun($to, $subject, $message) {
        if (!$this->config['api_key'] || !$this->config['smtp_host']) {
            throw new Exception('Mailgun API key and domain are required');
        }
        
        $domain = $this->config['smtp_host']; // Using smtp_host field as domain
        
        $data = [
            'from' => $this->config['from_name'] ? 
                     $this->config['from_name'] . ' <' . $this->config['from_email'] . '>' : 
                     $this->config['from_email'],
            'to' => $to,
            'subject' => $subject,
            'text' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "api:" . $this->config['api_key']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Mailgun cURL error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            $responseData = json_decode($response, true);
            $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
            throw new Exception('Mailgun API error: ' . $errorMessage . ' (HTTP ' . $httpCode . ')');
        }
        
        return true;
    }
    
    public function testConnection() {
        if (!$this->config) {
            throw new Exception('SMTP configuration not found');
        }
        
        try {
            // Send a test email to the configured from_email address
            $subject = 'SMTP Test - Email Validator';
            $message = "This is a test email to verify SMTP configuration.\n\n" .
                      "Provider: " . $this->config['provider_type'] . "\n" .
                      "Test performed on: " . date('Y-m-d H:i:s') . "\n\n" .
                      "If you receive this email, your SMTP configuration is working correctly.";
            
            return $this->sendEmail($this->config['from_email'], $subject, $message);
            
        } catch (Exception $e) {
            throw new Exception('SMTP test failed: ' . $e->getMessage());
        }
    }
}