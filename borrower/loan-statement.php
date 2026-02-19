<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
$loan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. Fetch Loan Details
$stmt = $conn->prepare("SELECT * FROM loans WHERE id = ? AND borrower_id = ?");
$stmt->bind_param("ii", $loan_id, $user['id']);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    die("Loan record not found.");
}

// 2. Fetch All Repayments for this loan
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
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/loan-app.css">
    <style>
        .statement-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #111;
            border-radius: 8px;
            overflow: hidden;
        }
        .statement-table th, .statement-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #333;
            color: #ddd;
        }
        .statement-table th {
            background: #1a1a1a;
            color: #f0a500;
            font-size: 13px;
            text-transform: uppercase;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-completed { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .status-approved { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        
        @media print {
            .sidebar, .dashboard-header, .btn-group { display: none; }
            .dashboard-main { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2>📜 Loan Statement</h2>
    <p>Detailed transaction history for Loan #<?php echo $loan_id; ?></p>
</div>

<div class="form-container" style="max-width: 1000px;">
    <div class="form-grid">
        <div class="info-box">
            <h4>Summary</h4>
            <p>Principal: <b>KES <?php echo number_format($loan['amount'], 2); ?></b></p>
            <p>Total Payable: <b>KES <?php echo number_format($loan['total_amount'], 2); ?></b></p>
            <p>Interest Rate: <b><?php echo $loan['interest_rate']; ?>%</b></p>
        </div>
        <div class="info-box" style="border-left-color: #f0a500;">
            <h4>Current Standing</h4>
            <p>Status: <span class="status-badge status-<?php echo $loan['status']; ?>"><?php echo $loan['status']; ?></span></p>
            <p>Balance: <b style="color: #f0a500;">KES <?php echo number_format($loan['remaining_balance'], 2); ?></b></p>
            <p>Term: <b><?php echo $loan['duration_months']; ?> Months</b></p>
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
                <td style="color: #22c55e;">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                <td>KES <?php echo number_format($running_balance, 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="btn-group" style="margin-top: 30px;">
        <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Statement</button>
        <a href="my-loans.php" class="btn btn-primary">Back to My Loans</a>
    </div>
</div>
</main>
</body>
</html>