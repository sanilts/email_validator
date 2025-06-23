<?php
$user_info = [
    'username' => $_SESSION['username'] ?? 'Unknown',
    'role' => $_SESSION['role'] ?? 'user'
];
?>

<div class="topbar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <button class="btn btn-ghost me-3 d-lg-none" id="mobileSidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <button class="btn btn-ghost me-3 d-none d-lg-block" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <h4 class="mb-0 text-muted"><?php echo $page_title ?? 'Dashboard'; ?></h4>
    </div>
    
    <div class="d-flex align-items-center">
        <!-- Theme Toggle -->
        <button class="btn btn-ghost me-3" id="themeToggle" title="Toggle theme">
            <i class="fas fa-moon"></i>
        </button>
        
        <!-- Notifications -->
        <div class="dropdown me-3">
            <button class="btn btn-ghost position-relative" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    0
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <h6 class="dropdown-header">Notifications</h6>
                <div class="dropdown-item text-center text-muted">
                    No new notifications
                </div>
            </div>
        </div>
        
        <!-- User Menu -->
        <div class="dropdown">
            <button class="btn btn-ghost d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                <div class="avatar me-2">
                    <i class="fas fa-user-circle fa-2x"></i>
                </div>
                <div class="text-start d-none d-md-block">
                    <div class="fw-medium"><?php echo htmlspecialchars($user_info['username']); ?></div>
                    <div class="small text-muted"><?php echo ucfirst($user_info['role']); ?></div>
                </div>
                <i class="fas fa-chevron-down ms-2"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <h6 class="dropdown-header">
                    <?php echo htmlspecialchars($user_info['username']); ?>
                </h6>
                <div class="dropdown-divider"></div>
                
                <?php if ($user_info['role'] === 'user'): ?>
                    <a class="dropdown-item" href="profile.php">
                        <i class="fas fa-user me-2"></i>Profile
                    </a>
                    <a class="dropdown-item" href="profile.php?tab=settings">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                <?php else: ?>
                    <a class="dropdown-item" href="settings.php">
                        <i class="fas fa-cog me-2"></i>System Settings
                    </a>
                    <a class="dropdown-item" href="../user/dashboard.php">
                        <i class="fas fa-user-circle me-2"></i>User View
                    </a>
                <?php endif; ?>
                
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>auth/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</div>
