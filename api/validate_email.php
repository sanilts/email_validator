<?php
// ===================================================================
// UPDATED CONFIG/CONFIG.PHP - Fixed Version
// ===================================================================
?>
<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Application Constants
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Email Validator');
}

if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    if ($path === '/') $path = '';
    define('BASE_URL', $protocol . '://' . $host . $path . '/');
}

// File Upload Constants
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_BULK_EMAILS', 10000);
define('ALLOWED_FILE_TYPES', ['csv', 'txt']);

// Create uploads path dynamically
$upload_base = dirname(__DIR__) . '/assets/uploads/';
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', $upload_base);
}

// Validation Constants
define('VALIDATION_TIMEOUT', 10); // seconds
define('SMTP_TIMEOUT', 5); // seconds
define('SESSION_TIMEOUT', 3600); // 1 hour

// Include database config
if (file_exists(__DIR__ . '/database.php')) {
    require_once __DIR__ . '/database.php';
}

// Helper Functions
if (!function_exists('redirect')) {
    function redirect($url) {
        if (!headers_sent()) {
            header("Location: $url");
            exit();
        }
        echo "<script>window.location.href='$url';</script>";
        exit();
    }
}

if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('validate_email_format')) {
    function validate_email_format($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $pattern = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';
        return preg_match($pattern, $email);
    }
}

if (!function_exists('format_file_size')) {
    function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        if (!$datetime) return 'Never';
        $time = time() - strtotime($datetime);
        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60) . ' minutes ago';
        if ($time < 86400) return floor($time/3600) . ' hours ago';
        if ($time < 2592000) return floor($time/86400) . ' days ago';
        return date('M j, Y', strtotime($datetime));
    }
}

if (!function_exists('json_response')) {
    function json_response($data, $status_code = 200) {
        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('get_system_setting')) {
    function get_system_setting($key, $default = null) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting($key, $value, $description = null) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?, description = COALESCE(?, description)
            ");
            $stmt->execute([$key, $value, $description, $value, $description]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $details = null) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('check_rate_limit')) {
    function check_rate_limit($user_id, $action) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $limit = get_system_setting('rate_limit_per_hour', 1000);
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM activity_logs 
                WHERE user_id = ? AND action = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$user_id, $action]);
            $count = $stmt->fetch()['count'];
            
            return $count < $limit;
        } catch (Exception $e) {
            return true; // Allow if check fails
        }
    }
}

// Create upload directories if they don't exist
$upload_dirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'bulk/',
    UPLOAD_PATH . 'logo/'
];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>