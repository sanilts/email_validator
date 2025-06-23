<?php
// index.php - Simple working version
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if we can start
try {
    if (file_exists('config/config.php')) {
        require_once 'config/config.php';
    } else {
        throw new Exception('Config file missing');
    }
} catch (Exception $e) {
    die('Configuration Error: ' . $e->getMessage() . '<br><a href="error_check.php">Run Diagnostics</a>');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

// Simple redirect based on role
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin/index.php');
} else {
    header('Location: user/dashboard.php');
}
exit();
?>