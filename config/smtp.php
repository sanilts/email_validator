<?php
// ===================================================================
// CONFIG/SMTP.PHP - SMTP Configuration
// ===================================================================
?>
<?php
class SMTPHandler {
    private $config;
    
    public function __construct($config = null) {
        $this->config = $config;
    }
    
    public function send($to, $subject, $body, $from = null) {
        if (!$this->config) {
            return $this->sendWithPHPMail($to, $subject, $body, $from);
        }
        
        return $this->sendWithSMTP($to, $subject, $body, $from);
    }
    
    private function sendWithPHPMail($to, $subject, $body, $from = null) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        
        if ($from) {
            $headers .= "From: $from\r\n";
        }
        
        return mail($to, $subject, $body, $headers);
    }
    
    private function sendWithSMTP($to, $subject, $body, $from = null) {
        try {
            $socket = fsockopen($this->config['host'], $this->config['port'], $errno, $errstr, 30);
            
            if (!$socket) {
                throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
            }
            
            // SMTP conversation
            $this->readResponse($socket);
            
            // EHLO
            $this->sendCommand($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            $this->readResponse($socket);
            
            // STARTTLS if needed
            if ($this->config['encryption'] === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                $this->readResponse($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                
                $this->sendCommand($socket, "EHLO " . $_SERVER['SERVER_NAME']);
                $this->readResponse($socket);
            }
            
            // AUTH LOGIN
            if (!empty($this->config['username'])) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->readResponse($socket);
                
                $this->sendCommand($socket, base64_encode($this->config['username']));
                $this->readResponse($socket);
                
                $this->sendCommand($socket, base64_encode($this->config['password']));
                $this->readResponse($socket);
            }
            
            // MAIL FROM
            $from_email = $from ?: $this->config['username'];
            $this->sendCommand($socket, "MAIL FROM: <$from_email>");
            $this->readResponse($socket);
            
            // RCPT TO
            $this->sendCommand($socket, "RCPT TO: <$to>");
            $this->readResponse($socket);
            
            // DATA
            $this->sendCommand($socket, "DATA");
            $this->readResponse($socket);
            
            // Email content
            $email_content = "To: $to\r\n";
            $email_content .= "From: $from_email\r\n";
            $email_content .= "Subject: $subject\r\n";
            $email_content .= "MIME-Version: 1.0\r\n";
            $email_content .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $email_content .= $body . "\r\n.\r\n";
            
            fwrite($socket, $email_content);
            $this->readResponse($socket);
            
            // QUIT
            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
    }
    
    private function readResponse($socket) {
        return fgets($socket, 512);
    }
    
    public function testConnection() {
        if (!$this->config) {
            return ['success' => true, 'message' => 'PHP mail() function available'];
        }
        
        try {
            $socket = fsockopen($this->config['host'], $this->config['port'], $errno, $errstr, 10);
            
            if (!$socket) {
                throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
            }
            
            fclose($socket);
            return ['success' => true, 'message' => 'SMTP connection successful'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>