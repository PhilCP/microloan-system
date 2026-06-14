<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();

$stmt = $conn->prepare("SELECT l.*, u.full_name AS officer_name
    FROM loans l
    LEFT JOIN users u ON l.approved_by = u.id
    WHERE l.borrower_id = ? AND l.status = 'approved'
    ORDER BY l.created_at DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$loans = $stmt->get_result();

$totals = $conn->prepare("SELECT COUNT(*) as cnt, SUM(total_amount) as total_val, SUM(remaining_balance) as total_rem
    FROM loans WHERE borrower_id = ? AND status = 'approved'");
$totals->bind_param("i", $user['id']);
$totals->execute();
$t = $totals->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ActiveLoans_USR<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?>_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/print-loan-history.css">
</head>
<body>

<div class="btn-box">
    <button class="btn" style="background:#2980b9;color:#fff;" onclick="window.print()">Print Active Loans Report</button>
    <a href="my-loans.php" class="btn" style="background:#333;color:#fff;">Back</a>
</div>

<div class="report-container">
    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="margin:5px 0;color:#666;">Active Loans — <?php echo htmlspecialchars($user['full_name']); ?></p>
        </div>
        <div style="text-align:right;font-size:12px;color:#888;">
            Ref: USR-<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?>-ACTV<br>
            Generated: <?php echo date('d M Y, H:i'); ?>
        </div>
    </div>

    <!-- Summary strip -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:20px 0;padding:16px;background:#f9f9f9;border-radius:8px;border:1px solid #eee;">
        <div style="text-align:center;">
            <div style="font-size:10px;color:#888;font-weight:700;text-transform:uppercase;">Active Loans</div>
            <div style="font-size:22px;font-weight:900;color:#2980b9;"><?php echo $t['cnt']; ?></div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:10px;color:#888;font-weight:700;text-transform:uppercase;">Total Debt</div>
            <div style="font-size:18px;font-weight:900;">KES <?php echo number_format($t['total_val'],2); ?></div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:10px;color:#c0392b;font-weight:700;text-transform:uppercase;">Total Outstanding</div>
            <div style="font-size:18px;font-weight:900;color:#c0392b;">KES <?php echo number_format($t['total_rem'],2); ?></div>
        </div>
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th>Date Issued</th>
                <th>Loan Ref</th>
                <th>Principal</th>
                <th>Total Due</th>
                <th>Paid</th>
                <th>Outstanding</th>
                <th>Rate / Term</th>
                <th>Due Date</th>
                <th>Officer</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rows = [];
            while ($l = $loans->fetch_assoc()) $rows[] = $l;
            if (count($rows) > 0):
                foreach ($rows as $l):
                    $paid = $l['total_amount'] - $l['remaining_balance'];
            ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                <td>#LN-<?php echo str_pad($l['id'],5,'0',STR_PAD_LEFT); ?></td>
                <td>KES <?php echo number_format($l['amount'],2); ?></td>
                <td>KES <?php echo number_format($l['total_amount'],2); ?></td>
                <td style="color:#27ae60;font-weight:700;">KES <?php echo number_format($paid,2); ?></td>
                <td style="color:#c0392b;font-weight:700;">KES <?php echo number_format($l['remaining_balance'],2); ?></td>
                <td><?php echo $l['interest_rate']; ?>% / <?php echo $l['duration_months']; ?> mo</td>
                <td><?php echo $l['due_date'] ? date('d M Y', strtotime($l['due_date'])) : '—'; ?></td>
                <td><?php echo $l['officer_name'] ? htmlspecialchars($l['officer_name']) : '—'; ?></td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="9" style="text-align:center;padding:40px;color:#aaa;">No active loans found.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9f9f9;font-weight:bold;">
                <td colspan="4" style="text-align:right;padding:10px;">TOTALS</td>
                <td style="color:#27ae60;">KES <?php echo number_format($t['total_val'] - $t['total_rem'],2); ?></td>
                <td style="color:#c0392b;">KES <?php echo number_format($t['total_rem'],2); ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:40px;border-top:1px solid #eee;padding-top:20px;text-align:center;">
        <p style="font-size:10px;color:#aaa;">System-generated report of all active loan obligations. For disputes, contact support with your Loan ID as reference.</p>
    </div>
</div>
</body>
</html>