<?php
// =============================================================================
// API ROUTER (api/index.php)
// =============================================================================

require_once '../config/database.php';
require_once '../classes/SecurityManager.php';

session_start();

// Security checks
$security = new SecurityManager(new Database());

// Check if IP is blocked
if ($security->isIpBlocked($_SERVER['REMOTE_ADDR'])) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many failed attempts. Please try again later.']);
    exit;
}

// CORS headers for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// API routing
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

// Remove 'api' from segments if present
if ($segments[0] === 'api') {
    array_shift($segments);
}

$endpoint = $segments[0] ?? '';

// Authentication required endpoints
$authRequired = [
    'validate-email', 'bulk-upload', 'dashboard-stats', 'get-results',
    'export-results', 'smtp-settings', 'users', 'activity-logs',
    'statistics', 'system-settings'
];

if (in_array($endpoint, $authRequired) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Admin required endpoints
$adminRequired = ['users', 'activity-logs', 'system-settings'];

if (in_array($endpoint, $adminRequired) && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

// Route to appropriate handler
switch ($endpoint) {
    case 'validate-email':
        require 'validate-email.php';
        break;
        
    case 'bulk-upload':
        require 'bulk-upload.php';
        break;
        
    case 'dashboard-stats':
        require 'dashboard-stats.php';
        break;
        
    case 'get-results':
        require 'get-results.php';
        break;
        
    case 'export-results':
        require 'export-results.php';
        break;
        
    case 'smtp-settings':
        require 'smtp-settings.php';
        break;
        
    case 'users':
        require 'users.php';
        break;
        
    case 'activity-logs':
        require 'activity-logs.php';
        break;
        
    case 'statistics':
        require 'statistics.php';
        break;
        
    case 'system-settings':
        require 'system-settings.php';
        break;
        
    case 'batch-progress':
        require 'batch-progress.php';
        break;
        
    case 'test-smtp':
        require 'test-smtp.php';
        break;
        
    case 'get-validation-details':
        require 'get-validation-details.php';
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
?>