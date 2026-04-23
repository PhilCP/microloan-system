<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$borrower_id = $_GET['id'] ?? die("Target identity required.");
$admin_user = getCurrentUser();

// Fetch Borrower Profile
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param("i", $borrower_id);
$uStmt->execute();
$bUser = $uStmt->get_result()->fetch_assoc();

if (!$bUser) die("Personnel record not found.");

//Fetch Aggregated Story
$summary = $conn->query("SELECT 
    COUNT(id) as loan_count, 
    SUM(total_amount) as total_borrowed, 
    SUM(remaining_balance) as total_debt 
    FROM loans WHERE borrower_id = $borrower_id")->fetch_assoc();

$totalPaid = $conn->query("SELECT SUM(amount_paid) as paid FROM repayments r JOIN loans l ON r.loan_id = l.id WHERE l.borrower_id = $borrower_id")->fetch_assoc()['paid'] ?? 0;

//Fetch Full Transaction Timeline
$timeline = $conn->query("SELECT r.*, l.id as ln_id FROM repayments r JOIN loans l ON r.loan_id = l.id WHERE l.borrower_id = $borrower_id ORDER BY r.payment_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Story_<?php echo str_replace(' ', '_', $bUser['full_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/borrower_statement.css">
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 20px; padding-top: 20px;">
    <button onclick="window.print()" class="print-btn"> PRINT BORROWER STORY</button>
    <a href="users.php" style="margin-left: 10px; color: #666; font-family: sans-serif; text-decoration: none; font-size: 14px;">Back to Registry</a>
</div>

<div class="statement-container">
    <div class="watermark">CONFIDENTIAL</div>
    
    <div class="header">
        <div>
            <h1 style="margin:0; color: #2c3e50;">BORROWER <span>STORY</span></h1>
            <div style="color: #c59100; font-weight: bold; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Individual Financial History</div>
        </div>
        <div style="text-align: right; font-size: 12px;">
            <strong>Subject UID:</strong> <?php echo str_pad($bUser['id'], 6, '0', STR_PAD_LEFT); ?><br>
            <strong>Report Date:</strong> <?php echo date('d M Y, H:i'); ?><br>
            <strong>Status:</strong> Verified Account
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="label">LEGAL IDENTITY</div>
            <div class="value"><?php echo strtoupper($bUser['full_name']); ?></div>
            <div style="font-size: 11px; color: #777;"><?php echo $bUser['email']; ?></div>
        </div>
        <div class="info-box">
            <div class="label">LIFETIME BORROWED</div>
            <div class="value">KES <?php echo number_format($summary['total_borrowed'], 2); ?></div>
            <div style="font-size: 11px; color: #777;"><?php echo $summary['loan_count']; ?> Approved Facilities</div>
        </div>
        <div class="info-box">
            <div class="label">TOTAL REPAID</div>
            <div class="value" style="color: #27ae60;">KES <?php echo number_format($totalPaid, 2); ?></div>
            <div style="font-size: 11px; color: #777;">To Date</div>
        </div>
    </div>

    <h3 style="font-size: 13px; color: #2c3e50; border-left: 3px solid #c59100; padding-left: 10px; margin-bottom: 15px;">TRANSACTION TIMELINE</h3>
    
    <table class="statement-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Loan Reference</th>
                <th>Payment Method</th>
                <th class="text-right">Credit Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if($timeline->num_rows > 0): ?>
                <?php while($row = $timeline->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                    <td><strong>#LN-<?php echo str_pad($row['ln_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                    <td><small><?php echo strtoupper($row['payment_method']); ?></small></td>
                    <td class="text-right" style="color: #27ae60; font-weight: bold;">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center; padding: 30px; color: #999;">No repayment history found for this subject.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-right" style="font-weight: bold; border-top: 2px solid #2c3e50; padding-top: 15px;">CURRENT OUTSTANDING DEBT:</td>
                <td class="text-right" style="font-weight: bold; color: #c0392b; border-top: 2px solid #2c3e50; padding-top: 15px; font-size: 16px;">
                    KES <?php echo number_format($summary['total_debt'], 2); ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 60px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div style="font-size: 10px; color: #999;">
            OFFICIAL FINANCIAL STATEMENT<br>
            AUDITED BY: <?php echo strtoupper($admin_user['full_name']); ?><br>
            *** END OF SUBJECT RECORD ***
        </div>
        
  <div style="display: flex; flex-direction: column; align-items: center;">
        <div style="font-family: 'Brush Script MT', cursive; font-size: 28px; margin-bottom: -5px; color: #2c3e50; text-align: center; width: 100%;">
            <?php echo htmlspecialchars($admin_user['full_name']); ?>
        </div>
        
        <div style="text-align: center; border-top: 1.5px solid #000; width: 240px; padding-top: 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: bold; font-family: sans-serif;">
            Authorized Admin Signature
        </div>
    </div>
    </div>
</div>

</body>
</html>