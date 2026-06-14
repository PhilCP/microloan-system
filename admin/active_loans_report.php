<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();

// Active Loans Data
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
GROUP BY l.id, l.total_amount, l.interest_rate, l.duration_months, l.created_at, l.remaining_balance, u.full_name, u.email
ORDER BY l.created_at DESC";

$result = $conn->query($query);

$countQuery = "SELECT COUNT(*) as total, SUM(total_amount) as total_value FROM loans WHERE status='approved'";
$countRow = $conn->query($countQuery)->fetch_assoc();
$totalActive = $countRow['total'] ?? 0;
$totalValue  = $countRow['total_value'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ActiveLoans_<?php echo date('Ymd'); ?></title>
    <link rel="stylesheet" href="../assets/css/full_history_report.css">
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom:20px;">
    <button onclick="window.print()" style="padding:10px 20px; background:#2980b9; color:#fff; border:none; cursor:pointer; border-radius:5px; font-weight:bold;">PRINT ACTIVE LOANS REPORT</button>
    <a href="reports.php" style="margin-left:10px; color:#666;">Back to Dashboard</a>
</div>

<div class="statement-container">
    <div class="watermark">ACTIVE LOANS</div>

    <div class="header">
        <div>
            <h1 style="margin:0; color:var(--dark-slate);">MICRO<span>LOAN</span> SYSTEM</h1>
            <div style="color:#2980b9; font-weight:bold; font-size:12px;">ACTIVE LOANS PORTFOLIO REPORT</div>
        </div>
        <div style="text-align:right; font-size:12px;">
            <strong>Ref:</strong> AD-ACTV-<?php echo date('Ymd'); ?><br>
            <strong>Generated:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Admin:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div style="font-size:10px; color:#2980b9; font-weight:bold;">TOTAL ACTIVE LOANS</div>
            <div style="font-size:22px; font-weight:bold; color:#2980b9;"><?php echo number_format($totalActive); ?></div>
        </div>
        <div class="info-box" style="grid-column: span 2;">
            <div style="font-size:10px; color:var(--primary-gold); font-weight:bold;">TOTAL PORTFOLIO VALUE</div>
            <div style="font-size:22px; font-weight:bold;">KES <?php echo number_format($totalValue, 2); ?></div>
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
            while($row = $result->fetch_assoc()) $rows[] = $row;
            if(count($rows) > 0):
                foreach($rows as $row): ?>
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
                <td style="font-size:11px;">
                    <?php echo $row['interest_rate']; ?>% / <?php echo $row['duration_months']; ?> mo
                </td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="7" style="text-align:center; padding:40px; color:#999;">NO ACTIVE LOANS FOUND.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9f9f9; font-weight:bold;">
                <td colspan="3" style="text-align:right; padding:10px;">TOTALS</td>
                <td class="text-right">KES <?php echo number_format($totalValue, 2); ?></td>
                <td class="text-right" style="color:#27ae60;">
                    KES <?php 
                    $totalPaid = array_sum(array_column($rows, 'total_paid'));
                    echo number_format($totalPaid, 2); ?>
                </td>
                <td class="text-right" style="color:#c0392b;">
                    KES <?php 
                    $totalRem = array_sum(array_column($rows, 'remaining_balance'));
                    echo number_format($totalRem, 2); ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:50px; display:flex; justify-content:space-between; align-items:flex-end;">
        <div style="font-size:10px; color:#999;">
            GENERATED BY SYSTEM ADMIN<br>
            ID: <?php echo $user['id']; ?> | IP: <?php echo $_SERVER['REMOTE_ADDR']; ?><br>
            *** END OF ACTIVE LOANS RECORD ***
        </div>
        <div>
            <div style="font-family:'Brush Script MT',cursive; font-size:24px; margin-bottom:5px; color:var(--dark-slate); text-align:center;">
                <?php echo htmlspecialchars($user['full_name']); ?>
            </div>
            <div style="text-align:center; border-top:1px solid #000; width:220px; padding-top:10px; font-size:11px; text-transform:uppercase; letter-spacing:1px;">
                Authorized Admin Signature
            </div>
        </div>
    </div>
</div>

</body>
</html>