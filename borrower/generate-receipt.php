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
    <style>
        :root { --primary-gold: #c59100; --dark-slate: #2c3e50; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #e0e0e0; padding: 40px; margin: 0; }
        
        .receipt-container { 
            background: #fff; 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 50px; 
            position: relative;
            border-top: 8px solid var(--primary-gold);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        /* Digital Seal Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 50px;
            font-weight: 900;
            color: rgba(197, 145, 0, 0.04);
            white-space: nowrap;
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 5px;
            border: 10px solid rgba(197, 145, 0, 0.04);
            padding: 10px;
            border-radius: 15px;
        }

        .header { text-align: center; margin-bottom: 40px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .logo-area h1 { margin: 0; color: var(--dark-slate); font-size: 22px; letter-spacing: 1px; }
        .logo-area span { color: var(--primary-gold); font-weight: bold; }
        .receipt-label { text-transform: uppercase; letter-spacing: 3px; font-size: 12px; color: #888; margin-top: 10px; display: block; }

        .info-section { margin-bottom: 30px; }
        .data-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; border-bottom: 1px dashed #f0f0f0; padding-bottom: 8px; }
        .data-label { color: #888; font-weight: 500; }
        .data-value { font-weight: 600; color: var(--dark-slate); }

        .payment-box { 
            background: var(--dark-slate); 
            color: white; 
            padding: 25px; 
            border-radius: 8px; 
            text-align: center; 
            margin: 30px 0;
            position: relative;
            z-index: 1;
        }
        .payment-box small { text-transform: uppercase; font-size: 10px; letter-spacing: 2px; opacity: 0.7; }
        .payment-box h2 { margin: 10px 0 0 0; font-size: 32px; color: var(--primary-gold); }

        .summary-footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; }
        .signature-block { border-top: 1px solid #333; width: 180px; text-align: center; padding-top: 10px; font-size: 11px; color: #666; }
        
        .btn-box { text-align: center; margin-bottom: 20px; }
        .btn { padding: 12px 30px; border-radius: 5px; cursor: pointer; border: none; font-weight: bold; transition: 0.3s; text-decoration: none; font-size: 14px; }
        .btn-p { background: var(--primary-gold); color: white; }
        .btn-s { background: #333; color: white; margin-left: 10px; }

        @media print {
            .btn-box { display: none; }
            body { background: #fff; padding: 0; }
            .receipt-container { box-shadow: none; border: none; max-width: 100%; padding: 30px; }
        }
    </style>
</head>
<body>

<div class="btn-box">
    <button class="btn btn-p" onclick="window.print()">🖨️ Print Official Receipt</button>
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