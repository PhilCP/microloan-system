<?php
session_start();
require_once 'config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

if (isset($_SESSION['user_id'])) {
    header("Location: /" . ($_SESSION['role'] ?? 'borrower') . "/dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Delete existing tokens for this email
            $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();

            // Generate secure token
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + 7200); // 2 hours, local server time

            $ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $email, $token, $expires_at);
            $ins->execute();

            $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host       = $_SERVER['HTTP_HOST'];
            $reset_link = "$protocol://$host/microloan-system/reset_password.php?token=$token";

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'philcinamula@gmail.com';
                $mail->Password   = 'fsmtyxnclnuewuca';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('no-reply@microloan.com', 'Microloan System');
                $mail->addAddress($email, $user['full_name']);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "
                    <div style='font-family:sans-serif;background:#000;color:#fff;padding:40px;border-radius:12px;max-width:500px;margin:auto;'>
                        <div style='text-align:center;margin-bottom:24px;'>
                            
                            <h2 style='color:#f0a500;margin:8px 0 0;'>Microloan System</h2>
                        </div>
                        <p>Hello <strong>{$user['full_name']}</strong>,</p>
                        <p style='color:#aaa;margin-top:8px;'>We received a request to reset your password. Click the button below:</p>
                        <div style='text-align:center;margin:32px 0;'>
                            <a href='$reset_link' style='background:#f0a500;color:#000;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block;'>
                                Reset My Password
                            </a>
                        </div>
                        <p style='color:#666;font-size:13px;'>This link expires in <strong style='color:#f0a500;'>2 hours</strong>. If you didn't request this, ignore this email.</p>
                        <hr style='border-color:#222;margin:24px 0;'>
                        <p style='color:#555;font-size:12px;text-align:center;'>Or copy this link:<br>
                        <span style='color:#888;word-break:break-all;'>$reset_link</span></p>
                    </div>
                ";
                $mail->AltBody = "Reset your password: $reset_link\n\nExpires in 2 hours.";
                $mail->send();
                $success = 'A password reset link has been sent to your email. Please check your inbox.';
            } catch (Exception $e) {
                $error = 'Failed to send email. Please try again later.';
            }
        } else {
            $success = 'If that email is registered, a reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/css/forgot_password.css">
</head>
<body>

<div class="top-nav">
    <a href="login.php" class="nav-btn">← Back to Login</a>
</div>

<div class="container">
    <div class="logo-section">
        
        <h1>Forgot Password?</h1>
        <p class="subtitle">No worries, we'll send you a reset link.</p>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-icon">📬</div>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       placeholder="john@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autofocus>
            </div>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>
        <?php endif; ?>

        <div class="divider"><span>REMEMBER YOUR PASSWORD?</span></div>
        <div class="footer-link">
            Go back to <a href="login.php">Log In</a>
        </div>
    </div>
</div>

</body>
</html>