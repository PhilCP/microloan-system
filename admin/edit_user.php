<?php
/**
 * Personnel Modification Module
 * High-clearance interface for updating agent credentials and access hierarchies.
 */
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";
$message = "";

// --- 1. Target Acquisition ---
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

// --- 2. Modification Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $new_role = $_POST['role'];
    $new_password = $_POST['password'];

    // Conditional Logic: Only update password if a new one is provided
    if (!empty($new_password)) {
        $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
        $update_stmt->bind_param("ssssi", $full_name, $email, $new_role, $hashed_pass, $target_id);
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $full_name, $email, $new_role, $target_id);
    }

    if ($update_stmt->execute()) {
        // Operational Logging
        $logAction = "ADMIN [{$user['id']}] Modified credentials for UID-" . str_pad($target_id, 4, '0', STR_PAD_LEFT);
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $logStmt->bind_param("is", $user['id'], $logAction);
        $logStmt->execute();

        header("Location: users.php?msg=UpdateSuccess");
        exit;
    } else {
        $message = "<div class='alert error'>❌ MODIFICATION FAILED: Database conflict or connectivity loss.</div>";
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
    <style>
        body { background: #000; color: #fff; font-family: 'Inter', sans-serif; }

        .page-center-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 85vh;
            padding: 20px;
        }

        .form-container { 
            width: 100%;
            max-width: 450px; 
            background: #0a0a0a; 
            padding: 40px; 
            border-radius: 12px; 
            border: 1px solid #1a1a1a; 
            box-shadow: 0 30px 60px rgba(0,0,0,0.8);
            position: relative;
        }

        /* Gold gradient top border */
        .form-container::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, #f0a500, transparent);
        }

        .form-group { margin-bottom: 25px; }

        label { 
            display: block; 
            color: #444; 
            font-size: 10px; 
            text-transform: uppercase; 
            margin-bottom: 10px; 
            letter-spacing: 1.5px;
            font-weight: 800;
        }

        input, select { 
            width: 100%; 
            padding: 14px; 
            background: #000; 
            border: 1px solid #222; 
            color: #fff; 
            border-radius: 6px; 
            font-size: 14px;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        input:focus, select:focus { border-color: #f0a500; outline: none; background: #050505; }

        .btn-update { 
            background: #f0a500; 
            color: #000; 
            font-weight: 900; 
            border: none; 
            padding: 16px; 
            cursor: pointer; 
            width: 100%; 
            border-radius: 6px; 
            transition: 0.3s; 
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .btn-update:hover { 
            background: #fff; 
            box-shadow: 0 0 20px rgba(240, 165, 0, 0.2);
            transform: translateY(-2px);
        }

        .btn-back {
            align-self: flex-start;
            margin-bottom: 25px;
            color: #444;
            text-decoration: none;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            font-weight: 800;
        }
        .btn-back:hover { color: #f0a500; }

        .alert { width: 100%; max-width: 450px; padding: 15px; border-radius: 6px; margin-bottom: 25px; text-align: center; font-size: 12px; font-weight: 700; }
        .error { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }
        
        .info-note { font-size: 9px; color: #222; margin-top: 8px; font-weight: 700; text-transform: uppercase; }
    </style>
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
                            <option value="admin" <?php echo $target_user['role'] == 'admin' ? 'selected' : ''; ?>>Level 3 - System Admin</option>
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
        // Sidebar Responsiveness Logic
        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('dashboardMain');
            sidebar.classList.toggle('active');
            main.style.marginLeft = sidebar.classList.contains('active') ? '240px' : '0';
        });
    </script>
</body>
</html>