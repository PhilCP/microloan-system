<?php
// Enable error reporting to catch any hidden issues on Mac
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $result = loginUser($email, $password);
        if ($result['success']) {
            redirectToDashboard();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Microloan System</title>
    <style>
        /* Global Black & White Theme */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: #000; 
            color: #fff;
            display: flex; 
            flex-direction: column;
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
        }

        /* Top Navigation Buttons */
        .top-nav {
            position: absolute;
            top: 20px;
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 40px;
        }

        .nav-btn {
            color: #999;
            text-decoration: none;
            font-size: 14px;
            border: 1px solid #333;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .nav-btn:hover {
            color: #fff;
            border-color: #fff;
        }

        /* Login Container */
        .login-container { 
            background: #111; 
            padding: 40px; 
            border: 1px solid #222;
            border-radius: 16px; 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        h1 { font-size: 28px; margin-bottom: 8px; text-align: center; }
        p.subtitle { color: #666; text-align: center; margin-bottom: 24px; font-size: 14px; }

        .alert { 
            color: #ff4444; 
            background: rgba(255, 68, 68, 0.1); 
            padding: 12px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-size: 14px;
            border: 1px solid rgba(255, 68, 68, 0.2);
        }

        label { display: block; margin-bottom: 8px; font-size: 14px; color: #999; }

        input { 
            width: 100%; 
            padding: 14px; 
            margin-bottom: 20px; 
            background: #000;
            border: 1px solid #333; 
            border-radius: 8px; 
            color: #fff;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input:focus { outline: none; border-color: #fff; }

       .btn-submit {
            width: 100%;
            padding: 16px;
            background: #f0a500;
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }
        .btn-submit:hover { background: #ffc107; transform: translateY(-2px); }

        /* Demo Credentials Section */
        .demo-credentials { 
            margin-top: 30px; 
            font-size: 13px; 
            background: #0a0a0a; 
            padding: 15px; 
            border-radius: 8px; 
            border: 1px dashed #333;
        }

        .demo-credentials p { color: #666; margin-bottom: 10px; }

        .btn-fill {
            background: transparent;
            color: #fff;
            border: 1px solid #444;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .footer-link { text-align: center; font-size: 14px; color: #666; }
        .footer-link a { color: #f0a500; text-decoration: none; font-weight: 600; }
        .divider { text-align: center; margin: 28px 0; border-bottom: 1px solid #222; line-height: 0.1em; }
        .divider span { background: #111; padding: 0 15px; color: #444; font-size: 12px; }
        .footer-link a:hover { text-decoration: underline; }

        .btn-fill:hover { border-color: #fff; }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="nav-btn">← Home</a>
        <a href="register.php" class="nav-btn">Register →</a>
    </div>

    <div class="login-container">
        <h1>Welcome Back</h1>
        <p class="subtitle">Enter your credentials to access your account</p>
        
        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="admin@microloan.com" required>
            
            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="••••••••" required>
            
            <button type="submit" class="btn-submit">Sign In</button>
            <div class="divider"><span>FORGOT YOUR PASSWORD?</span></div>
            <div class="footer-link">
             <a href="forgot_password.php">Reset It</a>
        </div>
        </form>

        <!-- <div class="demo-credentials">
            <p><strong>Demo:</strong> admin@microloan.com / admin123</p>
            <button type="button" class="btn-fill" onclick="fill('admin@microloan.com', 'admin123')">Auto-fill Admin</button>
            <button type="button" class="btn-fill" onclick="fill('officer@microloan.com', 'officer123')">Officer</button>
        </div> -->
    </div>

    <script>
        function fill(e, p) {
            document.getElementById('email').value = e;
            document.getElementById('password').value = p;
        }
    </script>
</body>
</html>