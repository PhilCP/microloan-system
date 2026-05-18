<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $userRole = $_SESSION['role'] ?? 'borrower';
    header("Location: /$userRole/dashboard.php");
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name']);
    $email            = trim($_POST['email']);
    $phone            = trim($_POST['phone']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role             = 'borrower';

    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows > 0) {
            $error = "Email address is already registered";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $phone, $hashedPassword, $role);

            if ($stmt->execute()) {
                $userId = $conn->insert_id;

                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                $action  = "New borrower account registered";
                $logStmt->bind_param("is", $userId, $action);
                $logStmt->execute();

                $_SESSION['success'] = "Account created successfully! Please login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="nav-btn">← Back to Home</a>
    </div>

    <div class="register-container">
        <div class="logo-section">
            <h1>Join us today</h1>
            <p class="subtitle">Quick setup for your financial growth and support.</p>
        </div>

        <div class="register-card">

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" id="registerForm">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="full_name" placeholder="John Doe"
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                           required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="john@example.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="+254 700 000 000"
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                               required>
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
                <a href="login.php">Log In</a>
            </div>
        </div>
    </div>

    <script>
        // Password Strength
        document.getElementById('password').addEventListener('input', function() {
            const bar = document.getElementById('strengthBar');
            const val = this.value;
            bar.className = 'strength-bar';
            if (val.length > 0 && val.length < 6)        bar.classList.add('weak');
            else if (val.length >= 6 && val.length < 10) bar.classList.add('medium');
            else if (val.length >= 10)                   bar.classList.add('strong');
        });

        // Full Name with letters, spaces, hyphens, apostrophes only
        document.getElementById('full_name').addEventListener('keypress', function(e) {
            if (!/[a-zA-Z\s\-']/.test(e.key)) e.preventDefault();
        });
        document.getElementById('full_name').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '');
        });

        // Phone, digits only (+ allowed at start)
        document.getElementById('phone').addEventListener('keypress', function(e) {
            const isPlus = e.key === '+' && this.value.length === 0;
            if (!/[0-9]/.test(e.key) && !isPlus) e.preventDefault();
        });
        document.getElementById('phone').addEventListener('input', function() {
            let val = this.value;
            const hasPlus = val.startsWith('+');
            val = val.replace(/[^0-9]/g, '');
            this.value = hasPlus ? '+' + val : val;
        });

        // Phone Auto-format on blur
        document.getElementById('phone').addEventListener('blur', function() {
            if (!this.value.startsWith('+254') && this.value.length > 0) {
                let cleaned = this.value.replace(/\D/g, '');
                if (cleaned.startsWith('0'))   cleaned = cleaned.substring(1);
                if (cleaned.startsWith('254')) this.value = '+' + cleaned;
                else if (cleaned.length >= 9)  this.value = '+254' + cleaned;
            }
        });
    </script>
</body>
</html>