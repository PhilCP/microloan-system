<?php
// forgot_password.php
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
                            <div style='font-size:36px;'>🏦</div>
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
    <title>Forgot Password - Microloan System</title>
    <style>
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

        .top-nav { position: absolute; top: 20px; left: 40px; }
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

        .container { width: 100%; max-width: 440px; }

        .logo-section { text-align: center; margin-bottom: 30px; }
        .logo { font-size: 40px; margin-bottom: 10px; }
        h1 { font-size: 30px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { color: #666; font-size: 15px; }

        .card {
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
            line-height: 1.5;
        }
        .alert-error  { background: rgba(255,68,68,0.1); border: 1px solid rgba(255,68,68,0.2); color: #ff6b6b; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: #22c55e; }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-size: 13px; color: #999; font-weight: 500; }

        input[type=email] {
            width: 100%;
            padding: 14px;
            background: #000;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
        }
        input[type=email]:focus { outline: none; border-color: #f0a500; }
        input[type=email]::placeholder { color: #444; }

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

        .divider { text-align: center; margin: 28px 0; border-bottom: 1px solid #222; line-height: 0.1em; }
        .divider span { background: #111; padding: 0 15px; color: #444; font-size: 12px; }

        .footer-link { text-align: center; font-size: 14px; color: #666; }
        .footer-link a { color: #f0a500; text-decoration: none; font-weight: 600; }
        .footer-link a:hover { text-decoration: underline; }

        .success-icon { font-size: 48px; text-align: center; margin-bottom: 16px; }
    </style>
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