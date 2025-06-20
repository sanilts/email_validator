<?php
// =============================================================================
// EMAIL SENDER CLASS (classes/EmailSender.php)
// =============================================================================

class EmailSender {
    private $db;
    private $config;
    
    public function __construct($database, $userId) {
        $this->db = $database;
        $this->loadConfig($userId);
    }
    
    private function loadConfig($userId) {
        $stmt = $this->db->prepare("SELECT * FROM smtp_settings WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function sendVerificationEmail($email) {
        if (!$this->config) {
            throw new Exception('SMTP configuration not found');
        }
        
        $verificationCode = bin2hex(random_bytes(16));
        $subject = 'Email Verification - Email Validator';
        $message = "Please click the link below to verify your email address:\n\n";
        $message .= "Verification Code: $verificationCode\n\n";
        $message .= "This is an automated email verification from Email Validator.";
        
        switch ($this->config['provider_type']) {
            case 'php_mail':
                return $this->sendWithPhpMail($email, $subject, $message);
                
            case 'smtp':
                return $this->sendWithSmtp($email, $subject, $message);
                
            case 'postmark':
                return $this->sendWithPostmark($email, $subject, $message);
                
            case 'sendgrid':
                return $this->sendWithSendGrid($email, $subject, $message);
                
            case 'mailgun':
                return $this->sendWithMailgun($email, $subject, $message);
                
            default:
                throw new Exception('Unsupported email provider');
        }
    }
    
    private function sendWithPhpMail($to, $subject, $message) {
        $headers = 'From: ' . $this->config['from_email'];
        if ($this->config['from_name']) {
            $headers = 'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>';
        }
        
        return mail($to, $subject, $message, $headers);
    }
    
    private function sendWithSmtp($to, $subject, $message) {
        // Basic SMTP implementation
        // In production, use a library like PHPMailer or SwiftMailer
        try {
            $socket = fsockopen($this->config['smtp_host'], $this->config['smtp_port'], $errno, $errstr, 30);
            
            if (!$socket) {
                return false;
            }
            
            // SMTP conversation
            $this->smtpCommand($socket, '', '220');
            $this->smtpCommand($socket, 'EHLO localhost', '250');
            
            if ($this->config['smtp_username']) {
                $this->smtpCommand($socket, 'AUTH LOGIN', '334');
                $this->smtpCommand($socket, base64_encode($this->config['smtp_username']), '334');
                $this->smtpCommand($socket, base64_encode($this->config['smtp_password']), '235');
            }
            
            $this->smtpCommand($socket, 'MAIL FROM: <' . $this->config['from_email'] . '>', '250');
            $this->smtpCommand($socket, 'RCPT TO: <' . $to . '>', '250');
            $this->smtpCommand($socket, 'DATA', '354');
            
            $emailData = "From: " . $this->config['from_email'] . "\r\n";
            $emailData .= "To: $to\r\n";
            $emailData .= "Subject: $subject\r\n";
            $emailData .= "\r\n";
            $emailData .= $message . "\r\n";
            $emailData .= ".\r\n";
            
            $this->smtpCommand($socket, $emailData, '250');
            $this->smtpCommand($socket, 'QUIT', '221');
            
            fclose($socket);
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function smtpCommand($socket, $command, $expectedCode) {
        if ($command !== '') {
            fputs($socket, $command . "\r\n");
        }
        
        $response = fgets($socket);
        
        if (substr($response, 0, 3) != $expectedCode) {
            throw new Exception("SMTP Error: Expected $expectedCode, got $response");
        }
        
        return $response;
    }
    
    private function sendWithPostmark($to, $subject, $message) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.postmarkapp.com/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Postmark-Server-Token: ' . $this->config['api_key']
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'From' => $this->config['from_email'],
            'To' => $to,
            'Subject' => $subject,
            'TextBody' => $message
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    }
    
    private function sendWithSendGrid($to, $subject, $message) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['api_key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'personalizations' => [[
                'to' => [['email' => $to]]
            ]],
            'from' => ['email' => $this->config['from_email']],
            'subject' => $subject,
            'content' => [[
                'type' => 'text/plain',
                'value' => $message
            ]]
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 202;
    }
    
    private function sendWithMailgun($to, $subject, $message) {
        $domain = $this->config['smtp_host']; // Use smtp_host as domain for Mailgun
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, "api:" . $this->config['api_key']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'from' => $this->config['from_email'],
            'to' => $to,
            'subject' => $subject,
            'text' => $message
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    }
}
?>