<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();

// --- Global Summary ---
$statsQuery = "SELECT 
    (SELECT SUM(total_amount) FROM loans WHERE status IN ('approved', 'completed')) as total_disbursed,
    (SELECT SUM(amount_paid) FROM repayments) as total_recovered";
$stats = $conn->query($statsQuery)->fetch_assoc();

$totalDisbursed = $stats['total_disbursed'] ?? 0;
$totalRecovered = $stats['total_recovered'] ?? 0;
$outstanding = $totalDisbursed - $totalRecovered;

// --- Master Repayment List ---
$repaymentsQuery = "SELECT r.*, u.full_name, l.remaining_balance
                    FROM repayments r
                    JOIN loans l ON r.loan_id = l.id
                    JOIN users u ON l.borrower_id = u.id
                    ORDER BY r.payment_date DESC";
$repayments = $conn->query($repaymentsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master_Audit_<?php echo date('Ymd'); ?></title>
    <style>
        :root { --primary-gold: #c59100; --dark-slate: #2c3e50; }
        body { font-family: 'Segoe UI', sans-serif; background: #e0e0e0; padding: 40px; }
        .statement-container { 
            background: #fff; max-width: 1000px; margin: 0 auto; padding: 60px; 
            border-top: 8px solid var(--primary-gold); box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative;
        }
        .watermark {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 60px; font-weight: 900; color: rgba(0,0,0,0.03); z-index: 0; pointer-events: none;
        }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; border-bottom: 1px solid #eee; padding-bottom: 20px;}
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .info-box { background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
        .statement-table { width: 100%; border-collapse: collapse; }
        .statement-table th { background: var(--dark-slate); color: #fff; padding: 12px; text-align: left; font-size: 11px; }
        .statement-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
        .text-right { text-align: right; }
        @media print { .no-print { display: none; } body { background: #fff; padding: 0; } .statement-container { box-shadow: none; border: none; } }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; background: var(--primary-gold); color: #fff; border: none; cursor: pointer; border-radius: 5px; font-weight: bold;">🖨️ PRINT SYSTEM MASTER LEDGER</button>
    <a href="reports.php" style="margin-left: 10px; color: #666;">Back to Dashboard</a>
</div>

<div class="statement-container">
    <div class="watermark">ADMIN MASTER LEDGER</div>
    
    <div class="header">
        <div>
            <h1 style="margin:0; color: var(--dark-slate);">MICRO<span>LOAN</span> SYSTEM</h1>
            <div style="color: var(--primary-gold); font-weight: bold; font-size: 12px;">ADMINISTRATIVE AUDIT DIVISION</div>
        </div>
        <div style="text-align: right; font-size: 12px;">
            <strong>Audit Ref:</strong> AD-MSTR-<?php echo date('Ymd'); ?><br>
            <strong>Date Generated:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Admin:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div style="font-size: 10px; color: var(--primary-gold); font-weight: bold;">TOTAL DISBURSED</div>
            <div style="font-size: 18px; font-weight: bold;">KES <?php echo number_format($totalDisbursed, 2); ?></div>
        </div>
        <div class="info-box">
            <div style="font-size: 10px; color: #27ae60; font-weight: bold;">TOTAL RECOVERED</div>
            <div style="font-size: 18px; font-weight: bold; color: #27ae60;">KES <?php echo number_format($totalRecovered, 2); ?></div>
        </div>
        <div class="info-box">
            <div style="font-size: 10px; color: #c0392b; font-weight: bold;">SYSTEM RISK (OUTSTANDING)</div>
            <div style="font-size: 18px; font-weight: bold; color: #c0392b;">KES <?php echo number_format($outstanding, 2); ?></div>
        </div>
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Borrower Identity</th>
                <th>Ref ID</th>
                <th>Method</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Rem. Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $repayments->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                <td>#LN-<?php echo $row['loan_id']; ?></td>
                <td><small><?php echo strtoupper(str_replace('_', ' ', $row['payment_method'])); ?></small></td>
                <td class="text-right" style="color: #27ae60; font-weight: bold;">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                <td class="text-right">KES <?php echo number_format($row['remaining_balance'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

 <div style="margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div style="font-size: 10px; color: #999;">
            GENERATED BY SYSTEM ADMIN<br>
            ID: <?php echo $user['id']; ?> | IP: <?php echo $_SERVER['REMOTE_ADDR']; ?><br>
            *** END OF SYSTEM RECORD ***
        </div>
        
        <div>
            <div style="font-family: 'Brush Script MT', cursive; font-size: 24px; margin-bottom: 5px; color: var(--dark-slate); text-align: center;">
                <?php echo htmlspecialchars($user['full_name']); ?>
            </div>
            <div style="text-align: center; border-top: 1px solid #000; width: 220px; padding-top: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">
                Authorized Admin Signature
            </div>
        </div>
    </div>
</div>

</body>
</html>