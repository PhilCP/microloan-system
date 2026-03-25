<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();

//Fetch System Summary for Header
$statsQuery = "SELECT 
    (SELECT SUM(amount) FROM loans WHERE status IN ('approved', 'completed')) as total_disbursed,
    (SELECT SUM(amount_paid) FROM repayments) as total_recovered";
$stats = $conn->query($statsQuery)->fetch_assoc();

$totalDisbursed = $stats['total_disbursed'] ?? 0;
$totalRecovered = $stats['total_recovered'] ?? 0;
$outstanding = $totalDisbursed - $totalRecovered;

//Fetch All Repayments for the History Table
$repaymentsQuery = "SELECT r.*, u.full_name, l.total_amount as loan_total, l.remaining_balance
                    FROM repayments r
                    JOIN loans l ON r.loan_id = l.id
                    JOIN users u ON l.borrower_id = u.id
                    ORDER BY r.payment_date DESC, r.id DESC";
$repayments = $conn->query($repaymentsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global_Repayment_History_<?php echo date('Ymd'); ?></title>
    <style>
        :root { --primary-gold: #c59100; --dark-slate: #2c3e50; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #e0e0e0; padding: 40px; margin: 0; }
        
        .statement-container { 
            background: #fff; 
            max-width: 1000px; 
            margin: 0 auto; 
            padding: 60px; 
            position: relative;
            border-top: 8px solid var(--primary-gold);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .watermark {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 70px; font-weight: 900; color: rgba(197, 145, 0, 0.03);
            white-space: nowrap; pointer-events: none; text-transform: uppercase;
            letter-spacing: 10px; border: 15px solid rgba(197, 145, 0, 0.03);
            padding: 20px; border-radius: 20px; z-index: 0;
        }

        .header { display: flex; justify-content: space-between; margin-bottom: 50px; position: relative; z-index: 1;}
        .logo-area h1 { margin: 0; color: var(--dark-slate); font-size: 24px; letter-spacing: 1px; }
        .logo-area span { color: var(--primary-gold); font-weight: bold; }
        
        .meta-data { text-align: right; font-size: 13px; line-height: 1.6; }

        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .info-box { background: #fcfcfc; padding: 15px; border: 1px solid #eee; border-radius: 8px; }
        .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--primary-gold); margin-bottom: 8px; font-weight: bold; }
        .stat-val { font-size: 18px; font-weight: bold; color: var(--dark-slate); }

        .statement-table { width: 100%; border-collapse: collapse; margin-top: 20px; position: relative; z-index: 1; }
        .statement-table th { background: var(--dark-slate); color: #fff; padding: 12px 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .statement-table td { padding: 12px 10px; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
        
        .text-right { text-align: right; }
        .cr-amt { color: #27ae60; font-weight: bold; }

        .summary-footer { margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-end; }
        .signature-block { border-top: 1px solid #333; width: 200px; text-align: center; padding-top: 10px; font-size: 11px; }

        .btn-box { text-align: center; margin-bottom: 20px; }
        .btn { padding: 12px 30px; border-radius: 5px; cursor: pointer; border: none; font-weight: bold; transition: 0.3s; }
        .btn-p { background: var(--primary-gold); color: white; }
        .btn-s { background: #333; color: white; text-decoration: none; display: inline-block; margin-left: 10px; }

        @media print {
            .btn-box { display: none; }
            body { background: #fff; padding: 0; }
            .statement-container { box-shadow: none; border-top: 4px solid var(--primary-gold); max-width: 100%; padding: 10px; }
        }
    </style>
</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" onclick="window.print()">🖨️ Print Master Ledger</button>
    <a href="reports.php" class="btn btn-s">Back to Reports</a>
</div>

<div class="statement-container">
    <div class="watermark">MASTER LEDGER</div>
    
    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="font-size: 11px; color: #666; margin: 5px 0;">Official System Audit - Global Repayments</p>
        </div>
        <div class="meta-data">
            <strong>Audit Ref:</strong> AD-<?php echo date('Ymd-His'); ?><br>
            <strong>Report Date:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Officer:</strong> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="section-title">Total Field Disbursement</div>
            <div class="stat-val">KES <?php echo number_format($totalDisbursed, 2); ?></div>
        </div>
        <div class="info-box">
            <div class="section-title">Total Recovered Capital</div>
            <div class="stat-val" style="color: #27ae60;">KES <?php echo number_format($totalRecovered, 2); ?></div>
        </div>
        <div class="info-box">
            <div class="section-title">Current System Risk (Outstanding)</div>
            <div class="stat-val" style="color: #c0392b;">KES <?php echo number_format($outstanding, 2); ?></div>
        </div>
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Borrower</th>
                <th>Loan Ref</th>
                <th>Method</th>
                <th class="text-right">Credit (Received)</th>
                <th class="text-right">Remaining Bal.</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $repayments->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                <td>#LN-<?php echo $row['loan_id']; ?></td>
                <td><small><?php echo strtoupper(str_replace('_', ' ', $row['payment_method'])); ?></small></td>
                <td class="text-right cr-amt">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                <td class="text-right">KES <?php echo number_format($row['remaining_balance'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="summary-footer">
        <div style="font-size: 11px; color: #777;">
            *** END OF SYSTEM RECORD ***<br>
            Validated against live database logs.
        </div>
        <div>
            <div style="font-family: 'Brush Script MT', cursive; font-size: 22px; margin-bottom: 5px; color: var(--dark-slate); text-align: center;">
                <?php echo htmlspecialchars($user['full_name']); ?>
            </div>
            <div class="signature-block">Officer Certification Signature</div>
        </div>
    </div>
</div>

</body>
</html>