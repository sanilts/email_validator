<?php
// ===================================================================
// API/STATS.PHP - User Statistics API
// ===================================================================
?>
<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $period = $_GET['period'] ?? '30days';
    $user_id = $_SESSION['user_id'];
    
    $stats = [];
    
    // Determine date range
    switch ($period) {
        case '7days':
            $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '90days':
            $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case '1year':
            $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        default:
            $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    // Total validations
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM email_validations 
        WHERE user_id = ? AND $date_condition
    ");
    $stmt->execute([$user_id]);
    $stats['total_validations'] = $stmt->fetch()['total'];
    
    // Status breakdown
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM email_validations 
        WHERE user_id = ? AND $date_condition
        GROUP BY status
    ");
    $stmt->execute([$user_id]);
    $status_breakdown = $stmt->fetchAll();
    $stats['status_breakdown'] = $status_breakdown;
    
    // Daily trend
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as validations,
            SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as valid_count
        FROM email_validations 
        WHERE user_id = ? AND $date_condition
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$user_id]);
    $stats['daily_trend'] = $stmt->fetchAll();
    
    // Top domains
    $stmt = $db->prepare("
        SELECT 
            SUBSTRING(email, LOCATE('@', email) + 1) as domain,
            COUNT(*) as count,
            AVG(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) * 100 as success_rate
        FROM email_validations 
        WHERE user_id = ? AND $date_condition
        GROUP BY domain
        HAVING count >= 5
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $stats['top_domains'] = $stmt->fetchAll();
    
    // Risk score distribution
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN risk_score = 0 THEN 'No Risk'
                WHEN risk_score <= 25 THEN 'Low Risk'
                WHEN risk_score <= 50 THEN 'Medium Risk'
                WHEN risk_score <= 75 THEN 'High Risk'
                ELSE 'Very High Risk'
            END as risk_category,
            COUNT(*) as count
        FROM email_validations 
        WHERE user_id = ? AND $date_condition
        GROUP BY risk_category
    ");
    $stmt->execute([$user_id]);
    $stats['risk_distribution'] = $stmt->fetchAll();
    
    // Validation type breakdown
    $stmt = $db->prepare("
        SELECT 
            validation_type,
            COUNT(*) as count
        FROM email_validations 
        WHERE user_id = ? AND $date_condition
        GROUP BY validation_type
    ");
    $stmt->execute([$user_id]);
    $stats['validation_types'] = $stmt->fetchAll();
    
    // Performance metrics
    $stmt = $db->prepare("
        SELECT 
            AVG(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) * 100 as success_rate,
            AVG(risk_score) as avg_risk_score,
            SUM(CASE WHEN is_disposable = 1 THEN 1 ELSE 0 END) as disposable_count,
            SUM(CASE WHEN is_role_based = 1 THEN 1 ELSE 0 END) as role_based_count
        FROM email_validations 
        WHERE user_id = ? AND $date_condition
    ");
    $stmt->execute([$user_id]);
    $stats['performance_metrics'] = $stmt->fetch();
    
    json_response(['success' => true, 'stats' => $stats, 'period' => $period]);
    
} catch (Exception $e) {
    error_log("Stats API error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to retrieve statistics'], 500);
}
?>