<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
$officerId = $user['id']; //scope all queries to this officer only

// Fetch Summary Stats filtered to this officer's loans only
$statsStmt = $conn->prepare("SELECT 
    (SELECT SUM(l.amount) 
     FROM loans l 
     WHERE l.status IN ('approved', 'completed') 
     AND l.approved_by = ?) as total_disbursed,

    (SELECT SUM(r.amount_paid) 
     FROM repayments r 
     JOIN loans l ON r.loan_id = l.id 
     WHERE l.approved_by = ?) as total_recovered");

$statsStmt->bind_param("ii", $officerId, $officerId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

$totalDisbursed = $stats['total_disbursed'] ?? 0;
$totalRecovered = $stats['total_recovered'] ?? 0;
$outstanding    = $totalDisbursed - $totalRecovered;

// Fetch Repayments — only for loans assigned to this officer
$repStmt = $conn->prepare("SELECT r.*, u.full_name, l.total_amount as loan_total, l.remaining_balance
                    FROM repayments r
                    JOIN loans l ON r.loan_id = l.id
                    JOIN users u ON l.borrower_id = u.id
                    WHERE l.approved_by = ?
                    ORDER BY r.payment_date DESC, r.id DESC");

$repStmt->bind_param("i", $officerId);
$repStmt->execute();
$repayments = $repStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Officer_Repayment_History_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/full_history_report_officer.css">
</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" onclick="window.print()">Print Ledger</button>
    <a href="reports.php" class="btn btn-s">Back to Reports</a>
</div>

<div class="statement-container">
    <div class="watermark">OFFICER LEDGER</div>

    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="font-size: 11px; color: #666; margin: 5px 0;">Officer Audit — Personal Repayment History</p>
        </div>
        <div class="meta-data">
            <strong>Audit Ref:</strong> OF-<?php echo date('Ymd-His'); ?><br>
            <strong>Report Date:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Officer:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="section-title">Total Disbursed (Your Loans)</div>
            <div class="stat-val">KES <?php echo number_format($totalDisbursed, 2); ?></div>
        </div>
        <div class="info-box">
            <div class="section-title">Total Recovered Capital</div>
            <div class="stat-val" style="color: #27ae60;">KES <?php echo number_format($totalRecovered, 2); ?></div>
        </div>
        <div class="info-box">
            <div class="section-title">Outstanding (Your Portfolio)</div>
            <div class="stat-val" style="color: #c0392b;">KES <?php echo number_format($outstanding, 2); ?></div>
        </div>
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Borrower</th>
                <th>Loan Ref</th>
                <th>Method</th>
                <th class="text-right">Credit (Received)</th>
                <th class="text-right">Remaining Bal.</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($repayments->num_rows > 0): ?>
                <?php while ($row = $repayments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                    <td>#LN-<?php echo $row['loan_id']; ?></td>
                    <td><small><?php echo strtoupper(str_replace('_', ' ', $row['payment_method'])); ?></small></td>
                    <td class="text-right cr-amt">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                    <td class="text-right">KES <?php echo number_format($row['remaining_balance'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 40px; color:#999;">
                        No repayment transactions found for your assigned loans.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="summary-footer">
        <div style="font-size: 11px; color: #777;">
            *** END OF OFFICER RECORD ***<br>
            Validated against live database logs.
        </div>
        <div>
            <div style="font-family: 'Brush Script MT', cursive; font-size: 22px; margin-bottom: 5px; color: var(--dark-slate); text-align: center;">
                <?php echo htmlspecialchars($user['full_name']); ?>
            </div>
            <div class="signature-block">Officer Certification Signature</div>
        </div>
    </div>
</div>

</body>
</html>