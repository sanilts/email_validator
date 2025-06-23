<?php
// ===================================================================
// INCLUDES/SMTP_HANDLER.PHP - Enhanced SMTP Handler
// ===================================================================
?>
<?php
require_once __DIR__ . '/../config/smtp.php';

class EnhancedSMTPHandler extends SMTPHandler {
    private $db;
    
    public function __construct($db, $config = null) {
        parent::__construct($config);
        $this->db = $db;
    }
    
    public function getUserSMTPConfig($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM smtp_configs WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function sendVerificationEmail($user_id, $to, $verification_code) {
        $smtp_config = $this->getUserSMTPConfig($user_id);
        
        // Get email template
        $template = $this->getVerificationTemplate($user_id);
        
        $variables = [
            '{{email}}' => $to,
            '{{verification_code}}' => $verification_code,
            '{{date}}' => date('Y-m-d'),
            '{{time}}' => date('H:i:s'),
            '{{company_name}}' => get_system_setting('company_name', APP_NAME)
        ];
        
        $subject = $this->processTemplate($template['subject'], $variables);
        $body = $this->processTemplate($template['body'], $variables);
        
        if ($smtp_config) {
            $this->config = $smtp_config;
        }
        
        return $this->send($to, $subject, $body);
    }
    
    private function getVerificationTemplate($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM email_templates 
                WHERE user_id = ? AND template_type = 'verification' 
                ORDER BY is_default DESC, created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $template = $stmt->fetch();
            
            if (!$template) {
                // Return default template
                return [
                    'subject' => 'Email Verification Code',
                    'body' => '
                        <h2>Email Verification</h2>
                        <p>Hello,</p>
                        <p>Your verification code is: <strong>{{verification_code}}</strong></p>
                        <p>This code will expire in 10 minutes.</p>
                        <p>Best regards,<br>{{company_name}}</p>
                    '
                ];
            }
            
            return $template;
        } catch (Exception $e) {
            // Return default template on error
            return [
                'subject' => 'Email Verification Code',
                'body' => '
                    <h2>Email Verification</h2>
                    <p>Hello,</p>
                    <p>Your verification code is: <strong>{{verification_code}}</strong></p>
                    <p>This code will expire in 10 minutes.</p>
                    <p>Best regards,<br>{{company_name}}</p>
                '
            ];
        }
    }
    
    private function processTemplate($template, $variables) {
        $processed = $template;
        foreach ($variables as $key => $value) {
            $processed = str_replace($key, $value, $processed);
        }
        return $processed;
    }
    
    public function testUserSMTPConfig($user_id) {
        $config = $this->getUserSMTPConfig($user_id);
        
        if (!$config) {
            return ['success' => false, 'message' => 'No SMTP configuration found'];
        }
        
        $this->config = $config;
        return $this->testConnection();
    }
}
?>