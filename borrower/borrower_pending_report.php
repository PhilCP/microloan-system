<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();

$stmt = $conn->prepare("SELECT l.*
    FROM loans l
    WHERE l.borrower_id = ? AND l.status = 'pending'
    ORDER BY l.created_at DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$loans = $stmt->get_result();

$totals = $conn->prepare("SELECT COUNT(*) as cnt, SUM(amount) as total_requested
    FROM loans WHERE borrower_id = ? AND status = 'pending'");
$totals->bind_param("i", $user['id']);
$totals->execute();
$t = $totals->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PendingApplications_USR<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?>_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/print-loan-history.css">
</head>
<body>

<div class="btn-box">
    <button class="btn" style="background:#b7870a;color:#fff;" onclick="window.print()">Print Pending Applications Report</button>
    <a href="my-loans.php" class="btn" style="background:#333;color:#fff;">Back</a>
</div>

<div class="report-container">
    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="margin:5px 0;color:#666;">Pending Applications — <?php echo htmlspecialchars($user['full_name']); ?></p>
        </div>
        <div style="text-align:right;font-size:12px;color:#888;">
            Ref: USR-<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?>-PEND<br>
            Generated: <?php echo date('d M Y, H:i'); ?>
        </div>
    </div>

    <!-- Summary -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:20px 0;padding:16px;background:#fffbeb;border-radius:8px;border:1px solid #fde68a;">
        <div style="text-align:center;">
            <div style="font-size:10px;color:#b7870a;font-weight:700;text-transform:uppercase;">Awaiting Review</div>
            <div style="font-size:28px;font-weight:900;color:#b7870a;"><?php echo $t['cnt']; ?></div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:10px;color:#888;font-weight:700;text-transform:uppercase;">Total Requested</div>
            <div style="font-size:20px;font-weight:900;">KES <?php echo number_format($t['total_requested'],2); ?></div>
        </div>
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th>Date Submitted</th>
                <th>Loan Ref</th>
                <th>Principal Requested</th>
                <th>Duration</th>
                <th>Interest Rate</th>
                <th>Purpose</th>
                <th>Collateral</th>
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
                <td style="font-weight:700;">KES <?php echo number_format($l['amount'],2); ?></td>
                <td><?php echo $l['duration_months']; ?> months</td>
                <td><?php echo $l['interest_rate']; ?>%</td>
                <td class="remarks-text">
                    <?php echo !empty($l['purpose'])
                        ? htmlspecialchars($l['purpose'])
                        : '<span style="color:#ccc;">—</span>'; ?>
                </td>
                <td>
                    <?php echo !empty($l['collateral_type'])
                        ? htmlspecialchars($l['collateral_type'])
                        : '<span style="color:#ccc;">—</span>'; ?>
                </td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:#aaa;">No pending applications at this time.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#fffbeb;font-weight:bold;">
                <td colspan="2" style="text-align:right;padding:10px;">TOTAL REQUESTED</td>
                <td style="color:#b7870a;">KES <?php echo number_format($t['total_requested'],2); ?></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:40px;border-top:1px solid #eee;padding-top:20px;text-align:center;">
        <p style="font-size:10px;color:#aaa;">These applications are currently under review. You will be notified once a decision is made. Contact support with your Loan ID for enquiries.</p>
    </div>
</div>
</body>
</html>