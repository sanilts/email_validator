<?php
// ===================================================================
// ENHANCED ADMIN SIDEBAR - Complete Menu Structure
// ===================================================================

$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$base_path = $is_admin ? '../' : '';

// Determine the correct base URL
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
if ($current_dir === 'admin') {
    $nav_base = '';
} elseif ($current_dir === 'user') {
    $nav_base = '';
} else {
    $nav_base = './';
}
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="d-flex align-items-center">
            <?php 
            $logo_path = get_system_setting('company_logo');
            if ($logo_path && file_exists($logo_path)): 
            ?>
                <img src="<?php echo BASE_URL . $logo_path; ?>" alt="Logo" class="me-2" style="height: 40px;">
            <?php else: ?>
                <i class="fas fa-envelope-check fa-2x text-primary me-2"></i>
            <?php endif; ?>
            <h4 class="mb-0"><?php echo get_system_setting('company_name', APP_NAME); ?></h4>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <?php if ($is_admin): ?>
            <!-- Admin Navigation -->
            <ul class="nav flex-column">
                <!-- OVERVIEW SECTION -->
                <li class="nav-item">
                    <h6 class="sidebar-heading text-muted px-3 mb-2 mt-3">
                        <i class="fas fa-chart-line me-2"></i>OVERVIEW
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>analytics.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'system_health.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>system_health.php">
                        <i class="fas fa-heartbeat"></i>
                        <span>System Health</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>reports.php">
                        <i class="fas fa-file-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </li>

                <!-- USER MANAGEMENT SECTION -->
                <li class="nav-item">
                    <h6 class="sidebar-heading text-muted px-3 mb-2 mt-4">
                        <i class="fas fa-users me-2"></i>USER MANAGEMENT
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>users.php">
                        <i class="fas fa-users"></i>
                        <span>All Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'user_roles.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>user_roles.php">
                        <i class="fas fa-user-tag"></i>
                        <span>User Roles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'logs.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>logs.php">
                        <i class="fas fa-list-alt"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'security.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>security.php">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                </li>

                <!-- EMAIL MANAGEMENT SECTION -->
                <li class="nav-item">
                    <h6 class="sidebar-heading text-muted px-3 mb-2 mt-4">
                        <i class="fas fa-envelope me-2"></i>EMAIL MANAGEMENT
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'all_validations.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>all_validations.php">
                        <i class="fas fa-envelope-check"></i>
                        <span>All Validations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'bulk_jobs.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>bulk_jobs.php">
                        <i class="fas fa-tasks"></i>
                        <span>Bulk Jobs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'email_lists.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>email_lists.php">
                        <i class="fas fa-list"></i>
                        <span>Email Lists</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'email_templates.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>email_templates.php">
                        <i class="fas fa-file-alt"></i>
                        <span>Email Templates</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'email_campaigns.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>email_campaigns.php">
                        <i class="fas fa-bullhorn"></i>
                        <span>Email Campaigns</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'blocked_emails.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>blocked_emails.php">
                        <i class="fas fa-ban"></i>
                        <span>Blocked Emails</span>
                    </a>
                </li>

                <!-- SYSTEM CONFIGURATION SECTION -->
                <li class="nav-item">
                    <h6 class="sidebar-heading text-muted px-3 mb-2 mt-4">
                        <i class="fas fa-cogs me-2"></i>SYSTEM CONFIG
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>settings.php">
                        <i class="fas fa-cog"></i>
                        <span>General Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'company.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>company.php">
                        <i class="fas fa-building"></i>
                        <span>Company Branding</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'smtp_config.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>smtp_config.php">
                        <i class="fas fa-server"></i>
                        <span>SMTP Configuration</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'rate_limiting.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>rate_limiting.php">
                        <i class="fas fa-stopwatch"></i>
                        <span>Rate Limiting</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'api_management.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>api_management.php">
                        <i class="fas fa-code"></i>
                        <span>API Management</span>
                    </a>
                </li>

                <!-- MAINTENANCE SECTION -->
                <li class="nav-item">
                    <h6 class="sidebar-heading text-muted px-3 mb-2 mt-4">
                        <i class="fas fa-wrench me-2"></i>MAINTENANCE
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'backup.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>backup.php">
                        <i class="fas fa-database"></i>
                        <span>Database Backup</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'system_cleanup.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>system_cleanup.php">
                        <i class="fas fa-broom"></i>
                        <span>System Cleanup</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'import_export.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>import_export.php">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Import/Export</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'system_info.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>system_info.php">
                        <i class="fas fa-info-circle"></i>
                        <span>System Information</span>
                    </a>
                </li>

                <!-- TOOLS SECTION -->
                <li class="nav-item">
                    <h6 class="sidebar-heading text-muted px-3 mb-2 mt-4">
                        <i class="fas fa-tools me-2"></i>TOOLS & TESTING
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'email_tester.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>email_tester.php">
                        <i class="fas fa-vial"></i>
                        <span>Email Tester</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'domain_checker.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>domain_checker.php">
                        <i class="fas fa-search"></i>
                        <span>Domain Checker</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'blacklist_checker.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>blacklist_checker.php">
                        <i class="fas fa-shield-alt"></i>
                        <span>Blacklist Checker</span>
                    </a>
                </li>

                <!-- USER VIEW SECTION -->
                <li class="nav-item">
                    <h6 class="sidebar-heading text-muted px-3 mb-2 mt-4">
                        <i class="fas fa-user me-2"></i>USER EXPERIENCE
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../user/dashboard.php">
                        <i class="fas fa-user-circle"></i>
                        <span>Switch to User View</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../user/validate.php">
                        <i class="fas fa-envelope-check"></i>
                        <span>Quick Validation</span>
                    </a>
                </li>
            </ul>
        <?php else: ?>
            <!-- User Navigation -->
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'validate.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>validate.php">
                        <i class="fas fa-envelope-check"></i>
                        <span>Validate Email</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'bulk_validate.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>bulk_validate.php">
                        <i class="fas fa-upload"></i>
                        <span>Bulk Validation</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'lists.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>lists.php">
                        <i class="fas fa-list"></i>
                        <span>Email Lists</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'templates.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>templates.php">
                        <i class="fas fa-file-alt"></i>
                        <span>Templates</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'email_campaigns.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>email_campaigns.php">
                        <i class="fas fa-bullhorn"></i>
                        <span>Campaigns</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>analytics.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>profile.php">
                        <i class="fas fa-user-cog"></i>
                        <span>Profile</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer mt-auto p-3">
        <div class="d-grid">
            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>
    </div>
</div>