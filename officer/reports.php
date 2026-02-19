<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
$role = $user['role'] ?? 'officer'; // Fixes the "Undefined variable $role" error

// --- 1. Fetch Summary Stats ---
$statsQuery = "SELECT 
    (SELECT SUM(amount) FROM loans WHERE status='approved' OR status='completed') as total_disbursed,
    (SELECT SUM(amount_paid) FROM repayments) as total_recovered,
    (SELECT COUNT(*) FROM loans WHERE status='approved') as active_loans_count";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

$totalDisbursed = $stats['total_disbursed'] ?? 0;
$totalRecovered = $stats['total_recovered'] ?? 0;
$outstanding = $totalDisbursed - $totalRecovered;

// --- 2. Fetch Detailed Transactions (including Remaining Balance) ---
$repaymentsQuery = "SELECT r.*, u.full_name, l.remaining_balance 
                    FROM repayments r
                    JOIN loans l ON r.loan_id = l.id
                    JOIN users u ON l.borrower_id = u.id
                    ORDER BY r.payment_date DESC LIMIT 50";
$repaymentsResult = $conn->query($repaymentsQuery);

$pageTitle = "Financial Reports";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* Black Ops Specific Report Styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #111;
            border: 1px solid #222;
            padding: 25px;
            border-radius: 12px;
            border-top: 4px solid #f0a500;
        }
        .stat-label {
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
        }
        .stat-number.success { color: #22c55e; }
        .stat-number.danger { color: #ef4444; }

        .report-section {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #222;
            padding-bottom: 15px;
        }
        .method-badge {
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 4px;
            background: #222;
            color: #aaa;
            text-transform: uppercase;
        }
        .btn-print {
            background: #222;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            border: 1px solid #333;
            cursor: pointer;
        }
        .btn-print:hover { background: #f0a500; color: #000; }
        
        @media print {
            .sidebar, .btn-print, .header { display: none !important; }
            .dashboard-main { margin-left: 0 !important; padding: 0 !important; }
            body { background: white !important; color: black !important; }
            .stat-card, .report-section { border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body style="background: #000; color: #fff;">

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="welcome" style="margin-bottom: 30px;">
            <h2>📊 Tactical Financial Reports</h2>
            <p style="color: #666;">Monitoring disbursements, recoveries, and field liquidity.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Disbursed</div>
                <div class="stat-number">KES <?php echo number_format($totalDisbursed, 2); ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #22c55e;">
                <div class="stat-label">Total Recovered</div>
                <div class="stat-number success">KES <?php echo number_format($totalRecovered, 2); ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #ef4444;">
                <div class="stat-label">Outstanding Balance</div>
                <div class="stat-number danger">KES <?php echo number_format($outstanding, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Deployments</div>
                <div class="stat-number" style="color: #f0a500;"><?php echo $stats['active_loans_count']; ?></div>
            </div>
        </div>

        <div class="report-section">
            <div class="section-header">
                <h3>Transaction History</h3>
               <a href="full_history_report.php" class="btn-print" target="_blank">🖨️ Export Official History</a>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Borrower</th>
                            <th>Loan ID</th>
                            <th>Method</th>
                            <th>Amount Paid</th>
                            <th>Remaining Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($repaymentsResult && $repaymentsResult->num_rows > 0): ?>
                            <?php while($row = $repaymentsResult->fetch_assoc()): ?>
                            <tr>
                                <td style="font-size: 13px; color: #888;">
                                    <?php echo date('M d, Y', strtotime($row['payment_date'])); ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                <td>#<?php echo $row['loan_id']; ?></td>
                                <td><span class="method-badge"><?php echo str_replace('_', ' ', $row['payment_method']); ?></span></td>
                                <td style="color: #22c55e; font-weight: 700;">
                                    KES <?php echo number_format($row['amount_paid'], 2); ?>
                                </td>
                                <td style="color: #f0a500; font-weight: 600;">
                                    KES <?php echo number_format($row['remaining_balance'], 2); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                    No repayment transactions recorded in the system.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('dashboardMain').classList.toggle('shifted');
        });
    </script>
</body>
</html>