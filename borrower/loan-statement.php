<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
$loan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Loan Details
$stmt = $conn->prepare("SELECT * FROM loans WHERE id = ? AND borrower_id = ?");
$stmt->bind_param("ii", $loan_id, $user['id']);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    die("Loan record not found.");
}

// Fetch All Repayments for this loan
$r_stmt = $conn->prepare("SELECT * FROM repayments WHERE loan_id = ? ORDER BY payment_date ASC, id ASC");
$r_stmt->bind_param("i", $loan_id);
$r_stmt->execute();
$repayments = $r_stmt->get_result();

$pageTitle = "Loan Statement #" . $loan_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="/microloan-system/assets/css/statement.css">
    
    <style>
        html { scroll-behavior: smooth; }
    </style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="welcome">
        <h2>📜 Loan Statement</h2>
        <p>Detailed transaction history for <strong>Loan #<?php echo $loan_id; ?></strong></p>
    </div>

    <div class="form-container" style="max-width: 1000px; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        
        <div class="form-grid">
            <div class="info-box">
                <h4>Loan Summary</h4>
                <p>Principal: <span>KES <?php echo number_format($loan['amount'], 2); ?></span></p>
                <p>Interest Rate: <span><?php echo $loan['interest_rate']; ?>%</span></p>
                <p>Total Payable: <strong>KES <?php echo number_format($loan['total_amount'], 2); ?></strong></p>
            </div>
            
            <div class="info-box" style="border-left-color: #f0a500;">
                <h4>Account Status</h4>
                <p>Status: <span class="status-badge status-<?php echo $loan['status']; ?>"><?php echo $loan['status']; ?></span></p>
                <p>Term: <span><?php echo $loan['duration_months']; ?> Months</span></p>
                <p>Balance: <strong style="color: #f0a500;">KES <?php echo number_format($loan['remaining_balance'], 2); ?></strong></p>
            </div>
        </div>

        <table class="statement-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Debit (Loan)</th>
                    <th>Credit (Paid)</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo date('d M Y', strtotime($loan['created_at'])); ?></td>
                    <td>Loan Disbursed (inc. interest)</td>
                    <td>KES <?php echo number_format($loan['total_amount'], 2); ?></td>
                    <td>-</td>
                    <td>KES <?php echo number_format($loan['total_amount'], 2); ?></td>
                </tr>

                <?php 
                $running_balance = $loan['total_amount'];
                while($row = $repayments->fetch_assoc()): 
                    $running_balance -= $row['amount_paid'];
                ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                    <td>Repayment Made</td>
                    <td>-</td>
                    <td style="color: #2e7d32; font-weight: 700;">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                    <td>KES <?php echo number_format($running_balance, 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-outline" style="border-color: #ddd;">🖨️ Print Statement</button>
            <a href="my-loans.php" class="btn btn-gold">Back to My Loans</a>
        </div>
    </div>
</main>

</body>
</html>