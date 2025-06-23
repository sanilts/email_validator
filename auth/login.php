<?php
// auth/login.php - Simple working version
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

// Try to load config
try {
    require_once '../config/config.php';
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('System Error: ' . $e->getMessage() . '<br><a href="../error_check.php">Run Diagnostics</a>');
}

// Handle login
if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                header('Location: ../index.php');
                exit();
            } else {
                $error = 'Invalid credentials';
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Email Validator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #659833, #32679B); min-height: 100vh; }
        .login-card { max-width: 400px; margin: 100px auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2>Email Validator</h2>
                        <p class="text-muted">Sign in to continue</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username or Email</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="../error_check.php" class="btn btn-outline-secondary btn-sm">System Diagnostics</a>
                        <a href="../install.php" class="btn btn-outline-primary btn-sm">Run Installer</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>