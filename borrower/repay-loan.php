<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();

if (!isset($_GET['id'])) {
    die("Invalid Loan ID");
}

$loan_id = intval($_GET['id']);

// Fetch Loan with validation to ensure it belongs to the logged-in borrower
$stmt = $conn->prepare("SELECT * FROM loans WHERE id = ? AND borrower_id = ?");
$stmt->bind_param("ii", $loan_id, $user['id']);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) { die("Loan not found or access denied."); }

$success = '';
$error = '';

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment = isset($_POST['pay_full']) ? $loan['remaining_balance'] : floatval($_POST['payment']);

    if ($payment <= 0) {
        $error = "Enter a valid payment amount.";
    } elseif ($payment > $loan['remaining_balance'] + 0.01) { // Added small buffer for float precision
        $error = "Payment exceeds remaining balance.";
    } else {
        $new_balance = max(0, $loan['remaining_balance'] - $payment);
        $status = ($new_balance <= 0) ? 'completed' : 'approved';

        // Start Transaction for data integrity
        $conn->begin_transaction();
        try {
            // Update Loan Balance
            $update = $conn->prepare("UPDATE loans SET remaining_balance=?, status=? WHERE id=?");
            $update->bind_param("dsi", $new_balance, $status, $loan_id);
            $update->execute();

            // Insert Repayment Record
            $insert = $conn->prepare("INSERT INTO repayments (loan_id, amount_paid, payment_date, recorded_by) VALUES (?, ?, CURDATE(), ?)");
            $insert->bind_param("idi", $loan_id, $payment, $user['id']);
            $insert->execute();

            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $log_action = "Made payment of KES " . number_format($payment, 2) . " for Loan #$loan_id";
            $log_stmt->bind_param("is", $user['id'], $log_action);
            $log_stmt->execute();

            $conn->commit();
            
            $loan['remaining_balance'] = $new_balance;
            $loan['status'] = $status;
            $success = "Payment of KES " . number_format($payment, 2) . " processed successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Transaction failed. Please try again.";
        }
    }
}

/* logic to Get Repayment History */
$history = $conn->prepare("SELECT * FROM repayments WHERE loan_id=? ORDER BY created_at DESC");
$history->bind_param("i", $loan_id);
$history->execute();
$repayments = $history->get_result();

$pageTitle = "Repay Loan #" . $loan_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/repay-loan.css">
  
</head>
<body>

<div class="form-container">
    <div style="margin-bottom: 30px;">
        <h2 style="margin:0;"> Repayment Terminal</h2>
        <p style="color: #666;">Loan Reference: #LN-<?php echo str_pad($loan['id'], 5, '0', STR_PAD_LEFT); ?></p>
    </div>
    
    <?php if($success): ?>
        <div class="alert alert-success">✓ <?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-error"> <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="info-box">
        <div style="font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 1px;">Outstanding Balance</div>
        <div class="balance-display">
            KES <?php echo number_format($loan['remaining_balance'], 2); ?>
        </div>
        <div style="font-size: 13px; color: #444;">Original Liability: KES <?php echo number_format($loan['total_amount'], 2); ?></div>
    </div>

    <?php if($loan['remaining_balance'] > 0): ?>
    <div class="payment-input-wrapper">
        <form method="POST">
            <label style="color: #888; display: block; margin-bottom: 10px; font-size: 12px;">SPECIFY PAYMENT AMOUNT</label>
            <input type="number" name="payment" step="0.01" min="1" max="<?php echo $loan['remaining_balance']; ?>" placeholder="0.00" required>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Process Payment</button>
                <button type="submit" name="pay_full" class="btn btn-secondary" onclick="return confirm('Clear total remaining balance?')">Pay Full Balance</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <div class="alert alert-success" style="text-align:center; margin-top:20px;">
            🎊 This loan is fully cleared. 
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 20px;">
        <a href="my-loans.php" style="color: #444; text-decoration: none; font-size: 13px; font-weight: 700;">← RETURN TO PORTFOLIO</a>
    </div>

    <div style="margin-top: 50px;">
        <h3 style="color: #f0a500; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px;"> Transaction Ledger</h3>
        <div style="background: #0a0a0a; border-radius: 12px; border: 1px solid #1a1a1a; overflow: hidden;">
            <table class="statement-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount Paid</th>
                        <th style="text-align: right; padding-right: 20px;">Verification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $repayments->fetch_assoc()): ?>
                    <tr>
                        <td style="color: #666;"><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                        <td style="font-weight: 800; color: #fff;">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                        <td style="text-align: right; padding-right: 20px;">
                            <a href="generate-receipt.php?repayment_id=<?php echo $row['id']; ?>" target="_blank" class="btn-download">
                                RECEIPT
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($repayments->num_rows == 0): ?>
                        <tr><td colspan="3" style="text-align:center; padding: 40px; color: #333;">No transactions identified for this account.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>