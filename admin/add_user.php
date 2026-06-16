<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user    = getCurrentUser();
$role    = "admin";
$message = "";

// Allowed roles admin can create  borrowers self-register, so only officer and admin here
$allowedRoles = ['officer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email= trim($_POST['email']);
    $phone= trim($_POST['phone'] ?? '');
    $raw_password = $_POST['password'];
    $target_role = $_POST['role'];

    //  Validate that the submitted role is one the admin is allowed to create
    if (!in_array($target_role, $allowedRoles)) {
        $message = "<div class='alert error'>Invalid role selected.</div>";
    } elseif (empty($full_name) || empty($email) || empty($raw_password)) {
        $message = "<div class='alert error'>All fields are required.</div>";
    } else {
        // Check for duplicate email
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $message = "<div class='alert error'>ACCESS DENIED: Email already exists in database.</div>";
        } else {
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $phone, $hashed_password, $target_role);

            if ($stmt->execute()) {
                $newUserId  = $conn->insert_id;
                $logAction  = "Admin created new $target_role: $full_name (ID: $newUserId)";
                $logStmt    = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                $logStmt->bind_param("is", $user['id'], $logAction);
                $logStmt->execute();

                $_SESSION['success_message'] = "User created successfully! ID: #$newUserId";
                header("Location: users.php");
                exit();
            } else {
                $message = "<div class='alert error'>Deployment failed. Error: " . $conn->error . "</div>";
            }
        }
        $check->close();
    }
}

$pageTitle = "Deploy Personnel";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/add-user.css">
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s;">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="page-center-wrapper">
            <div style="text-align: center; margin-bottom: 35px;">
                <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 2px;">Personnel Deployment</h2>
                <p style="color: #444; font-size: 13px;">
                    Create officer or admin accounts. Borrowers register themselves via the public registration page.
                </p>
            </div>

            <?php echo $message; ?>

            <div class="form-container">
                <form method="POST" autocomplete="off">

                    <div class="form-group">
                        <label>Legal Full Name</label>
                        <input type="text" name="full_name" required placeholder="Ex: John Doe">
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="identity@domain.com">
                    </div>

                    <div class="form-group">
                        <label>Phone Number (Optional)</label>
                        <input type="text" name="phone" placeholder="+254 700 000 000">
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="pass-wrapper">
                            <input type="password" name="password" id="passInput" required placeholder="••••••••">
                            <span class="gen-btn" onclick="generatePass()" title="Generate Random Key">Auto-Gen</span>
                        </div>
                        <small style="color:#666; font-size:11px;">
                            Share this password with the user so they can log in and change it.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                   
                        <select name="role" required>
                            <option value="officer">Loan Officer — Reviews and approves loan applications</option>
                            <!-- <option value="admin">System Admin — Full system access and user management</option> -->
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Create Account</button>
                </form>
            </div>

            <a href="users.php" class="back-link">← Cancel and return to Users</a>
        </div>
    </main>

    <script>
        function generatePass() {
            const charset = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%^&*";
            let pass = "";
            for (let i = 0; i < 14; ++i) {
                pass += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            const input = document.getElementById('passInput');
            input.value = pass;
            input.type  = 'text';
            input.style.color      = '#f0a500';
            input.style.fontWeight = '900';
            input.style.fontFamily = 'monospace';
        }
    </script>
</body>
</html>