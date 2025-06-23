<?php
// ===================================================================
// ADMIN/ANALYTICS.PHP - System-wide Analytics Dashboard
// ===================================================================
require_once '../config/config.php';
require_once '../includes/auth.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    redirect('../user/dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Get system-wide analytics
$analytics = [];

try {
    // Overall system stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.id END) as active_users,
            COUNT(ev.id) as total_validations,
            COUNT(CASE WHEN ev.status = 'valid' THEN 1 END) as valid_emails,
            COUNT(CASE WHEN ev.status = 'invalid' THEN 1 END) as invalid_emails,
            COUNT(CASE WHEN ev.status = 'risky' THEN 1 END) as risky_emails,
            COUNT(CASE WHEN DATE(ev.created_at) = CURDATE() THEN 1 END) as today_validations
        FROM users u
        LEFT JOIN email_validations ev ON u.id = ev.user_id
    ");
    $stmt->execute();
    $analytics['overview'] = $stmt->fetch();
    
    // Monthly trends (last 12 months)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_validations,
            COUNT(CASE WHEN status = 'valid' THEN 1 END) as valid_count,
            COUNT(CASE WHEN status = 'invalid' THEN 1 END) as invalid_count,
            COUNT(CASE WHEN status = 'risky' THEN 1 END) as risky_count,
            COUNT(DISTINCT user_id) as active_users
        FROM email_validations 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $analytics['monthly_trends'] = $stmt->fetchAll();
    
    // User activity (top 10 most active users)
    $stmt = $db->prepare("
        SELECT 
            u.username,
            u.email,
            u.role,
            COUNT(ev.id) as total_validations,
            COUNT(CASE WHEN ev.status = 'valid' THEN 1 END) as valid_count,
            MAX(ev.created_at) as last_validation
        FROM users u
        LEFT JOIN email_validations ev ON u.id = ev.user_id
        WHERE u.is_active = 1
        GROUP BY u.id
        ORDER BY total_validations DESC
        LIMIT 10
    ");
    $stmt->execute();
    $analytics['top_users'] = $stmt->fetchAll();
    
    // Domain analysis (top domains being validated)
    $stmt = $db->prepare("
        SELECT 
            SUBSTRING(email, LOCATE('@', email) + 1) as domain,
            COUNT(*) as count,
            COUNT(CASE WHEN status = 'valid' THEN 1 END) as valid_count,
            COUNT(CASE WHEN status = 'invalid' THEN 1 END) as invalid_count,
            ROUND(COUNT(CASE WHEN status = 'valid' THEN 1 END) / COUNT(*) * 100, 1) as success_rate
        FROM email_validations 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY domain
        HAVING count >= 5
        ORDER BY count DESC
        LIMIT 15
    ");
    $stmt->execute();
    $analytics['top_domains'] = $stmt->fetchAll();
    
    // Validation types breakdown
    $stmt = $db->prepare("
        SELECT 
            validation_type,
            COUNT(*) as count,
            ROUND(COUNT(*) / (SELECT COUNT(*) FROM email_validations) * 100, 1) as percentage
        FROM email_validations 
        GROUP BY validation_type
    ");
    $stmt->execute();
    $analytics['validation_types'] = $stmt->fetchAll();
    
    // Performance metrics
    $stmt = $db->prepare("
        SELECT 
            AVG(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) * 100 as overall_success_rate,
            COUNT(CASE WHEN is_disposable = 1 THEN 1 END) as disposable_count,
            COUNT(CASE WHEN is_role_based = 1 THEN 1 END) as role_based_count,
            COUNT(CASE WHEN is_catch_all = 1 THEN 1 END) as catch_all_count,
            AVG(risk_score) as avg_risk_score
        FROM email_validations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $analytics['performance'] = $stmt->fetch();
    
    // System usage by hour (last 7 days)
    $stmt = $db->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as validations
        FROM email_validations 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $stmt->execute();
    $analytics['hourly_usage'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Admin analytics error: " . $e->getMessage());
    $analytics = [
        'overview' => [],
        'monthly_trends' => [],
        'top_users' => [],
        'top_domains' => [],
        'validation_types' => [],
        'performance' => [],
        'hourly_usage' => []
    ];
}

$page_title = 'System Analytics';
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">System Analytics</h2>
                <div>
                    <button class="btn btn-outline-primary" onclick="refreshAnalytics()">
                        <i class="fas fa-sync me-2"></i>Refresh
                    </button>
                    <button class="btn btn-primary" onclick="exportSystemReport()">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </div>
            </div>

            <!-- Overview Stats -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo number_format($analytics['overview']['total_users'] ?? 0); ?></div>
                                    <div class="stats-label">Total Users</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-users fa-2x text-primary opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card success h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-success"><?php echo number_format($analytics['overview']['active_users'] ?? 0); ?></div>
                                    <div class="stats-label">Active Users</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-user-check fa-2x text-success opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card secondary h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-info"><?php echo number_format($analytics['overview']['total_validations'] ?? 0); ?></div>
                                    <div class="stats-label">Total Validations</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-envelope-check fa-2x text-info opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card success h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-success"><?php echo number_format($analytics['overview']['valid_emails'] ?? 0); ?></div>
                                    <div class="stats-label">Valid Emails</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card danger h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number text-danger"><?php echo number_format($analytics['overview']['invalid_emails'] ?? 0); ?></div>
                                    <div class="stats-label">Invalid Emails</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-times-circle fa-2x text-danger opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo number_format($analytics['overview']['today_validations'] ?? 0); ?></div>
                                    <div class="stats-label">Today's Validations</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-calendar-day fa-2x text-primary opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">System Usage Trends (Last 12 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendsChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Validation Types</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="validationTypesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Top Domains (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="topDomainsChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Usage by Hour (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyUsageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Tables Row -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Top Active Users</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['top_users'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Role</th>
                                                <th>Validations</th>
                                                <th>Success Rate</th>
                                                <th>Last Activity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['top_users'] as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($user['email']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo number_format($user['total_validations']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $rate = $user['total_validations'] > 0 ? 
                                                            round(($user['valid_count'] / $user['total_validations']) * 100, 1) : 0;
                                                        ?>
                                                        <span class="badge bg-<?php echo $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger'); ?>">
                                                            <?php echo $rate; ?>%
                                                        </span>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?php echo $user['last_validation'] ? time_ago($user['last_validation']) : 'Never'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No user activity data</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Performance Metrics (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['performance'])): ?>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h4 mb-1 text-success"><?php echo round($analytics['performance']['overall_success_rate'], 1); ?>%</div>
                                            <div class="small text-muted">Overall Success Rate</div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h4 mb-1 text-warning"><?php echo round($analytics['performance']['avg_risk_score'], 1); ?></div>
                                            <div class="small text-muted">Average Risk Score</div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?php echo number_format($analytics['performance']['disposable_count']); ?></div>
                                            <div class="small text-muted">Disposable Emails</div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?php echo number_format($analytics['performance']['role_based_count']); ?></div>
                                            <div class="small text-muted">Role-based Emails</div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No performance data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Analytics data
const analyticsData = <?php echo json_encode($analytics); ?>;

// Chart.js configuration
Chart.defaults.font.family = 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif';
Chart.defaults.color = '#6c757d';

// Monthly Trends Chart
const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
new Chart(monthlyTrendsCtx, {
    type: 'line',
    data: {
        labels: analyticsData.monthly_trends.map(item => item.month),
        datasets: [
            {
                label: 'Total Validations',
                data: analyticsData.monthly_trends.map(item => item.total_validations),
                borderColor: '#659833',
                backgroundColor: 'rgba(101, 152, 51, 0.1)',
                tension: 0.4
            },
            {
                label: 'Active Users',
                data: analyticsData.monthly_trends.map(item => item.active_users),
                borderColor: '#32679B',
                backgroundColor: 'rgba(50, 103, 155, 0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                position: 'left'
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});

// Validation Types Chart
const validationTypesCtx = document.getElementById('validationTypesChart').getContext('2d');
new Chart(validationTypesCtx, {
    type: 'doughnut',
    data: {
        labels: analyticsData.validation_types.map(item => item.validation_type.charAt(0).toUpperCase() + item.validation_type.slice(1)),
        datasets: [{
            data: analyticsData.validation_types.map(item => item.count),
            backgroundColor: ['#659833', '#32679B', '#0F101D', '#ffc107']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});

// Top Domains Chart
const topDomainsCtx = document.getElementById('topDomainsChart').getContext('2d');
new Chart(topDomainsCtx, {
    type: 'bar',
    data: {
        labels: analyticsData.top_domains.map(item => item.domain),
        datasets: [{
            label: 'Validations',
            data: analyticsData.top_domains.map(item => item.count),
            backgroundColor: '#659833'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true
            }
        }
    }
});

// Hourly Usage Chart
const hourlyUsageCtx = document.getElementById('hourlyUsageChart').getContext('2d');
new Chart(hourlyUsageCtx, {
    type: 'bar',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + ':00'),
        datasets: [{
            label: 'Validations',
            data: Array.from({length: 24}, (_, i) => {
                const hourData = analyticsData.hourly_usage.find(h => h.hour == i);
                return hourData ? hourData.validations : 0;
            }),
            backgroundColor: '#32679B'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function refreshAnalytics() {
    location.reload();
}

function exportSystemReport() {
    // Create CSV content
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "System Analytics Report - " + new Date().toLocaleDateString() + "\n\n";
    
    // Add overview stats
    csvContent += "Overview Statistics\n";
    csvContent += "Total Users," + (analyticsData.overview.total_users || 0) + "\n";
    csvContent += "Active Users," + (analyticsData.overview.active_users || 0) + "\n";
    csvContent += "Total Validations," + (analyticsData.overview.total_validations || 0) + "\n";
    csvContent += "Valid Emails," + (analyticsData.overview.valid_emails || 0) + "\n";
    csvContent += "Invalid Emails," + (analyticsData.overview.invalid_emails || 0) + "\n";
    csvContent += "Today's Validations," + (analyticsData.overview.today_validations || 0) + "\n\n";
    
    // Add monthly trends
    csvContent += "Monthly Trends\n";
    csvContent += "Month,Total Validations,Valid,Invalid,Risky,Active Users\n";
    analyticsData.monthly_trends.forEach(item => {
        csvContent += `${item.month},${item.total_validations},${item.valid_count},${item.invalid_count},${item.risky_count},${item.active_users}\n`;
    });
    
    // Download CSV
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "system_analytics_" + new Date().toISOString().split('T')[0] + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../templates/footer.php'; ?>