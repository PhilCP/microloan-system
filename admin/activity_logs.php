<?php
/**
 * System Audit Trail
 * Surveillance module for monitoring administrative and operational integrity.
 */
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";

// --- 1. Fetch Logs with User Details (Limited to 100 for performance) ---
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
    <style>
        body { background: #000; color: #fff; font-family: 'Inter', sans-serif; }

        /* Terminal Aesthetic Log Container */
        .log-container {
            background: #050505;
            border: 1px solid #1a1a1a;
            border-radius: 12px;
            padding: 25px;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .log-table th {
            text-align: left;
            color: #444;
            padding: 12px;
            border-bottom: 1px solid #1a1a1a;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 2px;
        }

        .log-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #0a0a0a;
            color: #888;
            vertical-align: top;
        }

        .log-table tr:hover td { color: #ccc; background: rgba(255,255,255,0.01); }

        .timestamp { color: #333; font-size: 11px; white-space: nowrap; }
        .agent-name { color: #f0a500; font-weight: 800; }
        .action-text { color: #22c55e; } /* Operational Green */
        
        .role-tag {
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 3px;
            background: #111;
            color: #444;
            margin-left: 8px;
            border: 1px solid #222;
        }

        /* Status Header */
        .terminal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px 20px;
            background: #0a0a0a;
            border-radius: 8px;
            border-left: 4px solid #f0a500;
        }

        .live-indicator {
            height: 8px;
            width: 8px;
            background: #f0a500;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; box-shadow: 0 0 0 0 rgba(240, 165, 0, 0.7); }
            70% { opacity: 0.5; box-shadow: 0 0 0 10px rgba(240, 165, 0, 0); }
            100% { opacity: 1; box-shadow: 0 0 0 0 rgba(240, 165, 0, 0); }
        }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; padding: 30px;">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="welcome" style="margin-bottom: 35px;">
            <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">System Audit Trail</h2>
            <p style="color: #444;">Monitoring encrypted log streams for administrative and operational accountability.</p>
        </div>

        <div class="terminal-header">
            <div>
                <span class="live-indicator"></span>
                <span style="font-size: 11px; color: #f0a500; font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">Live Log Stream v1.0</span>
            </div>
            <span style="font-size: 10px; color: #333; text-transform: uppercase; font-weight: 800;">Buffer: 100 Entries</span>
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
                                <span style="color: #444;">$</span> <?php echo htmlspecialchars($log['action']); ?>
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
        // Sidebar Toggle with persistent layout
        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('dashboardMain');
            sidebar.classList.toggle('active');
            main.style.marginLeft = sidebar.classList.contains('active') ? '240px' : '0';
        });

        // Optional: Auto-refresh logs every 60 seconds
        /*
        setTimeout(function(){
           location.reload();
        }, 60000);
        */
    </script>
</body>
</html>