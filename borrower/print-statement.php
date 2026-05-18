<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
$loan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Loan Details
$stmt = $conn->prepare("SELECT l.*, u.full_name, u.email, u.phone FROM loans l JOIN users u ON l.borrower_id = u.id WHERE l.id = ? AND l.borrower_id = ?");
$stmt->bind_param("ii", $loan_id, $user['id']);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) { die("Statement record not found."); }

// Fetch Repayments
$r_stmt = $conn->prepare("SELECT * FROM repayments WHERE loan_id = ? ORDER BY payment_date ASC, id ASC");
$r_stmt->bind_param("i", $loan_id);
$r_stmt->execute();
$repayments = $r_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official_Statement_#<?php echo $loan_id; ?></title>
    <link rel="stylesheet" href="../assets/css/print-statement.css">

</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" onclick="window.print()"> Download Official PDF</button>
    <a href="my-loans.php" class="btn btn-s">Return to Dashboard</a>
</div>

<div class="statement-container">
    <div class="watermark">OFFICIAL DOCUMENT</div>
    
    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <p style="font-size: 12px; color: #666; margin: 5px 0;">Certified Lending Institution</p>
        </div>
        <div class="meta-data">
            <strong>Statement ID:</strong> ST-<?php echo time(); ?><br>
            <strong>Date Generated:</strong> <?php echo date('d M Y'); ?><br>
            <strong>Status:</strong> <span style="color:var(--primary-gold)"><?php echo strtoupper($loan['status']); ?></span>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="section-title">Borrower Details</div>
            <div class="data-row"><span class="data-label">Full Name</span><span class="data-value"><?php echo htmlspecialchars($loan['full_name']); ?></span></div>
            <div class="data-row"><span class="data-label">Email</span><span class="data-value"><?php echo htmlspecialchars($loan['email']); ?></span></div>
            <div class="data-row"><span class="data-label">Reference</span><span class="data-value">USR-<?php echo str_pad($loan['borrower_id'], 4, '0', STR_PAD_LEFT); ?></span></div>
        </div>
        <div class="info-box">
            <div class="section-title">Loan Summary</div>
            <div class="data-row"><span class="data-label">Approved Amount</span><span class="data-value">KES <?php echo number_format($loan['amount'], 2); ?></span></div>
            <div class="data-row"><span class="data-label">Total Payable</span><span class="data-value">KES <?php echo number_format($loan['total_amount'], 2); ?></span></div>
            <div class="data-row"><span class="data-label">Remaining Balance</span><span class="data-value" style="color:var(--primary-gold)">KES <?php echo number_format($loan['remaining_balance'], 2); ?></span></div>
        </div>
    </div>

    <table class="statement-table">
        <thead>
            <tr>
                <th>Transaction Date</th>
                <th>Description</th>
                <th class="text-right">Debit (KES)</th>
                <th class="text-right">Credit (KES)</th>
                <th class="text-right">Balance (KES)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="row-disbursement">
                <td><?php echo date('d M Y', strtotime($loan['created_at'])); ?></td>
                <td>LOAN DISBURSEMENT - #LN-<?php echo $loan['id']; ?></td>
                <td class="text-right dr-amt"><?php echo number_format($loan['total_amount'], 2); ?></td>
                <td class="text-right">-</td>
                <td class="text-right"><?php echo number_format($loan['total_amount'], 2); ?></td>
            </tr>

            <?php 
            $running_balance = $loan['total_amount'];
            while($row = $repayments->fetch_assoc()): 
                $running_balance -= $row['amount_paid'];
            ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                <td>REPAYMENT RECEIVED - REC#<?php echo $row['id']; ?></td>
                <td class="text-right">-</td>
                <td class="text-right cr-amt"><?php echo number_format($row['amount_paid'], 2); ?></td>
                <td class="text-right"><?php echo number_format($running_balance, 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="summary-footer">
        <div>
            <div style="font-family: 'Brush Script MT', cursive; font-size: 24px; margin-bottom: 5px; color: var(--dark-slate);">System Generated</div>
            <div class="signature-block">Authorized Digital Signature</div>
        </div>
    </div>

    <div style="text-align: center; margin-top: 50px; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 20px;">
        This document is an electronically generated statement and is valid without a physical signature. <br>
        MicroLoan System &copy; <?php echo date('Y'); ?> | Professional Financial Solutions
    </div>
</div>

</body>
</html>