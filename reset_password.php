<?php

session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: /" . ($_SESSION['role'] ?? 'borrower') . "/dashboard.php");
    exit();
}

$token       = trim($_GET['token'] ?? '');
$error       = '';
$success     = '';
$valid_token = false;
$user_email  = '';

//Validate token
if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $reset = $result->fetch_assoc();
        // Compare expiry using PHP time (in order to avoid MySQL timezone issues)
        if (strtotime($reset['expires_at']) < time()) {
            $error = 'This reset link has expired. Please request a new one.';
        } else {
            $user_email  = $reset['email'];
            $valid_token = true;
        }
    } else {
        $error = 'This reset link is invalid or has already been used.';
    }
}

// form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        // Update user password
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $upd->bind_param("ss", $hashed, $user_email);
        $upd->execute();

        // Mark token as used
        $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $mark->bind_param("s", $token);
        $mark->execute();

        // Log the activity
        $uid_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $uid_stmt->bind_param("s", $user_email);
        $uid_stmt->execute();
        $uid_row = $uid_stmt->get_result()->fetch_assoc();
        if ($uid_row) {
            $uid    = $uid_row['id'];
            $action = 'User reset their password';
            $log    = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $log->bind_param("is", $uid, $action);
            $log->execute();
        }

        $success     = 'Your password has been reset successfully. You can now log in.';
        $valid_token = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/css/reset_password.css">
   
</head>
<body>

<div class="top-nav">
    <a href="login.php" class="nav-btn">← Back to Login</a>
</div>

<div class="container">
    <div class="logo-section">
        <h1>Reset Password</h1>
        <p class="subtitle">Choose a strong new password for your account.</p>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
                <?php if (!$valid_token && !$success): ?>
                    <br><br><a href="forgot_password.php" style="color:#f0a500;font-weight:600;">Request a new link →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <div class="footer-link">
                <a href="login.php" style="display:inline-block;margin-top:8px;">→ Go to Login</a>
            </div>

        <?php elseif ($valid_token): ?>
        <form method="POST" action="reset_password.php?token=<?= htmlspecialchars($token) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Min. 8 characters"
                           required minlength="8"
                           oninput="checkStrength(this.value)">
                    <div class="strength-meter"><div id="strengthBar" class="strength-bar"></div></div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter password"
                           required minlength="8">
                </div>
            </div>
            <button type="submit" class="btn-submit">Reset Password</button>
        </form>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="divider"><span>REMEMBER YOUR PASSWORD?</span></div>
        <div class="footer-link">Go back to <a href="login.php">Log In</a></div>
        <?php endif; ?>
    </div>
</div>

<script>
function checkStrength(val) {
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    bar.className = 'strength-bar';
    if (val.length === 0) { lbl.textContent = ''; return; }
    if (val.length < 8) {
        bar.classList.add('weak');   lbl.textContent = 'Weak';
    } else if (val.length < 12 || !/[^a-zA-Z0-9]/.test(val)) {
        bar.classList.add('medium'); lbl.textContent = 'Medium';
    } else {
        bar.classList.add('strong'); lbl.textContent = 'Strong ✓';
    }
}
</script>

</body>
</html>