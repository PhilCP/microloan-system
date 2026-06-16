<?php

session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";

// Fetch Logs with User Details (Limited to 100 for performance)
$logsQuery = "SELECT al.*, u.full_name, u.role as user_role 
              FROM activity_logs al 
              JOIN users u ON al.user_id = u.id 
              ORDER BY al.created_at DESC 
              LIMIT 100";
$logsResult = $conn->query($logsQuery);

$pageTitle = "System Audit Logs";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
        
       <link rel="stylesheet" href="../assets/css/dashboard.css">
       <link rel="stylesheet" href="../assets/css/audit-logs.css">

</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; padding: 30px;">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="welcome" style="margin-bottom: 35px;">
           
            <p style="color: #444;">Monitoring encrypted log streams for administrative and operational accountability.</p>
        </div>

        <div class="terminal-header">
            <div>
                <span class="live-indicator"></span>
                <span style="font-size: 11px; color: #f0a500; font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">Live Log Stream </span>
            </div>
            
        </div>

        <div class="log-container">
            
            <table class="log-table">
                <thead>
                    <tr>
                        <th width="18%">Chronology</th>
                        <th width="22%">Authorized Agent</th>
                        <th>Command Execution Output</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($logsResult->num_rows > 0): ?>
                        <?php while($log = $logsResult->fetch_assoc()): ?>
                        <tr>
                            <td class="timestamp"><?php echo date('Y.m.d | H:i:s', strtotime($log['created_at'])); ?></td>
                            <td>
                                <span class="agent-name"><?php echo htmlspecialchars($log['full_name']); ?></span>
                                <span class="role-tag"><?php echo strtoupper($log['user_role']); ?></span>
                            </td>
                            <td class="action-text">
                                <span style="color: #444;"></span> <?php echo htmlspecialchars($log['action']); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 60px; color: #222; text-transform: uppercase; font-weight: 900;">
                                Log Buffer Empty. No activity detected.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Sidebar Toggle 
        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('dashboardMain');
            sidebar.classList.toggle('active');
            main.style.marginLeft = sidebar.classList.contains('active') ? '240px' : '0';
        });

        
       
    </script>
</body>
</html>