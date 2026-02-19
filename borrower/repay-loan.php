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

// Fetch Loan with validation
$stmt = $conn->prepare("SELECT * FROM loans WHERE id = ? AND borrower_id = ?");
$stmt->bind_param("ii", $loan_id, $user['id']);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) { die("Loan not found."); }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment = isset($_POST['pay_full']) ? $loan['remaining_balance'] : floatval($_POST['payment']);

    if ($payment <= 0) {
        $error = "Enter valid payment amount.";
    } elseif ($payment > $loan['remaining_balance']) {
        $error = "Payment exceeds remaining balance.";
    } else {
        $new_balance = $loan['remaining_balance'] - $payment;
        $status = ($new_balance <= 0) ? 'completed' : 'approved';

        // Update Loan Balance
        $update = $conn->prepare("UPDATE loans SET remaining_balance=?, status=? WHERE id=?");
        $update->bind_param("dsi", $new_balance, $status, $loan_id);
        $update->execute();

        // Insert Repayment - Corrected to include recorded_by and CURDATE()
        $insert = $conn->prepare("INSERT INTO repayments (loan_id, amount_paid, payment_date, recorded_by) VALUES (?, ?, CURDATE(), ?)");
        $insert->bind_param("idi", $loan_id, $payment, $user['id']);
        $insert->execute();

        $loan['remaining_balance'] = $new_balance;
        $loan['status'] = $status;
        $success = "Payment successful!";
    }
}

/* Get Repayment History */
$history = $conn->prepare("SELECT * FROM repayments WHERE loan_id=? ORDER BY created_at DESC");
$history->bind_param("i", $loan_id);
$history->execute();
$repayments = $history->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Repay Loan #<?php echo $loan_id; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/loan-app.css">
    <style>
        body { background: #0b0b0b; color: #ddd; padding: 40px 20px; }
        .btn-download {
            padding: 5px 12px;
            background: rgba(240, 165, 0, 0.1);
            color: #f0a500;
            border: 1px solid #f0a500;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            transition: 0.3s;
        }
        .btn-download:hover { background: #f0a500; color: #000; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>💳 Repay Loan #<?php echo $loan['id']; ?></h2>
    
    <?php if($success): ?>
        <div class="alert alert-success">✓ <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="info-box">
        <h4>Loan Standing</h4>
        <div class="balance-display" style="font-size: 28px; margin: 10px 0;">
            KES <?php echo number_format($loan['remaining_balance'],2); ?>
        </div>
        <p>Total Payable: KES <?php echo number_format($loan['total_amount'],2); ?></p>
    </div>

    <?php if($loan['remaining_balance'] > 0): ?>
    <form method="POST" style="margin-top: 20px;">
        <div class="form-group">
            <label>Amount to Pay (KES)</label>
            <input type="number" name="payment" step="0.01" placeholder="Enter amount..." required>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Submit Payment</button>
            <button type="submit" name="pay_full" class="btn btn-secondary">Pay Full</button>
            <a href="my-loans.php" class="btn btn-secondary">Back</a>
        </div>
    </form>
    <?php endif; ?>

    <h3 style="margin-top:50px; color: #f0a500;">📊 Repayment Transactions</h3>
    <table class="statement-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Amount Paid</th>
                <th style="text-align: right;">Receipt</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $repayments->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                <td style="font-weight: bold; color: #22c55e;">KES <?php echo number_format($row['amount_paid'],2); ?></td>
                <td style="text-align: right;">
                    <a href="generate-receipt.php?repayment_id=<?php echo $row['id']; ?>" target="_blank" class="btn-download">
                        📄 Download
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if($repayments->num_rows == 0): ?>
                <tr><td colspan="3" style="text-align:center; padding: 30px; color: #666;">No payments recorded yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>