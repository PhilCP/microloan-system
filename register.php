<?php
// Enable error reporting to catch any hidden issues on Mac
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Redirect if logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The registerUser function is inside includes/auth.php
    $result = registerUser($_POST);
    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
        header("Location: login.php");
        exit();
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Microloan System</title>
    <style>
        /* Global Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #000;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Navigation */
        .top-nav {
            position: absolute;
            top: 20px;
            width: 100%;
            display: flex;
            justify-content: flex-start;
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

        .nav-btn:hover { color: #fff; border-color: #fff; }

        .register-container { width: 100%; max-width: 550px; }

        /* Header section */
        .logo-section { text-align: center; margin-bottom: 30px; }
        .logo { font-size: 40px; margin-bottom: 10px; }
        h1 { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { color: #666; font-size: 15px; }

        /* Form Card */
        .register-card {
            background: #111;
            border: 1px solid #222;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }

        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.2);
            color: #ff6b6b;
        }

        /* Grid for side-by-side inputs */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-size: 13px; color: #999; font-weight: 500; }
        
        input {
            width: 100%;
            padding: 14px;
            background: #000;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        input:focus { outline: none; border-color: #fff; }

        /* Password Strength */
        .strength-meter {
            height: 3px;
            background: #222;
            margin-top: 8px;
            border-radius: 2px;
            overflow: hidden;
        }
        .strength-bar { height: 100%; width: 0; transition: width 0.3s, background 0.3s; }
        .weak { background: #ff4444; width: 33%; }
        .medium { background: #ffbb33; width: 66%; }
        .strong { background: #00C851; width: 100%; }

        /* Buttons */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #fff;
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover { background: #e6e6e6; transform: translateY(-2px); }

        .divider {
            text-align: center;
            margin: 30px 0;
            border-bottom: 1px solid #222;
            line-height: 0.1em;
        }
        .divider span { background: #111; padding: 0 15px; color: #444; font-size: 12px; }

        .footer-link { text-align: center; font-size: 14px; color: #666; }
        .footer-link a { color: #fff; text-decoration: none; font-weight: 600; }
        
        /* Mobile adjust */
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="nav-btn">← Back to Home</a>
    </div>

    <div class="register-container">
        <div class="logo-section">
            <div class="logo">🏦</div>
            <h1>Join Us</h1>
            <p class="subtitle">Quick setup for your financial growth.</p>
        </div>

        <div class="register-card">
            <?php if ($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="registerForm">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="John Doe" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="john@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="+254..." required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <div class="strength-meter"><div id="strengthBar" class="strength-bar"></div></div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Create Account</button>
            </form>

            <div class="divider"><span>ALREADY HAVE AN ACCOUNT?</span></div>

            <div class="footer-link">
    Sign in to your dashboard
    <a href="login.php" class="button-link">Log In</a>
</div>

        </div>
    </div>

    

    <script>
        // Password Strength Logic
        document.getElementById('password').addEventListener('input', function() {
            const bar = document.getElementById('strengthBar');
            const val = this.value;
            bar.className = 'strength-bar';
            
            if (val.length > 0 && val.length < 6) bar.classList.add('weak');
            else if (val.length >= 6 && val.length < 10) bar.classList.add('medium');
            else if (val.length >= 10) bar.classList.add('strong');
        });

        // Phone Formatting (Optional UX)
        document.getElementById('phone').addEventListener('blur', function() {
            if(!this.value.startsWith('+254') && this.value.length > 0) {
                this.value = '+254' + this.value.replace(/^0/, '');
            }
        });
    </script>
</body>
</html>