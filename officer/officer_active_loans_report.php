<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
$officerId = $user['id'];

// Active loans approved by this officer only
$query = "SELECT 
    l.id AS loan_id,
    l.total_amount,
    l.interest_rate,
    l.duration_months,
    l.created_at,
    l.remaining_balance,
    u.full_name,
    u.email,
    COALESCE(SUM(r.amount_paid), 0) AS total_paid
FROM loans l
JOIN users u ON l.borrower_id = u.id
LEFT JOIN repayments r ON r.loan_id = l.id
WHERE l.status = 'approved'
AND l.approved_by = ?
GROUP BY l.id, l.total_amount, l.interest_rate, l.duration_months, l.created_at, l.remaining_balance, u.full_name, u.email
ORDER BY l.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $officerId);
$stmt->execute();
$result = $stmt->get_result();

$countStmt = $conn->prepare("SELECT COUNT(*) as total, SUM(total_amount) as total_value FROM loans WHERE status='approved' AND approved_by = ?");
$countStmt->bind_param("i", $officerId);
$countStmt->execute();
$countRow = $countStmt->get_result()->fetch_assoc();
$totalActive = $countRow['total'] ?? 0;
$totalValue  = $countRow['total_value'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Officer_ActiveLoans_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/full_history_report_officer.css">
</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" style="background:#2980b9;" onclick="window.print()">Print Active Loans Report</button>
    <a href="reports.php" class="btn btn-s">Back to Reports</a>
</div>

<div class="statement-container">
    <div class="watermark">ACTIVE LOANS</div>

    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="font-size:11px; color:#666; margin:5px 0;">Officer Audit — Active Loans Portfolio</p>
        </div>
        <div class="meta-data">
            <strong>Audit Ref:</strong> OF-ACTV-<?php echo date('Ymd-His'); ?><br>
            <strong>Report Date:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Officer:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="section-title" style="color:#2980b9;">ACTIVE DEPLOYMENTS</div>
            <div class="stat-val" style="color:#2980b9;"><?php echo number_format($totalActive); ?></div>
        </div>
        <div class="info-box" style="grid-column: span 2;">
            <div class="section-title">TOTAL PORTFOLIO VALUE</div>
            <div class="stat-val">KES <?php echo number_format($totalValue, 2); ?></div>
        </div>
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th>Date Issued</th>
                <th>Borrower</th>
                <th>Loan Ref</th>
                <th class="text-right">Loan Amount</th>
                <th class="text-right">Paid So Far</th>
                <th class="text-right">Rem. Balance</th>
                <th>Rate / Term</th>
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
                <td>
                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                    <small style="color:#888;"><?php echo htmlspecialchars($row['email']); ?></small>
                </td>
                <td>#LN-<?php echo str_pad($row['loan_id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td class="text-right">KES <?php echo number_format($row['total_amount'], 2); ?></td>
                <td class="text-right" style="color:#27ae60;">KES <?php echo number_format($row['total_paid'], 2); ?></td>
                <td class="text-right" style="color:#c0392b; font-weight:bold;">KES <?php echo number_format($row['remaining_balance'], 2); ?></td>
                <td style="font-size:11px;"><?php echo $row['interest_rate']; ?>% / <?php echo $row['duration_months']; ?> mo</td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="7" style="text-align:center; padding:40px; color:#999;">No active loans found in your portfolio.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9f9f9; font-weight:bold;">
                <td colspan="3" style="text-align:right; padding:10px;">TOTALS</td>
                <td class="text-right">KES <?php echo number_format($totalValue, 2); ?></td>
                <td class="text-right" style="color:#27ae60;">
                    KES <?php echo number_format(array_sum(array_column($rows, 'total_paid')), 2); ?>
                </td>
                <td class="text-right" style="color:#c0392b;">
                    KES <?php echo number_format(array_sum(array_column($rows, 'remaining_balance')), 2); ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary-footer">
        <div style="font-size:11px; color:#777;">
            *** END OF OFFICER ACTIVE LOANS RECORD ***<br>
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