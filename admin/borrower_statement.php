<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$borrower_id = $_GET['id'] ?? die("Target identity required.");

// 1. Fetch Borrower Profile
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param("i", $borrower_id);
$uStmt->execute();
$bUser = $uStmt->get_result()->fetch_assoc();

// 2. Fetch Aggregated Story
$summary = $conn->query("SELECT 
    COUNT(id) as loan_count, 
    SUM(total_amount) as total_borrowed, 
    SUM(remaining_balance) as total_debt 
    FROM loans WHERE borrower_id = $borrower_id")->fetch_assoc();

$totalPaid = $conn->query("SELECT SUM(amount_paid) as paid FROM repayments r JOIN loans l ON r.loan_id = l.id WHERE l.borrower_id = $borrower_id")->fetch_assoc()['paid'] ?? 0;

// 3. Fetch Full Transaction Timeline
$timeline = $conn->query("SELECT r.*, l.id as ln_id FROM repayments r JOIN loans l ON r.loan_id = l.id WHERE l.borrower_id = $borrower_id ORDER BY r.payment_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Story_<?php echo $bUser['full_name']; ?></title>
    <style>
        body { font-family: 'Courier New', monospace; background: #fff; padding: 50px; color: #000; }
        .statement-box { border: 2px solid #000; padding: 40px; max-width: 800px; margin: 0 auto; position: relative; }
        .header-box { border-bottom: 2px solid #000; margin-bottom: 20px; padding-bottom: 20px; display: flex; justify-content: space-between; }
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; border: 1px solid #000; margin-bottom: 30px; }
        .summary-item { padding: 10px; border-right: 1px solid #000; text-align: center; }
        .summary-item:last-child { border-right: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; background: #f0f0f0; padding: 8px; border: 1px solid #000; font-size: 12px; }
        td { padding: 8px; border: 1px solid #000; font-size: 12px; }
        .watermark { position: absolute; top: 40%; left: 15%; font-size: 80px; color: rgba(0,0,0,0.05); transform: rotate(-30deg); z-index: -1; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom:20px;">
    <button onclick="window.print()" style="padding:10px 20px; cursor:pointer;">🖨️ PRINT BORROWER STORY</button>
</div>

<div class="statement-box">
    <div class="watermark">CONFIDENTIAL</div>
    <div class="header-box">
        <div>
            <h2 style="margin:0;">BORROWER FINANCIAL STORY</h2>
            <p>Subject: <?php echo strtoupper($bUser['full_name']); ?></p>
        </div>
        <div style="text-align:right; font-size:12px;">
            UID: <?php echo str_pad($bUser['id'], 6, '0', STR_PAD_LEFT); ?><br>
            Email: <?php echo $bUser['email']; ?>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-item">
            <div style="font-size:10px;">TOTAL LOANS</div>
            <strong><?php echo $summary['loan_count']; ?></strong>
        </div>
        <div class="summary-item">
            <div style="font-size:10px;">LIFETIME BORROWED</div>
            <strong>KES <?php echo number_format($summary['total_borrowed'], 2); ?></strong>
        </div>
        <div class="summary-item">
            <div style="font-size:10px;">TOTAL REPAID</div>
            <strong style="color:green;">KES <?php echo number_format($totalPaid, 2); ?></strong>
        </div>
    </div>

    <p style="font-size:11px; font-weight:bold;">TRANSACTION TIMELINE:</p>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Loan Ref</th>
                <th>Method</th>
                <th style="text-align:right;">Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $timeline->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('d-M-Y', strtotime($row['payment_date'])); ?></td>
                <td>#LN-<?php echo $row['ln_id']; ?></td>
                <td><?php echo strtoupper($row['payment_method']); ?></td>
                <td style="text-align:right;">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right; font-weight:bold;">CURRENT DEBT:</td>
                <td style="text-align:right; font-weight:bold; color:red;">KES <?php echo number_format($summary['total_debt'], 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:50px; font-size:10px; border-top:1px solid #eee; padding-top:10px;">
        *** This document serves as a verified financial history generated on <?php echo date('Y-m-d H:i'); ?> ***
    </div>
</div>

</body>
</html>