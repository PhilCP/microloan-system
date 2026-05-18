<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";
$message = "";

//Target Acquisition
if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit;
}

$target_id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $target_id);
$stmt->execute();
$target_user = $stmt->get_result()->fetch_assoc();

if (!$target_user) {
    die("CRITICAL ERROR: Personnel record not found in system archives.");
}

// user modification Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $new_role = $_POST['role'];
    $new_password = $_POST['password'];

    if (!empty($new_password)) {
        $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
        $update_stmt->bind_param("ssssi", $full_name, $email, $new_role, $hashed_pass, $target_id);
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $full_name, $email, $new_role, $target_id);
    }

    if ($update_stmt->execute()) {
        $logAction = "ADMIN [{$user['id']}] Modified credentials for UID-" . str_pad($target_id, 4, '0', STR_PAD_LEFT);
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $logStmt->bind_param("is", $user['id'], $logAction);
        $logStmt->execute();

        header("Location: users.php?msg=UpdateSuccess");
        exit;
    } else {
        $message = "<div class='alert error'> MODIFICATION FAILED: Database conflict or connectivity loss.</div>";
    }
}

$pageTitle = "Modify Personnel";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/user-edit.css">
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s;">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="page-center-wrapper">
            
            <a href="users.php" class="btn-back">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>
                Abort & Return
            </a>

            <div style="text-align: center; margin-bottom: 35px;">
                <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 2px;">Modify Personnel</h2>
                <p style="color: #444; font-size: 13px;">Updating Master Record: <span style="color:#f0a500; font-family: monospace;">UID-<?php echo str_pad($target_id, 4, '0', STR_PAD_LEFT); ?></span></p>
            </div>

            <?php echo $message; ?>

            <div class="form-container">
                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <label>Identity (Full Name)</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($target_user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Operational Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($target_user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Designation (Clearance)</label>
                        <select name="role" required>
                            <option value="borrower" <?php echo $target_user['role'] == 'borrower' ? 'selected' : ''; ?>>Level 1 - Borrower</option>
                            <option value="officer" <?php echo $target_user['role'] == 'officer' ? 'selected' : ''; ?>>Level 2 - Loan Officer</option>
                            <!-- <option value="admin" <?php echo $target_user['role'] == 'admin' ? 'selected' : ''; ?>>Level 3 - System Admin</option> -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Credential Override (Password)</label>
                        <input type="password" name="password" placeholder="••••••••" autocomplete="new-password">
                        <div class="info-note">Leave field blank to maintain current encryption.</div>
                    </div>

                    <button type="submit" class="btn-update">Commit Changes</button>
                </form>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('dashboardMain');
            sidebar.classList.toggle('active');
            main.style.marginLeft = sidebar.classList.contains('active') ? '240px' : '0';
        });
    </script>
</body>
</html>