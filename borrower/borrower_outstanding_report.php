<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();

$stmt = $conn->prepare("SELECT l.*, u.full_name AS officer_name
    FROM loans l
    LEFT JOIN users u ON l.approved_by = u.id
    WHERE l.borrower_id = ?
    AND l.status IN ('approved','completed')
    AND l.remaining_balance > 0
    ORDER BY l.remaining_balance DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$loans = $stmt->get_result();

$totals = $conn->prepare("SELECT SUM(remaining_balance) as total_outstanding, SUM(total_amount) as total_debt
    FROM loans WHERE borrower_id = ? AND status IN ('approved','completed') AND remaining_balance > 0");
$totals->bind_param("i", $user['id']);
$totals->execute();
$t = $totals->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Outstanding_USR<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?>_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/print-loan-history.css">
</head>
<body>

<div class="btn-box">
    <button class="btn" style="background:#c0392b;color:#fff;" onclick="window.print()">Print Outstanding Balance Report</button>
    <a href="my-loans.php" class="btn" style="background:#333;color:#fff;">Back</a>
</div>

<div class="report-container">
    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="margin:5px 0;color:#666;">Outstanding Balances — <?php echo htmlspecialchars($user['full_name']); ?></p>
        </div>
        <div style="text-align:right;font-size:12px;color:#888;">
            Ref: USR-<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?>-OUTST<br>
            Generated: <?php echo date('d M Y, H:i'); ?>
        </div>
    </div>

    <!-- Single net figure -->
    <div style="margin:20px 0;padding:20px;background:#fff5f5;border-radius:8px;border:1px solid #fca5a5;border-left:5px solid #c0392b;text-align:center;">
        <div style="font-size:11px;color:#c0392b;font-weight:800;text-transform:uppercase;letter-spacing:1px;">Total Amount You Still Owe</div>
        <div style="font-size:32px;font-weight:900;color:#c0392b;margin-top:6px;">KES <?php echo number_format($t['total_outstanding'],2); ?></div>
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th>Date Issued</th>
                <th>Loan Ref</th>
                <th>Total Loan</th>
                <th>Paid So Far</th>
                <th>Outstanding</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rows = [];
            while ($l = $loans->fetch_assoc()) $rows[] = $l;
            if (count($rows) > 0):
                foreach ($rows as $l):
                    $paid = $l['total_amount'] - $l['remaining_balance'];
                    $isOverdue = $l['due_date'] && strtotime(date('Y-m-d')) > strtotime($l['due_date']) && $l['status'] === 'approved';
            ?>
            <tr <?php echo $isOverdue ? 'style="background:#fff5f5;"' : ''; ?>>
                <td><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                <td>#LN-<?php echo str_pad($l['id'],5,'0',STR_PAD_LEFT); ?></td>
                <td>KES <?php echo number_format($l['total_amount'],2); ?></td>
                <td style="color:#27ae60;font-weight:700;">KES <?php echo number_format($paid,2); ?></td>
                <td style="color:#c0392b;font-weight:800;">KES <?php echo number_format($l['remaining_balance'],2); ?></td>
                <td style="<?php echo $isOverdue ? 'color:#c0392b;font-weight:700;' : ''; ?>">
                    <?php echo $l['due_date'] ? date('d M Y', strtotime($l['due_date'])) : '—'; ?>
                    <?php if ($isOverdue): ?><br><small style="color:#c0392b;">⚠ OVERDUE</small><?php endif; ?>
                </td>
                <td><span class="badge bg-<?php echo $l['status']; ?>"><?php echo ucfirst($l['status']); ?></span></td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:#aaa;">No outstanding balances. You're all clear!</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#fff5f5;font-weight:bold;">
                <td colspan="4" style="text-align:right;padding:10px;">TOTAL OUTSTANDING</td>
                <td style="color:#c0392b;">KES <?php echo number_format($t['total_outstanding'],2); ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:40px;border-top:1px solid #eee;padding-top:20px;text-align:center;">
        <p style="font-size:10px;color:#aaa;">System-generated outstanding balance report. Contact support with your Loan ID for any disputes.</p>
    </div>
</div>
</body>
</html>