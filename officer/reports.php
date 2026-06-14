<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
$role = $user['role'] ?? 'officer';
$officerId = $user['id']; // scope all queries to this officer

// Fetch Summary Stats filtered by this officer's assigned loans only
$statsQuery = "SELECT 
    (SELECT SUM(l.amount) 
     FROM loans l 
     WHERE (l.status='approved' OR l.status='completed') 
     AND l.approved_by = ?) as total_disbursed,

    (SELECT SUM(r.amount_paid) 
     FROM repayments r 
     JOIN loans l ON r.loan_id = l.id 
     WHERE l.approved_by = ?) as total_recovered,

    (SELECT COUNT(*) 
     FROM loans 
     WHERE status='approved' 
     AND approved_by = ?) as active_loans_count";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("iii", $officerId, $officerId, $officerId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

$totalDisbursed = $stats['total_disbursed'] ?? 0;
$totalRecovered = $stats['total_recovered'] ?? 0;
$outstanding    = $totalDisbursed - $totalRecovered;

// Fetch Detailed Transactions — only repayments on loans assigned to this officer
$repaymentsQuery = "SELECT r.*, u.full_name, l.remaining_balance 
                    FROM repayments r
                    JOIN loans l ON r.loan_id = l.id
                    JOIN users u ON l.borrower_id = u.id
                    WHERE l.approved_by = ?
                    ORDER BY r.payment_date DESC
                    LIMIT 50";

$repStmt = $conn->prepare($repaymentsQuery);
$repStmt->bind_param("i", $officerId);
$repStmt->execute();
$repaymentsResult = $repStmt->get_result();

$pageTitle = "Financial Reports";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/officer-reports.css">
</head>
<body style="background: #000;">

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s; padding: 30px; color: #fff;">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="welcome" style="margin-bottom: 30px;">
            <h2>Financial Reports</h2>
            <p style="color: #666;">Monitoring your disbursements, recoveries, and active loans.</p>
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
           <div style="display: flex; gap: 8px; flex-wrap: wrap;">
    <a href="full_history_report.php"                    class="btn-print" target="_blank"> Full History Report</a>
    <a href="officer_gross_disbursement_report.php"      class="btn-print" target="_blank" style="background:#b7870a;"> Gross Disbursement Report</a>
    <a href="officer_capital_recovered_report.php"       class="btn-print" target="_blank" style="background:#1a7a3f;"> Capital Recovered Report</a>
    <a href="officer_outstanding_report.php"             class="btn-print" target="_blank" style="background:#a93226;"> Outstanding Report</a>
    <a href="officer_active_loans_report.php"            class="btn-print" target="_blank" style="background:#1a5c9e;"> Active Loans Report</a>
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
                            <?php while ($row = $repaymentsResult->fetch_assoc()): ?>
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
                                    No repayment transactions recorded for your loans.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>