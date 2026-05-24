<?php
/**
 * Login Page - Rural Development Management System
 */

session_start();
require_once 'Backend/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'auth.php';
    
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = $result['message'];
    }
}

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error = 'Session expired. Please login again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RDMS - Login | Umeed-e-Sahar Foundation</title>
    <link rel="stylesheet" href="Frontend/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 480px;
            padding: 45px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.6s ease;
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .login-brand {
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .login-header p {
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
        }

        .form-group label {
            color: #e2e8f0;
            font-weight: 600;
        }

        .form-control-login {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-weight: 500;
        }

        .form-control-login:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #a78bfa;
            box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.2);
        }

        .form-control-login::placeholder {
            color: #64748b;
        }

        .btn-submit-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            border: none;
            border-radius: var(--radius-md);
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transition: var(--transition-normal);
        }

        .btn-submit-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }

        .demo-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-top: 30px;
            font-size: 13px;
        }

        .demo-box h4 {
            color: #f1f5f9;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-box p {
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 4px;
        }

        .demo-box p:last-child {
            margin-bottom: 0;
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-brand">
                <span>🌾</span> RDMS
            </div>
            <p>Rural Development Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 25px;">
                <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 25px;">
                <span>✓</span> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control form-control-login"
                    placeholder="Enter your username" 
                    required 
                    autofocus
                >
            </div>
            
            <div class="form-group" style="margin-bottom: 25px;">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control form-control-login"
                    placeholder="Enter your password" 
                    required
                >
            </div>
            
            <button type="submit" class="btn-submit-login">Sign In</button>
        </form>

        <div class="demo-box">
            <h4><span>📋</span> Default Credentials</h4>
            <p><strong>Admin:</strong> admin / password123</p>
            <p><strong>Manager:</strong> manager1 / password123</p>
            <p><strong>Field Worker:</strong> field_worker_1 / password123</p>
        </div>

        <div class="login-footer">
            <p>Umeed-e-Sahar Foundation | © <?php echo date('Y'); ?></p>
        </div>
    </div>

    <script>
        // Basic submit validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const u = document.getElementById('username').value.trim();
            const p = document.getElementById('password').value;
            if (!u || !p) {
                e.preventDefault();
                alert('Please fill out all fields');
            }
        });
    </script>
</body>
</html>
