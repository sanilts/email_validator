<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$base_path = $is_admin ? '../' : '';
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="d-flex align-items-center">
            <?php 
            $logo_path = get_system_setting('company_logo');
            if ($logo_path && file_exists($base_path . $logo_path)): 
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
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" href="users.php">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'logs.php' ? 'active' : ''; ?>" href="logs.php">
                        <i class="fas fa-list-alt"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'company.php' ? 'active' : ''; ?>" href="company.php">
                        <i class="fas fa-building"></i>
                        <span>Company</span>
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <h6 class="sidebar-heading text-muted px-3 mb-2">USER TOOLS</h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../user/dashboard.php">
                        <i class="fas fa-user-circle"></i>
                        <span>User View</span>
                    </a>
                </li>
            </ul>
        <?php else: ?>
            <!-- User Navigation -->
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'validate.php' ? 'active' : ''; ?>" href="validate.php">
                        <i class="fas fa-envelope-check"></i>
                        <span>Validate Email</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'bulk_validate.php' ? 'active' : ''; ?>" href="bulk_validate.php">
                        <i class="fas fa-upload"></i>
                        <span>Bulk Validation</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'lists.php' ? 'active' : ''; ?>" href="lists.php">
                        <i class="fas fa-list"></i>
                        <span>Email Lists</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'templates.php' ? 'active' : ''; ?>" href="templates.php">
                        <i class="fas fa-file-alt"></i>
                        <span>Templates</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="profile.php">
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