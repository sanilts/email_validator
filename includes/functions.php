<?php
class EmailValidator {
    private $db;
    private $cache_duration_months;
    
    public function __construct($database) {
        $this->db = $database;
        $this->cache_duration_months = get_system_setting('cache_duration_months', 3);
    }
    
    public function validate_email($email, $user_id, $use_cache = true) {
        $email = strtolower(trim($email));
        
        // Check cache first
        if ($use_cache) {
            $cached = $this->get_cached_validation($email);
            if ($cached) {
                return $cached;
            }
        }
        
        $result = [
            'email' => $email,
            'status' => 'unknown',
            'format_valid' => false,
            'dns_valid' => false,
            'smtp_valid' => false,
            'is_disposable' => false,
            'is_role_based' => false,
            'is_catch_all' => false,
            'risk_score' => 0,
            'details' => []
        ];
        
        try {
            // 1. Format validation
            $result['format_valid'] = $this->validate_format($email);
            if (!$result['format_valid']) {
                $result['status'] = 'invalid';
                $result['details'][] = 'Invalid email format';
                $this->cache_result($email, $user_id, $result);
                return $result;
            }
            
            // 2. Extract domain
            $domain = substr(strrchr($email, "@"), 1);
            
            // 3. Check if disposable
            $result['is_disposable'] = $this->is_disposable_email($domain);
            if ($result['is_disposable']) {
                $result['risk_score'] += 50;
                $result['details'][] = 'Disposable email provider';
            }
            
            // 4. Check if role-based
            $result['is_role_based'] = $this->is_role_based_email($email);
            if ($result['is_role_based']) {
                $result['risk_score'] += 30;
                $result['details'][] = 'Role-based email address';
            }
            
            // 5. DNS validation
            $result['dns_valid'] = $this->validate_dns($domain);
            if (!$result['dns_valid']) {
                $result['status'] = 'invalid';
                $result['details'][] = 'Domain does not exist or has no mail records';
                $this->cache_result($email, $user_id, $result);
                return $result;
            }
            
            // 6. SMTP validation
            $smtp_result = $this->validate_smtp($email, $domain);
            $result['smtp_valid'] = $smtp_result['valid'];
            $result['is_catch_all'] = $smtp_result['catch_all'];
            
            if ($result['is_catch_all']) {
                $result['risk_score'] += 20;
                $result['details'][] = 'Domain appears to accept all emails';
            }
            
            // 7. Determine final status
            if ($result['format_valid'] && $result['dns_valid'] && $result['smtp_valid']) {
                if ($result['risk_score'] > 70) {
                    $result['status'] = 'risky';
                } else {
                    $result['status'] = 'valid';
                }
            } else {
                $result['status'] = 'invalid';
            }
            
            // Cache the result
            $this->cache_result($email, $user_id, $result);
            
        } catch (Exception $e) {
            $result['details'][] = 'Validation error: ' . $e->getMessage();
            error_log("Email validation error for $email: " . $e->getMessage());
        }
        
        return $result;
    }
    
    private function validate_format($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Additional regex validation
        $pattern = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';
        return preg_match($pattern, $email);
    }
    
    private function validate_dns($domain) {
        // Check for MX records
        if (checkdnsrr($domain, 'MX')) {
            return true;
        }
        
        // Fallback: check for A records
        if (checkdnsrr($domain, 'A')) {
            return true;
        }
        
        // Fallback: check for AAAA records
        if (checkdnsrr($domain, 'AAAA')) {
            return true;
        }
        
        return false;
    }
    
    private function validate_smtp($email, $domain) {
        $result = ['valid' => false, 'catch_all' => false];
        
        // Get MX records
        $mx_hosts = [];
        if (getmxrr($domain, $mx_hosts, $mx_weights)) {
            array_multisort($mx_weights, $mx_hosts);
        } else {
            $mx_hosts = [$domain];
        }
        
        // Try different ports
        $ports = [25, 587, 465, 2525];
        
        foreach ($mx_hosts as $mx_host) {
            foreach ($ports as $port) {
                try {
                    $connection = $this->test_smtp_connection($mx_host, $port, $email);
                    if ($connection['connected']) {
                        $result['valid'] = $connection['email_exists'];
                        $result['catch_all'] = $connection['catch_all'];
                        return $result;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // If SMTP fails, check against common domains whitelist
        $trusted_domains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 
            'aol.com', 'icloud.com', 'protonmail.com'
        ];
        
        if (in_array($domain, $trusted_domains)) {
            $result['valid'] = true;
        }
        
        return $result;
    }
    
    private function test_smtp_connection($host, $port, $email) {
        $result = ['connected' => false, 'email_exists' => false, 'catch_all' => false];
        
        $context = stream_context_create([
            'socket' => ['bindto' => '0:0']
        ]);
        
        $connection = @stream_socket_client(
            "$host:$port", 
            $errno, 
            $errstr, 
            VALIDATION_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$connection) {
            return $result;
        }
        
        $result['connected'] = true;
        
        try {
            // Read initial response
            $response = fgets($connection, 1024);
            
            // HELO command
            fwrite($connection, "HELO example.com\r\n");
            $response = fgets($connection, 1024);
            
            // MAIL FROM command
            fwrite($connection, "MAIL FROM: <test@example.com>\r\n");
            $response = fgets($connection, 1024);
            
            // RCPT TO command for the actual email
            fwrite($connection, "RCPT TO: <$email>\r\n");
            $response = fgets($connection, 1024);
            $email_code = substr($response, 0, 3);
            
            // Test with invalid email to check for catch-all
            $random_email = 'test' . rand(100000, 999999) . '@' . substr(strrchr($email, "@"), 1);
            fwrite($connection, "RCPT TO: <$random_email>\r\n");
            $catch_all_response = fgets($connection, 1024);
            $catch_all_code = substr($catch_all_response, 0, 3);
            
            // QUIT command
            fwrite($connection, "QUIT\r\n");
            
            $result['email_exists'] = in_array($email_code, ['250', '251']);
            $result['catch_all'] = in_array($catch_all_code, ['250', '251']);
            
        } catch (Exception $e) {
            error_log("SMTP test error: " . $e->getMessage());
        } finally {
            fclose($connection);
        }
        
        return $result;
    }
    
    private function is_disposable_email($domain) {
        $disposable_domains = [
            '10minutemail.com', 'tempmail.org', 'guerrillamail.com', 
            'mailinator.com', 'yopmail.com', 'temp-mail.org',
            'throwaway.email', 'getnada.com', 'fakemailgenerator.com'
        ];
        return in_array($domain, $disposable_domains);
    }
    
    private function is_role_based_email($email) {
        $role_prefixes = [
            'admin', 'administrator', 'info', 'support', 'help',
            'sales', 'marketing', 'noreply', 'no-reply', 'contact',
            'service', 'team', 'office', 'mail', 'email'
        ];
        
        $local_part = substr($email, 0, strpos($email, '@'));
        return in_array(strtolower($local_part), $role_prefixes);
    }
    
    private function get_cached_validation($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM email_validations 
                WHERE email = ? AND expires_at > NOW() 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$email]);
            $cached = $stmt->fetch();
            
            if ($cached) {
                return [
                    'email' => $cached['email'],
                    'status' => $cached['status'],
                    'format_valid' => (bool)$cached['format_valid'],
                    'dns_valid' => (bool)$cached['dns_valid'],
                    'smtp_valid' => (bool)$cached['smtp_valid'],
                    'is_disposable' => (bool)$cached['is_disposable'],
                    'is_role_based' => (bool)$cached['is_role_based'],
                    'is_catch_all' => (bool)$cached['is_catch_all'],
                    'risk_score' => $cached['risk_score'],
                    'details' => json_decode($cached['validation_details'], true) ?: [],
                    'cached' => true,
                    'validated_at' => $cached['created_at']
                ];
            }
        } catch (Exception $e) {
            error_log("Cache retrieval error: " . $e->getMessage());
        }
        
        return null;
    }
    
    private function cache_result($email, $user_id, $result) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_validations 
                (user_id, email, status, format_valid, dns_valid, smtp_valid, 
                 is_disposable, is_role_based, is_catch_all, risk_score, validation_details) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $email,
                $result['status'],
                $result['format_valid'],
                $result['dns_valid'],
                $result['smtp_valid'],
                $result['is_disposable'],
                $result['is_role_based'],
                $result['is_catch_all'],
                $result['risk_score'],
                json_encode($result['details'])
            ]);
        } catch (Exception $e) {
            error_log("Cache storage error: " . $e->getMessage());
        }
    }
    
    public function validate_bulk($emails, $user_id, $job_id = null) {
        $results = [];
        $total = count($emails);
        $processed = 0;
        
        foreach ($emails as $email) {
            $result = $this->validate_email($email, $user_id);
            $results[] = $result;
            $processed++;
            
            // Update job progress if job_id provided
            if ($job_id) {
                $this->update_job_progress($job_id, $processed, $result['status']);
            }
            
            // Small delay to prevent overwhelming servers
            usleep(100000); // 0.1 seconds
        }
        
        return $results;
    }
    
    private function update_job_progress($job_id, $processed, $status) {
        try {
            $update_field = '';
            if ($status === 'valid') {
                $update_field = 'valid_emails = valid_emails + 1,';
            } elseif ($status === 'invalid') {
                $update_field = 'invalid_emails = invalid_emails + 1,';
            }
            
            $stmt = $this->db->prepare("
                UPDATE bulk_jobs SET 
                $update_field
                processed_emails = ? 
                WHERE id = ?
            ");
            $stmt->execute([$processed, $job_id]);
        } catch (Exception $e) {
            error_log("Job progress update error: " . $e->getMessage());
        }
    }
}
?>