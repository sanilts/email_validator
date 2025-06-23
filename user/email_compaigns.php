<?php
// ===================================================================
// USER/ANALYTICS.PHP - User Analytics Dashboard
// ===================================================================
?>
<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

// Get analytics data
$analytics = [];

try {
    // Monthly validation trends (last 6 months)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_validations,
            SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as valid_count,
            SUM(CASE WHEN status = 'invalid' THEN 1 ELSE 0 END) as invalid_count,
            SUM(CASE WHEN status = 'risky' THEN 1 ELSE 0 END) as risky_count
        FROM email_validations 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $analytics['monthly_trends'] = $stmt->fetchAll();
    
    // Daily validation trends (last 30 days)
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as validations
        FROM email_validations 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $analytics['daily_trends'] = $stmt->fetchAll();
    
    // Domain analysis
    $stmt = $db->prepare("
        SELECT 
            SUBSTRING(email, LOCATE('@', email) + 1) as domain,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as valid_count
        FROM email_validations 
        WHERE user_id = ?
        GROUP BY domain
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $analytics['top_domains'] = $stmt->fetchAll();
    
    // Validation type breakdown
    $stmt = $db->prepare("
        SELECT 
            validation_type,
            COUNT(*) as count
        FROM email_validations 
        WHERE user_id = ?
        GROUP BY validation_type
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $analytics['validation_types'] = $stmt->fetchAll();
    
    // Risk score distribution
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN risk_score = 0 THEN '0 (No Risk)'
                WHEN risk_score <= 25 THEN '1-25 (Low Risk)'
                WHEN risk_score <= 50 THEN '26-50 (Medium Risk)'
                WHEN risk_score <= 75 THEN '51-75 (High Risk)'
                ELSE '76-100 (Very High Risk)'
            END as risk_category,
            COUNT(*) as count
        FROM email_validations 
        WHERE user_id = ?
        GROUP BY risk_category
        ORDER BY risk_score
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $analytics['risk_distribution'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    $analytics = [
        'monthly_trends' => [],
        'daily_trends' => [],
        'top_domains' => [],
        'validation_types' => [],
        'risk_distribution' => []
    ];
}

$page_title = 'Analytics';
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Analytics Dashboard</h2>
                <div>
                    <button class="btn btn-outline-primary" onclick="refreshCharts()">
                        <i class="fas fa-sync me-2"></i>Refresh
                    </button>
                    <button class="btn btn-primary" onclick="exportAnalytics()">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Monthly Validation Trends</h5>
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
                            <h5 class="mb-0">Top Domains</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="topDomainsChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Risk Score Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="riskDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Activity Chart -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Daily Activity (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyActivityChart" height="80"></canvas>
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
                label: 'Valid',
                data: analyticsData.monthly_trends.map(item => item.valid_count),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            },
            {
                label: 'Invalid',
                data: analyticsData.monthly_trends.map(item => item.invalid_count),
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            },
            {
                label: 'Risky',
                data: analyticsData.monthly_trends.map(item => item.risky_count),
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
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
            label: 'Total Validations',
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

// Risk Distribution Chart
const riskDistributionCtx = document.getElementById('riskDistributionChart').getContext('2d');
new Chart(riskDistributionCtx, {
    type: 'pie',
    data: {
        labels: analyticsData.risk_distribution.map(item => item.risk_category),
        datasets: [{
            data: analyticsData.risk_distribution.map(item => item.count),
            backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});

// Daily Activity Chart
const dailyActivityCtx = document.getElementById('dailyActivityChart').getContext('2d');
new Chart(dailyActivityCtx, {
    type: 'bar',
    data: {
        labels: analyticsData.daily_trends.map(item => item.date),
        datasets: [{
            label: 'Daily Validations',
            data: analyticsData.daily_trends.map(item => item.validations),
            backgroundColor: '#32679B'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function refreshCharts() {
    location.reload();
}

function exportAnalytics() {
    // Create CSV content
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Analytics Report - " + new Date().toLocaleDateString() + "\n\n";
    
    // Add monthly trends
    csvContent += "Monthly Trends\n";
    csvContent += "Month,Total,Valid,Invalid,Risky\n";
    analyticsData.monthly_trends.forEach(item => {
        csvContent += `${item.month},${item.total_validations},${item.valid_count},${item.invalid_count},${item.risky_count}\n`;
    });
    
    csvContent += "\nTop Domains\n";
    csvContent += "Domain,Count,Valid Count\n";
    analyticsData.top_domains.forEach(item => {
        csvContent += `${item.domain},${item.count},${item.valid_count}\n`;
    });
    
    // Download CSV
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "analytics_report_" + new Date().toISOString().split('T')[0] + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../templates/footer.php'; ?>