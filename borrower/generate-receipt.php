<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
$repayment_id = isset($_GET['repayment_id']) ? intval($_GET['repayment_id']) : 0;

// Fetch repayment details with loan info
$query = "SELECT r.*, l.amount as principal, l.purpose, u.full_name, u.email 
          FROM repayments r 
          JOIN loans l ON r.loan_id = l.id 
          JOIN users u ON l.borrower_id = u.id 
          WHERE r.id = ? AND l.borrower_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $repayment_id, $user['id']);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Receipt not found or access denied.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official_Receipt_#<?php echo $repayment_id; ?></title>
    <link rel="stylesheet" href="../assets/css/generate-receipt.css">
  
</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" onclick="window.print()">Print Official Receipt</button>
    <a href="my-loans.php" class="btn btn-s">Close</a>
</div>

<div class="receipt-container">
    <div class="watermark">PAYMENT RECEIVED</div>
    
    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> SYSTEM</h1>
            <span class="receipt-label">Official Payment Receipt</span>
        </div>
    </div>

    <div class="info-section">
        <div class="data-row">
            <span class="data-label">Receipt Number</span>
            <span class="data-value">#REC-<?php echo str_pad($data['id'], 6, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="data-row">
            <span class="data-label">Transaction Date</span>
            <span class="data-value"><?php echo date('d M Y, h:i A', strtotime($data['created_at'])); ?></span>
        </div>
        <div class="data-row">
            <span class="data-label">Loan Reference</span>
            <span class="data-value">#LN-<?php echo str_pad($data['loan_id'], 5, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="data-row">
            <span class="data-label">Received From</span>
            <span class="data-value"><?php echo htmlspecialchars($data['full_name']); ?></span>
        </div>
    </div>

    <div class="payment-box">
        <small>Total Amount Paid</small>
        <h2>KES <?php echo number_format($data['amount_paid'], 2); ?></h2>
    </div>

    <div class="info-section">
        <div class="data-row">
            <span class="data-label">Payment Purpose</span>
            <span class="data-value"><?php echo htmlspecialchars($data['purpose']); ?></span>
        </div>
        <div class="data-row">
            <span class="data-label">Method</span>
            <span class="data-value">Electronic Transfer / Cash</span>
        </div>
    </div>

    <div class="summary-footer">
        <div style="font-size: 10px; color: #999; max-width: 200px;">
            This receipt confirms that the payment specified above has been successfully processed and credited to your loan account.
        </div>
        <div>
            <div style="font-family: 'Brush Script MT', cursive; font-size: 20px; margin-bottom: 5px; color: var(--dark-slate); text-align: center;">Verified</div>
            <div class="signature-block">Finance Department</div>
        </div>
    </div>

    <div style="text-align: center; margin-top: 40px; font-size: 10px; color: #aaa; border-top: 1px solid #eee; padding-top: 15px;">
        MicroLoan System | Professional Financial Services | &copy; <?php echo date('Y'); ?>
    </div>
</div>

</body>
</html>