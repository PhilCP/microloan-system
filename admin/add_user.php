<?php
/**
 * Personnel Deployment Module
 * Handles the initialization of new user accounts across all hierarchy levels.
 */
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";
$message = "";

// --- 1. Deployment Execution Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $target_role = $_POST['role'];

    // Identity Collision Check
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $message = "<div class='alert error'>⚠️ ACCESS DENIED: Identity already exists in database.</div>";
    } else {
        // SQL Injection protected via Prepared Statements
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $full_name, $email, $password, $target_role);
        
        if ($stmt->execute()) {
            // Log the deployment for audit trails
            $logAction = "ADMIN [{$user['id']}] Deployed new user: $full_name ($target_role)";
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $logStmt->bind_param("is", $user['id'], $logAction);
            $logStmt->execute();
            
            header("Location: users.php?msg=UserCreated");
            exit;
        } else {
            $message = "<div class='alert error'>❌ CRITICAL: Deployment sequence failed. System rejection.</div>";
        }
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
    <style>
        body { background: #000; color: #fff; font-family: 'Inter', sans-serif; }

        /* Tactical Centering */
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

        /* Subtle top accent */
        .form-container::after {
            content: "";
            position: absolute;
            top: -1px; left: 10%; right: 10%; height: 1px;
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
            box-sizing: border-box; 
            font-size: 14px;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        input:focus, select:focus { 
            border-color: #f0a500; 
            outline: none; 
            background: #050505;
        }

        .pass-wrapper { position: relative; }
        
        /* Auto-Generation Terminal Toggle */
        .gen-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: #111;
            color: #f0a500;
            border: 1px solid #222;
            padding: 4px 8px;
            font-size: 9px;
            border-radius: 3px;
            cursor: pointer;
            text-transform: uppercase;
            font-weight: 900;
            transition: 0.2s;
        }
        .gen-btn:hover { background: #f0a500; color: #000; border-color: #f0a500; }

        .btn-submit { 
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

        .btn-submit:hover { 
            background: #fff; 
            box-shadow: 0 0 20px rgba(240, 165, 0, 0.2);
        }

        .alert { 
            width: 100%;
            max-width: 450px;
            padding: 15px; 
            border-radius: 6px; 
            margin-bottom: 25px; 
            font-size: 12px; 
            text-align: center;
            font-weight: 700;
        }
        .error { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }

        .back-link {
            margin-top: 30px;
            color: #333;
            text-decoration: none;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 800;
        }
        .back-link:hover { color: #f0a500; }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s;">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="page-center-wrapper">
            <div style="text-align: center; margin-bottom: 35px;">
                <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 2px;">Personnel Deployment</h2>
                <p style="color: #444; font-size: 13px;">Initialize agent credentials and grant operational clearance.</p>
            </div>

            <?php echo $message; ?>

            <div class="form-container">
                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <label>Legal Full Name</label>
                        <input type="text" name="full_name" required placeholder="Ex: John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label>Operational Email</label>
                        <input type="email" name="email" required placeholder="identity@domain.com">
                    </div>

                    <div class="form-group">
                        <label>Security Key (Password)</label>
                        <div class="pass-wrapper">
                            <input type="password" name="password" id="passInput" required placeholder="••••••••">
                            <span class="gen-btn" onclick="generatePass()" title="Generate Random Key">Auto-Gen</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Operational Clearance Level</label>
                        <select name="role" required>
                            <option value="borrower">Level 1 - Borrower</option>
                            <option value="officer">Level 2 - Loan Officer</option>
                            <option value="admin">Level 3 - System Admin</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Initiate Deployment</button>
                </form>
            </div>

            <a href="users.php" class="back-link">← Cancel and Exit to Registry</a>
        </div>
    </main>

    

    <script>
        /**
         * Generates a 14-character high-entropy password
         */
        function generatePass() {
            const charset = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%^&*";
            let retVal = "";
            for (let i = 0; i < 14; ++i) {
                retVal += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            const passInput = document.getElementById('passInput');
            passInput.value = retVal;
            // Reveal password so admin can record/share it with the new user
            passInput.type = 'text'; 
            passInput.style.color = '#f0a500';
            passInput.style.fontWeight = '900';
            passInput.style.fontFamily = 'monospace';
        }

        // Sidebar Responsiveness
        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('dashboardMain');
            sidebar.classList.toggle('active');
            main.style.marginLeft = sidebar.classList.contains('active') ? '240px' : '0';
        });
    </script>
</body>
</html>