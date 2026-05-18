<?php
// Enable error reporting to catch any hidden issues 
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
    <link rel="stylesheet" href="assets/css/login.css">
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

    
    </div>

    <script>
        function fill(e, p) {
            document.getElementById('email').value = e;
            document.getElementById('password').value = p;
        }
    </script>
</body>
</html>