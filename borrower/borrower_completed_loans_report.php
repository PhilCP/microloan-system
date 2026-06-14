<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();

$stmt = $conn->prepare("SELECT l.*, u.full_name AS officer_name
    FROM loans l
    LEFT JOIN users u ON l.approved_by = u.id
    WHERE l.borrower_id = ? AND l.status = 'completed'
    ORDER BY l.created_at DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$loans = $stmt->get_result();

$totals = $conn->prepare("SELECT COUNT(*) as cnt, SUM(total_amount) as total_settled
    FROM loans WHERE borrower_id = ? AND status = 'completed'");
$totals->bind_param("i", $user['id']);
$totals->execute();
$t = $totals->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CompletedLoans_USR<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?>_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/print-loan-history.css">
</head>
<body>

<div class="btn-box">
    <button class="btn" style="background:#27ae60;color:#fff;" onclick="window.print()">Print Completed Loans Report</button>
    <a href="my-loans.php" class="btn" style="background:#333;color:#fff;">Back</a>
</div>

<div class="report-container">
    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="margin:5px 0;color:#666;">Completed Loans — <?php echo htmlspecialchars($user['full_name']); ?></p>
        </div>
        <div style="text-align:right;font-size:12px;color:#888;">
            Ref: USR-<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?>-COMP<br>
            Generated: <?php echo date('d M Y, H:i'); ?>
        </div>
    </div>

    <!-- Summary -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:20px 0;padding:16px;background:#f0faf4;border-radius:8px;border:1px solid #a7f3d0;">
        <div style="text-align:center;">
            <div style="font-size:10px;color:#27ae60;font-weight:700;text-transform:uppercase;">Loans Fully Settled</div>
            <div style="font-size:28px;font-weight:900;color:#27ae60;"><?php echo $t['cnt']; ?></div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:10px;color:#888;font-weight:700;text-transform:uppercase;">Total Capital Repaid</div>
            <div style="font-size:20px;font-weight:900;">KES <?php echo number_format($t['total_settled'],2); ?></div>
        </div>
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th>Date Applied</th>
                <th>Loan Ref</th>
                <th>Principal</th>
                <th>Total Repaid</th>
                <th>Rate / Term</th>
                <th>Officer</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rows = [];
            while ($l = $loans->fetch_assoc()) $rows[] = $l;
            if (count($rows) > 0):
                foreach ($rows as $l): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                <td>#LN-<?php echo str_pad($l['id'],5,'0',STR_PAD_LEFT); ?></td>
                <td>KES <?php echo number_format($l['amount'],2); ?></td>
                <td style="color:#27ae60;font-weight:700;">KES <?php echo number_format($l['total_amount'],2); ?></td>
                <td><?php echo $l['interest_rate']; ?>% / <?php echo $l['duration_months']; ?> mo</td>
                <td><?php echo $l['officer_name'] ? htmlspecialchars($l['officer_name']) : '—'; ?></td>
                <td class="remarks-text">
                    <?php echo !empty($l['admin_remarks'])
                        ? htmlspecialchars($l['admin_remarks'])
                        : '<span style="color:#ccc;">—</span>'; ?>
                </td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:#aaa;">No completed loans yet.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f0faf4;font-weight:bold;">
                <td colspan="3" style="text-align:right;padding:10px;">TOTAL REPAID</td>
                <td style="color:#27ae60;">KES <?php echo number_format($t['total_settled'],2); ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:40px;border-top:1px solid #eee;padding-top:20px;text-align:center;">
        <p style="font-size:10px;color:#aaa;">System-generated record of fully settled loans. For disputes, contact support with your Loan ID as reference.</p>
    </div>
</div>
</body>
</html>