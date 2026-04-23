<?php

session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";
$message = "";

//Deployment Execution Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? ''); // Add phone field
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $target_role = $_POST['role'];

    // Validation
    if (empty($full_name) || empty($email) || empty($_POST['password'])) {
        $message = "<div class='alert error'>⚠️ All fields are required.</div>";
    } else {
        // Identity Collision Check
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $message = "<div class='alert error'> ACCESS DENIED: Email already exists in database, use another email.</div>";
        } else {
            // SQL Injection protected via Prepared Statements
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $phone, $password, $target_role);
            
            if ($stmt->execute()) {
                $newUserId = $conn->insert_id;
                
                // Log the deployment for audit trails
                $logAction = "Admin created new user: $full_name ($target_role) - ID: $newUserId";
                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                $logStmt->bind_param("is", $user['id'], $logAction);
                $logStmt->execute();
                
                // Redirect with success message
                $_SESSION['success_message'] = " User created successfully! ID: #$newUserId";
                header("Location: users.php");
                exit();
            } else {
                $message = "<div class='alert error'> CRITICAL: Deployment sequence failed. Error: " . $conn->error . "</div>";
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
                        <label>Phone Number (Optional)</label>
                        <input type="text" name="phone" placeholder="+254 700 000 000">
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
                            <option value="borrower">Level 1: Borrower</option>
                            <option value="officer">Level 2: Loan Officer</option>
                            <option value="admin">Level 3: System Admin</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Initiate Deployment</button>
                </form>
            </div>

            <a href="users.php" class="back-link">← Cancel and Exit to Registry</a>
        </div>
    </main>

    <script>
      
        // logic to generate a 14 char password with uppercase, lowercase, numbers, and symbols
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
        // document.getElementById('sidebarToggle').addEventListener('click',()=>{
        //     const sidebar = document.getElementById('sidebar');
        //     const main = document.getElementById('dashboardMain');
        //     sidebar.classList.toggle('active');
        //     main.style.marginLeft = sidebar.classList.contains('active') ? '240px' : '0';
        // });
    </script>
</body>
</html>