<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
$officerId = $user['id'];

// Repayments only on loans this officer approved
$query = "SELECT 
    r.id,
    r.amount_paid,
    r.payment_date,
    r.payment_method,
    l.remaining_balance,
    l.id AS loan_id,
    u.full_name
FROM repayments r
JOIN loans l ON r.loan_id = l.id
JOIN users u ON l.borrower_id = u.id
WHERE l.approved_by = ?
ORDER BY r.payment_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $officerId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die('Query failed: ' . $conn->error);
}

$totalStmt = $conn->prepare("SELECT SUM(r.amount_paid) as grand_total FROM repayments r JOIN loans l ON r.loan_id = l.id WHERE l.approved_by = ?");
$totalStmt->bind_param("i", $officerId);
$totalStmt->execute();
$grandTotal = $totalStmt->get_result()->fetch_assoc()['grand_total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Officer_CapitalRecovered_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/full_history_report_officer.css">
</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" style="background:#27ae60;" onclick="window.print()">Print Capital Recovered Report</button>
    <a href="reports.php" class="btn btn-s">Back to Reports</a>
</div>

<div class="statement-container">
    <div class="watermark">CAPITAL RECOVERED</div>

    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="font-size:11px; color:#666; margin:5px 0;">Officer Audit — Capital Recovered Report</p>
        </div>
        <div class="meta-data">
            <strong>Audit Ref:</strong> OF-RECV-<?php echo date('Ymd-His'); ?><br>
            <strong>Report Date:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Officer:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box" style="grid-column: span 3;">
            <div class="section-title" style="color:#27ae60;">TOTAL CAPITAL RECOVERED (YOUR LOANS)</div>
            <div class="stat-val" style="color:#27ae60;">KES <?php echo number_format($grandTotal, 2); ?></div>
        </div>
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th>Payment Date</th>
                <th>Borrower</th>
                <th>Loan Ref</th>
                <th>Method</th>
                <th class="text-right">Amount Paid (KES)</th>
                <th class="text-right">Loan Rem. Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rows = [];
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            if (count($rows) > 0):
                foreach ($rows as $row): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                <td>#LN-<?php echo str_pad($row['loan_id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td><small><?php echo strtoupper(str_replace('_', ' ', $row['payment_method'])); ?></small></td>
                <td class="text-right cr-amt">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                <td class="text-right">KES <?php echo number_format($row['remaining_balance'], 2); ?></td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="6" style="text-align:center; padding:40px; color:#999;">No repayment transactions found for your assigned loans.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9f9f9; font-weight:bold;">
                <td colspan="4" style="text-align:right; padding:10px;">TOTAL RECOVERED</td>
                <td class="text-right" style="color:#27ae60;">KES <?php echo number_format($grandTotal, 2); ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary-footer">
        <div style="font-size:11px; color:#777;">
            *** END OF OFFICER RECOVERY RECORD ***<br>
            Validated against live database logs.
        </div>
        <div>
            <div style="font-family:'Brush Script MT',cursive; font-size:22px; margin-bottom:5px; color:var(--dark-slate); text-align:center;">
                <?php echo htmlspecialchars($user['full_name']); ?>
            </div>
            <div class="signature-block">Officer Certification Signature</div>
        </div>
    </div>
</div>

</body>
</html>