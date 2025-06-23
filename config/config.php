<?php
// config/config.php - Minimal configuration
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Email Validator');
}

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database config
if (file_exists(__DIR__ . '/database.php')) {
    require_once __DIR__ . '/database.php';
}

// Basic functions
if (!function_exists('redirect')) {
    function redirect($url) {
        if (!headers_sent()) {
            header("Location: $url");
            exit();
        }
    }
}

if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}
?>