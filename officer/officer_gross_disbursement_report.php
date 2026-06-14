<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
$officerId = $user['id'];

// Only loans approved by this officer
$query = "SELECT 
    l.id,
    l.total_amount,
    l.status,
    l.created_at AS disbursed_date,
    u.full_name
FROM loans l
JOIN users u ON l.borrower_id = u.id
WHERE l.status IN ('approved', 'completed')
AND l.approved_by = ?
ORDER BY l.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $officerId);
$stmt->execute();
$result = $stmt->get_result();

$totalStmt = $conn->prepare("SELECT SUM(total_amount) as grand_total FROM loans WHERE status IN ('approved','completed') AND approved_by = ?");
$totalStmt->bind_param("i", $officerId);
$totalStmt->execute();
$grandTotal = $totalStmt->get_result()->fetch_assoc()['grand_total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Officer_GrossDisbursement_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/full_history_report_officer.css">
</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" onclick="window.print()">Print Gross Disbursement Report</button>
    <a href="reports.php" class="btn btn-s">Back to Reports</a>
</div>

<div class="statement-container">
    <div class="watermark">GROSS DISBURSEMENT</div>

    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="font-size:11px; color:#666; margin:5px 0;">Officer Audit — Gross Disbursement Report</p>
        </div>
        <div class="meta-data">
            <strong>Audit Ref:</strong> OF-DISB-<?php echo date('Ymd-His'); ?><br>
            <strong>Report Date:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Officer:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box" style="grid-column: span 3;">
            <div class="section-title">TOTAL GROSS DISBURSEMENT (YOUR LOANS)</div>
            <div class="stat-val">KES <?php echo number_format($grandTotal, 2); ?></div>
        </div>
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th>Date Disbursed</th>
                <th>Borrower</th>
                <th>Loan Ref</th>
                <th>Status</th>
                <th class="text-right">Amount (KES)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rows = [];
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            if (count($rows) > 0):
                foreach ($rows as $row): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($row['disbursed_date'])); ?></td>
                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                <td>#LN-<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td><small><?php echo strtoupper($row['status']); ?></small></td>
                <td class="text-right" style="font-weight:bold;">KES <?php echo number_format($row['total_amount'], 2); ?></td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="5" style="text-align:center; padding:40px; color:#999;">No disbursements found for your account.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9f9f9; font-weight:bold;">
                <td colspan="4" style="text-align:right; padding:10px;">GRAND TOTAL DISBURSED</td>
                <td class="text-right" style="color:var(--primary-gold, #b7870a);">KES <?php echo number_format($grandTotal, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary-footer">
        <div style="font-size:11px; color:#777;">
            *** END OF OFFICER DISBURSEMENT RECORD ***<br>
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