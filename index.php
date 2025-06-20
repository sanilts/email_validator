<?php
// =============================================================================
// MAIN APPLICATION (index.php)
// =============================================================================

session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Validator Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --info-color: #0891b2;
            --light-bg: #f8fafc;
            --dark-bg: #0f172a;
            --light-card: #ffffff;
            --dark-card: #1e293b;
            --light-text: #334155;
            --dark-text: #e2e8f0;
        }

        [data-theme="dark"] {
            --bs-body-bg: var(--dark-bg);
            --bs-body-color: var(--dark-text);
        }

        [data-theme="dark"] .card {
            background-color: var(--dark-card);
            border-color: #334155;
        }

        [data-theme="dark"] .navbar {
            background-color: var(--dark-card) !important;
        }

        [data-theme="dark"] .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
        }

        .nav-pills .nav-link {
            color: rgba(255,255,255,0.8);
            margin-bottom: 0.5rem;
        }

        .nav-pills .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
        }

        .nav-pills .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }

        .stats-card {
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body data-theme="light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white"><i class="fas fa-envelope-check"></i> Email Validator</h4>
                        <small class="text-white-50">Welcome, <?= htmlspecialchars($user['username']) ?></small>
                    </div>
                    
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="#dashboard" class="nav-link active" data-section="dashboard">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#single-validation" class="nav-link" data-section="single-validation">
                                <i class="fas fa-envelope"></i> Single Email
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#bulk-validation" class="nav-link" data-section="bulk-validation">
                                <i class="fas fa-upload"></i> Bulk Upload
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#lists-management" class="nav-link" data-section="lists-management">
                                <i class="fas fa-list-alt"></i> Email Lists
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#email-templates" class="nav-link" data-section="email-templates">
                                <i class="fas fa-envelope-open-text"></i> Email Templates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#results" class="nav-link" data-section="results">
                                <i class="fas fa-chart-bar"></i> Results
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#smtp-settings" class="nav-link" data-section="smtp-settings">
                                <i class="fas fa-cog"></i> SMTP Settings
                            </a>
                        </li>
                        <?php if ($user['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a href="#user-management" class="nav-link" data-section="user-management">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#admin-validations" class="nav-link" data-section="admin-validations">
                                <i class="fas fa-chart-line"></i> All Validations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#activity-logs" class="nav-link" data-section="activity-logs">
                                <i class="fas fa-history"></i> Activity Logs
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="mt-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-white-50 small">Theme</span>
                            <label class="theme-switch">
                                <input type="checkbox" id="themeToggle">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <a href="logout.php" class="btn btn-outline-light btn-sm w-100">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="content-section">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title text-muted">Total Validations</h5>
                                            <h3 class="mb-0" id="total-validations">0</h3>
                                        </div>
                                        <div class="text-primary">
                                            <i class="fas fa-check-circle fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title text-muted">Valid Emails</h5>
                                            <h3 class="mb-0 text-success" id="valid-emails">0</h3>
                                        </div>
                                        <div class="text-success">
                                            <i class="fas fa-thumbs-up fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title text-muted">Invalid Emails</h5>
                                            <h3 class="mb-0 text-danger" id="invalid-emails">0</h3>
                                        </div>
                                        <div class="text-danger">
                                            <i class="fas fa-thumbs-down fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title text-muted">Success Rate</h5>
                                            <h3 class="mb-0 text-info" id="success-rate">0%</h3>
                                        </div>
                                        <div class="text-info">
                                            <i class="fas fa-chart-line fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-bar"></i> Recent Activity</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                    <th>Details</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="recent-activity">
                                                <tr>
                                                    <td colspan="4" class="text-center">No recent activity</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-info-circle"></i> Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary" data-section="single-validation">
                                            <i class="fas fa-envelope"></i> Validate Single Email
                                        </button>
                                        <button class="btn btn-success" data-section="bulk-validation">
                                            <i class="fas fa-upload"></i> Bulk Upload
                                        </button>
                                        <button class="btn btn-info" data-section="results">
                                            <i class="fas fa-download"></i> Export Results
                                        </button>
                                        <button class="btn btn-warning" data-section="smtp-settings">
                                            <i class="fas fa-cog"></i> SMTP Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Single Email Validation Section -->
                <div id="single-validation-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-envelope"></i> Single Email Validation</h1>
                    </div>

                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <form id="single-email-form">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" placeholder="Enter email address" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="single-email-list" class="form-label">Add to List (Optional)</label>
                                            <select class="form-select" id="single-email-list">
                                                <option value="">Select a list or leave empty</option>
                                            </select>
                                            <div class="form-text">You can add this validation to an existing list for better organization</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="single-email-template" class="form-label">Email Template</label>
                                            <select class="form-select" id="single-email-template">
                                                <option value="">Use default template</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="send-verification" checked>
                                                <label class="form-check-label" for="send-verification">
                                                    Send verification email
                                                </label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check"></i> Validate Email
                                        </button>
                                    </form>

                                    <div id="validation-result" class="mt-4 d-none">
                                        <h5>Validation Result</h5>
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-2">
                                                            <strong>Email:</strong> <span id="result-email"></span>
                                                        </div>
                                                        <div class="mb-2">
                                                            <strong>Format:</strong> <span id="result-format"></span>
                                                        </div>
                                                        <div class="mb-2">
                                                            <strong>Domain:</strong> <span id="result-domain"></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-2">
                                                            <strong>SMTP:</strong> <span id="result-smtp"></span>
                                                        </div>
                                                        <div class="mb-2">
                                                            <strong>Overall:</strong> <span id="result-overall"></span>
                                                        </div>
                                                        <div class="mb-2">
                                                            <strong>Verified:</strong> <span id="result-verified"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Validation Section -->
                <div id="bulk-validation-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-upload"></i> Bulk Email Validation</h1>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-file-upload"></i> Upload Email List</h5>
                                </div>
                                <div class="card-body">
                                    <form id="bulk-upload-form" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="csv-file" class="form-label">CSV File</label>
                                            <input type="file" class="form-control" id="csv-file" accept=".csv,.txt" required>
                                            <div class="form-text">Upload a CSV file with email addresses. One email per line or comma-separated.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="bulk-email-list" class="form-label">Add to List</label>
                                            <div class="input-group">
                                                <select class="form-select" id="bulk-email-list" required>
                                                    <option value="">Select existing list or create new</option>
                                                </select>
                                                <button class="btn btn-outline-secondary" type="button" id="create-new-list-btn">
                                                    <i class="fas fa-plus"></i> New List
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mb-3" id="new-list-fields" style="display: none;">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" id="new-list-name" placeholder="New list name">
                                                </div>
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" id="new-list-description" placeholder="List description (optional)">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="bulk-email-template" class="form-label">Email Template</label>
                                            <select class="form-select" id="bulk-email-template">
                                                <option value="">Use default template</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="bulk-send-verification" checked>
                                                <label class="form-check-label" for="bulk-send-verification">
                                                    Send verification emails
                                                </label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload"></i> Upload and Validate
                                        </button>
                                    </form>

                                    <div id="upload-progress" class="mt-4 d-none">
                                        <h6>Processing...</h6>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <div class="mt-2">
                                            <small id="progress-text">Starting validation...</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-info"></i> Guidelines</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> CSV format supported</li>
                                        <li><i class="fas fa-check text-success"></i> Maximum 10,000 emails</li>
                                        <li><i class="fas fa-check text-success"></i> One email per line</li>
                                        <li><i class="fas fa-check text-success"></i> Duplicates will be removed</li>
                                        <li><i class="fas fa-check text-success"></i> All emails added to selected list</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Lists Management Section -->
                <div id="lists-management-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-list-alt"></i> Email Lists Management</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createListModal">
                            <i class="fas fa-plus"></i> Create New List
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5><i class="fas fa-table"></i> Your Email Lists</h5>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="search-lists" placeholder="Search lists...">
                                                <button class="btn btn-outline-secondary" type="button">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>List Name</th>
                                                    <th>Description</th>
                                                    <th>Total Emails</th>
                                                    <th>Valid</th>
                                                    <th>Invalid</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="lists-table">
                                                <tr>
                                                    <td colspan="7" class="text-center">No lists found</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Templates Section -->
                <div id="email-templates-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-envelope-open-text"></i> Email Templates</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                            <i class="fas fa-plus"></i> Create New Template
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-envelope"></i> Verification Email Templates</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Template Name</th>
                                                    <th>Subject</th>
                                                    <th>Type</th>
                                                    <th>Default</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="templates-table">
                                                <tr>
                                                    <td colspan="6" class="text-center">No templates found</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Section -->
                <div id="results-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-list"></i> Validation Results</h1>
                        <div>
                            <button class="btn btn-success" id="export-results">
                                <i class="fas fa-download"></i> Export Results
                            </button>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><i class="fas fa-table"></i> Email Validation History</h5>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="search-emails" placeholder="Search emails...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Format</th>
                                            <th>Domain</th>
                                            <th>SMTP</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="results-table">
                                        <tr>
                                            <td colspan="7" class="text-center">No results found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <nav>
                                <ul class="pagination justify-content-center" id="results-pagination">
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- SMTP Settings Section -->
                <div id="smtp-settings-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-cog"></i> SMTP Settings</h1>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-envelope-open"></i> Email Configuration</h5>
                                </div>
                                <div class="card-body">
                                    <form id="smtp-settings-form">
                                        <div class="mb-3">
                                            <label for="provider-type" class="form-label">Provider Type</label>
                                            <select class="form-select" id="provider-type" required>
                                                <option value="">Select Provider</option>
                                                <option value="php_mail">PHP Mail</option>
                                                <option value="smtp">Custom SMTP</option>
                                                <option value="postmark">Postmark</option>
                                                <option value="sendgrid">SendGrid</option>
                                                <option value="mailgun">Mailgun</option>
                                            </select>
                                        </div>

                                        <div id="smtp-fields" class="d-none">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="smtp-host" class="form-label">SMTP Host</label>
                                                        <input type="text" class="form-control" id="smtp-host" placeholder="smtp.example.com">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="smtp-port" class="form-label">SMTP Port</label>
                                                        <input type="number" class="form-control" id="smtp-port" value="587">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="smtp-username" class="form-label">Username</label>
                                                        <input type="text" class="form-control" id="smtp-username">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="smtp-password" class="form-label">Password</label>
                                                        <input type="password" class="form-control" id="smtp-password">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="api-fields" class="d-none">
                                            <div class="mb-3">
                                                <label for="api-key" class="form-label">API Key</label>
                                                <input type="text" class="form-control" id="api-key">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="from-email" class="form-label">From Email</label>
                                                    <input type="email" class="form-control" id="from-email" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="from-name" class="form-label">From Name</label>
                                                    <input type="text" class="form-control" id="from-name">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <button type="button" class="btn btn-outline-primary" id="test-smtp">
                                                <i class="fas fa-vial"></i> Test Connection
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-list"></i> Provider Presets</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-primary preset-btn" data-preset="gmail">
                                            <i class="fab fa-google"></i> Gmail
                                        </button>
                                        <button class="btn btn-outline-primary preset-btn" data-preset="outlook">
                                            <i class="fab fa-microsoft"></i> Outlook
                                        </button>
                                        <button class="btn btn-outline-primary preset-btn" data-preset="yahoo">
                                            <i class="fab fa-yahoo"></i> Yahoo
                                        </button>
                                        <button class="btn btn-outline-primary preset-btn" data-preset="postmark">
                                            <i class="fas fa-paper-plane"></i> Postmark
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($user['role'] == 'admin'): ?>
                <!-- User Management Section -->
                <div id="user-management-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-users"></i> User Management</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="users-table">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Validations View Section -->
                <div id="admin-validations-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-chart-line"></i> All User Validations</h1>
                        <div>
                            <button class="btn btn-success" id="export-all-validations">
                                <i class="fas fa-download"></i> Export All
                            </button>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select class="form-select" id="filter-user">
                                <option value="">All Users</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filter-list">
                                <option value="">All Lists</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filter-status">
                                <option value="">All Statuses</option>
                                <option value="1">Valid Only</option>
                                <option value="0">Invalid Only</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control" id="filter-date" placeholder="Filter by date">
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><i class="fas fa-table"></i> Validation Records</h5>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="search-admin-emails" placeholder="Search emails...">
                                        <button class="btn btn-outline-secondary" type="button" id="apply-filters">
                                            <i class="fas fa-search"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Email</th>
                                            <th>List</th>
                                            <th>Status</th>
                                            <th>Format</th>
                                            <th>Domain</th>
                                            <th>SMTP</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="admin-validations-table">
                                        <tr>
                                            <td colspan="9" class="text-center">No validations found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <nav>
                                <ul class="pagination justify-content-center" id="admin-pagination">
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Activity Logs Section -->
                <div id="activity-logs-section" class="content-section d-none">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="fas fa-history"></i> Activity Logs</h1>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody id="logs-table">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="add-user-form">
                        <div class="mb-3">
                            <label for="new-username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="new-username" required>
                        </div>
                        <div class="mb-3">
                            <label for="new-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="new-email" required>
                        </div>
                        <div class="mb-3">
                            <label for="new-password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="new-password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new-role" class="form-label">Role</label>
                            <select class="form-select" id="new-role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-user">
                        <i class="fas fa-save"></i> Save User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create List Modal -->
    <div class="modal fade" id="createListModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-list-alt"></i> Create New Email List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="create-list-form">
                        <div class="mb-3">
                            <label for="list-name" class="form-label">List Name</label>
                            <input type="text" class="form-control" id="list-name" required placeholder="Enter list name">
                        </div>
                        <div class="mb-3">
                            <label for="list-description" class="form-label">Description</label>
                            <textarea class="form-control" id="list-description" rows="3" placeholder="Optional description for this list"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-list">
                        <i class="fas fa-save"></i> Create List
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Template Modal -->
    <div class="modal fade" id="createTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope-open-text"></i> Create Email Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="create-template-form">
                        <div class="mb-3">
                            <label for="template-name" class="form-label">Template Name</label>
                            <input type="text" class="form-control" id="template-name" required placeholder="Enter template name">
                        </div>
                        <div class="mb-3">
                            <label for="template-subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="template-subject" required placeholder="Email subject line">
                        </div>
                        <div class="mb-3">
                            <label for="template-message" class="form-label">Email Message</label>
                            <textarea class="form-control" id="template-message" rows="8" required placeholder="Email message content"></textarea>
                            <div class="form-text">
                                Available variables: {{verification_code}}, {{email}}, {{date}}, {{time}}
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="template-type" class="form-label">Template Type</label>
                            <select class="form-select" id="template-type" required>
                                <option value="verification">Verification</option>
                                <option value="notification">Notification</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="template-default">
                                <label class="form-check-label" for="template-default">
                                    Set as default template
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-template">
                        <i class="fas fa-save"></i> Create Template
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- List Download Options Modal -->
    <div class="modal fade" id="downloadListModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-download"></i> Download List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Choose what to download from list: <strong id="download-list-name"></strong></p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary download-option" data-type="all">
                            <i class="fas fa-list"></i> All Emails with Status
                        </button>
                        <button class="btn btn-outline-success download-option" data-type="valid">
                            <i class="fas fa-check"></i> Valid Emails Only
                        </button>
                        <button class="btn btn-outline-danger download-option" data-type="invalid">
                            <i class="fas fa-times"></i> Invalid Emails Only
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Template Modal -->
    <div class="modal fade" id="editTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Email Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-template-form">
                        <input type="hidden" id="edit-template-id">
                        <div class="mb-3">
                            <label for="edit-template-name" class="form-label">Template Name</label>
                            <input type="text" class="form-control" id="edit-template-name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-template-subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="edit-template-subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-template-message" class="form-label">Email Message</label>
                            <textarea class="form-control" id="edit-template-message" rows="8" required></textarea>
                            <div class="form-text">
                                Available variables: {{verification_code}}, {{email}}, {{date}}, {{time}}
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit-template-default">
                                <label class="form-check-label" for="edit-template-default">
                                    Set as default template
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="update-template">
                        <i class="fas fa-save"></i> Update Template
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        body.setAttribute('data-theme', savedTheme);
        themeToggle.checked = savedTheme === 'dark';

        themeToggle.addEventListener('change', function() {
            const theme = this.checked ? 'dark' : 'light';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });

        // Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link[data-section]');
            const sections = document.querySelectorAll('.content-section');
            const quickActions = document.querySelectorAll('button[data-section]');

            function showSection(sectionName) {
                // Hide all sections
                sections.forEach(section => section.classList.add('d-none'));
                
                // Show target section
                const targetSection = document.getElementById(sectionName + '-section');
                if (targetSection) {
                    targetSection.classList.remove('d-none');
                }

                // Update active nav link
                navLinks.forEach(link => link.classList.remove('active'));
                const activeLink = document.querySelector(`[data-section="${sectionName}"]`);
                if (activeLink && activeLink.classList.contains('nav-link')) {
                    activeLink.classList.add('active');
                }

                // Load section data
                loadSectionData(sectionName);
            }

            // Add click listeners to nav links
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const section = this.getAttribute('data-section');
                    showSection(section);
                });
            });

            // Add click listeners to quick action buttons
            quickActions.forEach(button => {
                button.addEventListener('click', function() {
                    const section = this.getAttribute('data-section');
                    showSection(section);
                });
            });

            // Load dashboard data initially
            loadSectionData('dashboard');
        });

        function loadSectionData(section) {
            switch(section) {
                case 'dashboard':
                    loadDashboardStats();
                    break;
                case 'results':
                    loadResults();
                    break;
                case 'user-management':
                    loadUsers();
                    break;
                case 'activity-logs':
                    loadActivityLogs();
                    break;
            }
        }

        function loadDashboardStats() {
            // Simulate API call to get dashboard stats
            fetch('api/dashboard-stats.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('total-validations').textContent = data.total || 0;
                    document.getElementById('valid-emails').textContent = data.valid || 0;
                    document.getElementById('invalid-emails').textContent = data.invalid || 0;
                    document.getElementById('success-rate').textContent = (data.successRate || 0) + '%';
                })
                .catch(error => console.error('Error loading dashboard stats:', error));
        }

        function loadResults() {
            // Load validation results
            fetch('api/get-results.php')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('results-table');
                    tbody.innerHTML = '';
                    
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No results found</td></tr>';
                        return;
                    }

                    data.forEach(result => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${result.email}</td>
                            <td><span class="badge bg-${result.is_valid ? 'success' : 'danger'}">${result.is_valid ? 'Valid' : 'Invalid'}</span></td>
                            <td><span class="badge bg-${result.format_valid ? 'success' : 'danger'}">${result.format_valid ? 'Valid' : 'Invalid'}</span></td>
                            <td><span class="badge bg-${result.domain_valid ? 'success' : 'danger'}">${result.domain_valid ? 'Valid' : 'Invalid'}</span></td>
                            <td><span class="badge bg-${result.smtp_valid ? 'success' : 'danger'}">${result.smtp_valid ? 'Valid' : 'Invalid'}</span></td>
                            <td>${new Date(result.validation_date).toLocaleDateString()}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="viewDetails('${result.id}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        `;
                    });
                })
                .catch(error => console.error('Error loading results:', error));
        }

        // Single email validation
        document.getElementById('single-email-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const sendVerification = document.getElementById('send-verification').checked;
            const listId = document.getElementById('single-email-list').value;
            const templateId = document.getElementById('single-email-template').value;
            
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
            
            fetch('api/validate-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    sendVerification: sendVerification,
                    listId: listId,
                    templateId: templateId
                })
            })
            .then(response => response.json())
            .then(data => {
                displayValidationResult(data);
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-check"></i> Validate Email';
                
                // Refresh lists if email was added to a list
                if (listId) {
                    loadEmailLists();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-check"></i> Validate Email';
            });
        });

        // Bulk upload functionality
        document.getElementById('bulk-upload-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('csv-file');
            const file = fileInput.files[0];
            const listId = document.getElementById('bulk-email-list').value;
            const templateId = document.getElementById('bulk-email-template').value;
            const newListName = document.getElementById('new-list-name').value;
            const newListDescription = document.getElementById('new-list-description').value;
            
            if (!file) {
                showAlert('Please select a CSV file', 'warning');
                return;
            }
            
            if (!listId && !newListName) {
                showAlert('Please select a list or create a new one', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('send_verification', document.getElementById('bulk-send-verification').checked);
            formData.append('list_id', listId);
            formData.append('template_id', templateId);
            
            if (listId === 'new' || !listId) {
                formData.append('new_list_name', newListName);
                formData.append('new_list_description', newListDescription);
            }
            
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            
            // Show progress bar
            const progressDiv = document.getElementById('upload-progress');
            progressDiv.classList.remove('d-none');
            
            fetch('api/bulk-upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    fileInput.value = '';
                    document.getElementById('new-list-name').value = '';
                    document.getElementById('new-list-description').value = '';
                    document.getElementById('new-list-fields').style.display = 'none';
                    document.getElementById('create-new-list-btn').innerHTML = '<i class="fas fa-plus"></i> New List';
                    startProgressTracking(data.batch_id);
                    loadEmailLists();
                    loadListsForDropdown();
                } else {
                    showAlert(data.error || 'Upload failed', 'danger');
                    progressDiv.classList.add('d-none');
                }
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-upload"></i> Upload and Validate';
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Upload failed', 'danger');
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-upload"></i> Upload and Validate';
                progressDiv.classList.add('d-none');
            });
        });

        function displayValidationResult(data) {
            document.getElementById('result-email').textContent = data.email;
            document.getElementById('result-format').innerHTML = `<span class="badge bg-${data.format_valid ? 'success' : 'danger'}">${data.format_valid ? 'Valid' : 'Invalid'}</span>`;
            document.getElementById('result-domain').innerHTML = `<span class="badge bg-${data.domain_valid ? 'success' : 'danger'}">${data.domain_valid ? 'Valid' : 'Invalid'}</span>`;
            document.getElementById('result-smtp').innerHTML = `<span class="badge bg-${data.smtp_valid ? 'success' : 'danger'}">${data.smtp_valid ? 'Valid' : 'Invalid'}</span>`;
            document.getElementById('result-overall').innerHTML = `<span class="badge bg-${data.is_valid ? 'success' : 'danger'}">${data.is_valid ? 'Valid' : 'Invalid'}</span>`;
            document.getElementById('result-verified').innerHTML = `<span class="badge bg-${data.verified ? 'success' : 'warning'}">${data.verified ? 'Yes' : 'Pending'}</span>`;
            
            document.getElementById('validation-result').classList.remove('d-none');
        }

        // SMTP provider presets
        const smtpPresets = {
            gmail: {
                host: 'smtp.gmail.com',
                port: 587,
                type: 'smtp'
            },
            outlook: {
                host: 'smtp-mail.outlook.com',
                port: 587,
                type: 'smtp'
            },
            yahoo: {
                host: 'smtp.mail.yahoo.com',
                port: 587,
                type: 'smtp'
            },
            postmark: {
                type: 'postmark'
            }
        };

        document.querySelectorAll('.preset-btn').forEach(button => {
            button.addEventListener('click', function() {
                const preset = this.getAttribute('data-preset');
                const config = smtpPresets[preset];
                
                if (config) {
                    document.getElementById('provider-type').value = config.type;
                    if (config.host) {
                        document.getElementById('smtp-host').value = config.host;
                        document.getElementById('smtp-port').value = config.port;
                    }
                    toggleSmtpFields();
                }
            });
        });

        // Provider type change handler
        document.getElementById('provider-type').addEventListener('change', toggleSmtpFields);

        function toggleSmtpFields() {
            const providerType = document.getElementById('provider-type').value;
            const smtpFields = document.getElementById('smtp-fields');
            const apiFields = document.getElementById('api-fields');
            
            smtpFields.classList.add('d-none');
            apiFields.classList.add('d-none');
            
            if (providerType === 'smtp') {
                smtpFields.classList.remove('d-none');
            } else if (['postmark', 'sendgrid', 'mailgun'].includes(providerType)) {
                apiFields.classList.remove('d-none');
            }
        }

        // Export results functionality
        document.getElementById('export-results').addEventListener('click', function() {
            window.location.href = 'api/export-results.php';
        });

        // Bulk upload functionality
        document.getElementById('bulk-upload-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('csv-file');
            const file = fileInput.files[0];
            
            if (!file) {
                showAlert('Please select a CSV file', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('send_verification', document.getElementById('bulk-send-verification').checked);
            
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            
            // Show progress bar
            const progressDiv = document.getElementById('upload-progress');
            progressDiv.classList.remove('d-none');
            
            fetch('api/bulk-upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    fileInput.value = '';
                    startProgressTracking(data.batch_id);
                } else {
                    showAlert(data.error || 'Upload failed', 'danger');
                    progressDiv.classList.add('d-none');
                }
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-upload"></i> Upload and Validate';
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Upload failed', 'danger');
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-upload"></i> Upload and Validate';
                progressDiv.classList.add('d-none');
            });
        });

        function startProgressTracking(batchId) {
            const progressBar = document.querySelector('.progress-bar');
            const progressText = document.getElementById('progress-text');
            
            const checkProgress = () => {
                fetch(`api/batch-progress.php?batch_id=${batchId}`)
                    .then(response => response.json())
                    .then(data => {
                        const percentage = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 0;
                        progressBar.style.width = percentage + '%';
                        progressBar.textContent = percentage + '%';
                        progressText.textContent = `Processed ${data.processed} of ${data.total} emails`;
                        
                        if (data.status === 'completed') {
                            progressText.textContent = `Validation completed! ${data.valid} valid, ${data.invalid} invalid`;
                            progressBar.classList.add('bg-success');
                            setTimeout(() => {
                                document.getElementById('upload-progress').classList.add('d-none');
                                loadResults(); // Refresh results
                            }, 3000);
                        } else if (data.status === 'failed') {
                            progressText.textContent = 'Validation failed';
                            progressBar.classList.add('bg-danger');
                        } else {
                            setTimeout(checkProgress, 2000);
                        }
                    })
                    .catch(error => {
                        console.error('Error checking progress:', error);
                        setTimeout(checkProgress, 5000);
                    });
            };
            
            checkProgress();
        }

        // SMTP settings form handling
        document.getElementById('smtp-settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                provider_type: document.getElementById('provider-type').value,
                smtp_host: document.getElementById('smtp-host').value,
                smtp_port: document.getElementById('smtp-port').value,
                smtp_username: document.getElementById('smtp-username').value,
                smtp_password: document.getElementById('smtp-password').value,
                api_key: document.getElementById('api-key').value,
                from_email: document.getElementById('from-email').value,
                from_name: document.getElementById('from-name').value
            };
            
            fetch('api/smtp-settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.error || 'Failed to save settings', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to save settings', 'danger');
            });
        });

        // Test SMTP connection
        document.getElementById('test-smtp').addEventListener('click', function() {
            const formData = {
                provider_type: document.getElementById('provider-type').value,
                smtp_host: document.getElementById('smtp-host').value,
                smtp_port: document.getElementById('smtp-port').value,
                smtp_username: document.getElementById('smtp-username').value,
                smtp_password: document.getElementById('smtp-password').value,
                api_key: document.getElementById('api-key').value,
                from_email: document.getElementById('from-email').value
            };
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            
            fetch('api/test-smtp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('SMTP connection successful!', 'success');
                } else {
                    showAlert(data.error || 'SMTP connection failed', 'danger');
                }
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-vial"></i> Test Connection';
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('SMTP test failed', 'danger');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-vial"></i> Test Connection';
            });
        });

        // Load SMTP settings on page load
        function loadSmtpSettings() {
            fetch('api/smtp-settings.php')
                .then(response => response.json())
                .then(data => {
                    if (data && data.provider_type) {
                        document.getElementById('provider-type').value = data.provider_type;
                        document.getElementById('smtp-host').value = data.smtp_host || '';
                        document.getElementById('smtp-port').value = data.smtp_port || '';
                        document.getElementById('smtp-username').value = data.smtp_username || '';
                        document.getElementById('api-key').value = data.api_key || '';
                        document.getElementById('from-email').value = data.from_email || '';
                        document.getElementById('from-name').value = data.from_name || '';
                        toggleSmtpFields();
                    }
                })
                .catch(error => console.error('Error loading SMTP settings:', error));
        }

        // User management functions
        function loadUsers() {
            fetch('api/users.php')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('users-table');
                    tbody.innerHTML = '';
                    
                    data.forEach(user => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${user.username}</td>
                            <td>${user.email}</td>
                            <td><span class="badge bg-${user.role === 'admin' ? 'danger' : 'primary'}">${user.role}</span></td>
                            <td><span class="badge bg-${user.is_active ? 'success' : 'secondary'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
                            <td>${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editUser(${user.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                    });
                })
                .catch(error => console.error('Error loading users:', error));
        }

        // Add user functionality
        document.getElementById('save-user').addEventListener('click', function() {
            const userData = {
                username: document.getElementById('new-username').value,
                email: document.getElementById('new-email').value,
                password: document.getElementById('new-password').value,
                role: document.getElementById('new-role').value
            };
            
            fetch('api/users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('add-user-form').reset();
                    bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                    loadUsers();
                } else {
                    showAlert(data.error || 'Failed to create user', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to create user', 'danger');
            });
        });

        function editUser(userId) {
            // Implementation for editing user
            showAlert('Edit user functionality would be implemented here', 'info');
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to deactivate this user?')) {
                fetch(`api/users.php?id=${userId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadUsers();
                    } else {
                        showAlert(data.error || 'Failed to delete user', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Failed to delete user', 'danger');
                });
            }
        }

        function loadActivityLogs() {
            fetch('api/activity-logs.php')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('logs-table');
                    tbody.innerHTML = '';
                    
                    data.forEach(log => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${new Date(log.created_at).toLocaleString()}</td>
                            <td>${log.username}</td>
                            <td><span class="badge bg-info">${log.action}</span></td>
                            <td>${log.details || '-'}</td>
                            <td>${log.ip_address}</td>
                        `;
                    });
                })
                .catch(error => console.error('Error loading activity logs:', error));
        }

        // Search functionality
        document.getElementById('search-emails').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#results-table tr');
            
            rows.forEach(row => {
                const email = row.cells[0]?.textContent.toLowerCase();
                if (email && email.includes(searchTerm)) {
                    row.style.display = '';
                } else if (email) {
                    row.style.display = 'none';
                }
            });
        });

        // Load SMTP settings when SMTP section is shown
        function loadSectionData(section) {
            switch(section) {
                case 'dashboard':
                    loadDashboardStats();
                    break;
                case 'results':
                    loadResults();
                    break;
                case 'user-management':
                    loadUsers();
                    break;
                case 'activity-logs':
                    loadActivityLogs();
                    break;
                case 'smtp-settings':
                    loadSmtpSettings();
                    break;
            }
        }

        // Utility functions
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('main');
            container.insertBefore(alertDiv, container.firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        function viewDetails(id) {
            // Implementation for viewing detailed validation results
            fetch(`api/get-validation-details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Display details in a modal or expand row
                    console.log('Validation details:', data);
                })
                .catch(error => console.error('Error loading details:', error));
        }
    </script>
</body>
</html>