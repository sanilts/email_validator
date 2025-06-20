<?php
// =============================================================================
// EXPORT ADMIN VALIDATIONS API (api/export-admin-validations.php)
// =============================================================================

require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build where clause based on filters
    $whereConditions = [];
    $params = [];
    
    if (!empty($_GET['user'])) {
        $whereConditions[] = 'ev.user_id = ?';
        $params[] = $_GET['user'];
    }
    
    if (!empty($_GET['list'])) {
        $whereConditions[] = 'ev.list_id = ?';
        $params[] = $_GET['list'];
    }
    
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $whereConditions[] = 'ev.is_valid = ?';
        $params[] = $_GET['status'];
    }
    
    if (!empty($_GET['date'])) {
        $whereConditions[] = 'DATE(ev.validation_date) = ?';
        $params[] = $_GET['date'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $stmt = $db->prepare("
        SELECT ev.email, u.username, el.list_name, ev.is_valid, ev.format_valid, 
               ev.domain_valid, ev.smtp_valid, ev.validation_date
        FROM email_validations ev
        JOIN users u ON ev.user_id = u.id
        LEFT JOIN email_lists el ON ev.list_id = el.id
        $whereClause
        ORDER BY ev.validation_date DESC
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'admin_validations_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, [
        'User',
        'Email',
        'List',
        'Status',
        'Format Valid',
        'Domain Valid',
        'SMTP Valid',
        'Validation Date'
    ]);
    
    // Write data
    foreach ($results as $result) {
        fputcsv($output, [
            $result['username'],
            $result['email'],
            $result['list_name'] ?? 'No List',
            $result['is_valid'] ? 'Valid' : 'Invalid',
            $result['format_valid'] ? 'Yes' : 'No',
            $result['domain_valid'] ? 'Yes' : 'No',
            $result['smtp_valid'] ? 'Yes' : 'No',
            $result['validation_date']
        ]);
    }
    
    fclose($output);
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, 'admin_export', ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        json_encode(['record_count' => count($results), 'filters' => $_GET]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error exporting validations: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Validator - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-envelope-check fa-3x text-primary"></i>
                            <h3 class="mt-3">Email Validator</h3>
                            <p class="text-muted">Sign in to your account</p>
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Sign In
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>