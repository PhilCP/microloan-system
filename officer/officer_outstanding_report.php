<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
$officerId = $user['id'];

// Outstanding per loan scoped to this officer
$query = "SELECT 
    l.id AS loan_id,
    l.total_amount,
    l.status,
    l.created_at,
    u.full_name,
    COALESCE(SUM(r.amount_paid), 0) AS total_paid,
    (l.total_amount - COALESCE(SUM(r.amount_paid), 0)) AS balance_outstanding
FROM loans l
JOIN users u ON l.borrower_id = u.id
LEFT JOIN repayments r ON r.loan_id = l.id
WHERE l.status IN ('approved', 'completed')
AND l.approved_by = ?
GROUP BY l.id, l.total_amount, l.status, l.created_at, u.full_name
HAVING balance_outstanding > 0
ORDER BY balance_outstanding DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $officerId);
$stmt->execute();
$result = $stmt->get_result();

// Net outstanding for this officer
$netStmt = $conn->prepare("SELECT 
    SUM(l.amount) as total_disbursed,
    (SELECT SUM(r.amount_paid) FROM repayments r JOIN loans l2 ON r.loan_id = l2.id WHERE l2.approved_by = ?) as total_recovered
FROM loans l WHERE l.status IN ('approved','completed') AND l.approved_by = ?");
$netStmt->bind_param("ii", $officerId, $officerId);
$netStmt->execute();
$summary = $netStmt->get_result()->fetch_assoc();
$outstanding = ($summary['total_disbursed'] ?? 0) - ($summary['total_recovered'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Officer_Outstanding_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/full_history_report_officer.css">
</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" style="background:#c0392b;" onclick="window.print()">Print Outstanding Report</button>
    <a href="reports.php" class="btn btn-s">Back to Reports</a>
</div>

<div class="statement-container">
    <div class="watermark">OUTSTANDING RISK</div>

    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="font-size:11px; color:#666; margin:5px 0;">Officer Audit — Outstanding Balances Report</p>
        </div>
        <div class="meta-data">
            <strong>Audit Ref:</strong> OF-OUTST-<?php echo date('Ymd-His'); ?><br>
            <strong>Report Date:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Officer:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box" style="grid-column: span 3;">
            <div class="section-title" style="color:#c0392b;">NET OUTSTANDING (YOUR PORTFOLIO)</div>
            <div class="stat-val" style="color:#c0392b;">KES <?php echo number_format($outstanding, 2); ?></div>
        </div>
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th>Date Issued</th>
                <th>Borrower</th>
                <th>Loan Ref</th>
                <th>Status</th>
                <th class="text-right">Loan Amount</th>
                <th class="text-right">Total Paid</th>
                <th class="text-right">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rows = [];
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            if (count($rows) > 0):
                foreach ($rows as $row): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                <td>#LN-<?php echo str_pad($row['loan_id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td><small><?php echo strtoupper($row['status']); ?></small></td>
                <td class="text-right">KES <?php echo number_format($row['total_amount'], 2); ?></td>
                <td class="text-right" style="color:#27ae60;">KES <?php echo number_format($row['total_paid'], 2); ?></td>
                <td class="text-right" style="color:#c0392b; font-weight:bold;">KES <?php echo number_format($row['balance_outstanding'], 2); ?></td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="7" style="text-align:center; padding:40px; color:#999;">No outstanding balances found for your portfolio.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9f9f9; font-weight:bold;">
                <td colspan="6" style="text-align:right; padding:10px;">TOTAL OUTSTANDING</td>
                <td class="text-right" style="color:#c0392b;">KES <?php echo number_format($outstanding, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary-footer">
        <div style="font-size:11px; color:#777;">
            *** END OF OFFICER OUTSTANDING RECORD ***<br>
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