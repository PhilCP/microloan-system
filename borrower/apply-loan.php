<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
if(!$user){
    die("User not found. Check session.");
}

$success = '';
$error = '';
$interest_rate = 5.00; // Default interest rate

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    $duration = intval($_POST['duration_months']);
    $purpose = trim($_POST['purpose']);
    
    // Validate inputs against the requirements
    if ($amount < 500) {
        $error = "Minimum loan amount is KES 500";
    } elseif ($amount > 10000) {
        $error = "Maximum loan amount is KES 10,000";
    } elseif ($duration < 1 || $duration > 24) {
        $error = "Loan duration must be between 1 and 24 months";
    } elseif (empty($purpose)) {
        $error = "Please provide loan purpose";
    } else {
        // Calculate total amount with interest
        $interest_amount = ($amount * $interest_rate * $duration) / 100;
        $total_amount = $amount + $interest_amount;
        $remaining_balance = $total_amount;
        
        // Insert loan application
        $stmt = $conn->prepare("INSERT INTO loans (borrower_id, amount, interest_rate, total_amount, remaining_balance, duration_months, purpose, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iddddis", $user['id'], $amount, $interest_rate, $total_amount, $remaining_balance, $duration, $purpose);
        
        if ($stmt->execute()) {
            $loan_id = $stmt->insert_id;
            
            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $log_action = "Applied for loan of KES " . number_format($amount, 2);
            $log_stmt->bind_param("is", $user['id'], $log_action);
            $log_stmt->execute();
            
            $success = "Your loan application has been submitted successfully! Application ID: #" . $loan_id;
            
            // Clear post to reset form
            $_POST = array();
        } else {
            $error = "Error submitting application. Please try again.";
        }
        $stmt->close();
    }
}

$pageTitle = "Apply for Loan";
$role = "borrower";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<style>
/* Tactical Form Styling */
.form-container { max-width: 800px; margin: 0 auto; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; font-weight: 600; }
.alert-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
.alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

.info-box {
    background: rgba(59, 130, 246, 0.05);
    border-left: 4px solid #3b82f6;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    color: #ddd;
}
.info-box h4 { color: #3b82f6; margin-top: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.form-group { margin-bottom: 24px; }
.form-group.full-width { grid-column: 1 / -1; }

label { display: block; color: #f0a500; font-weight: 700; margin-bottom: 10px; font-size: 13px; text-transform: uppercase; }
.required { color: #ef4444; }

input, select, textarea {
    width: 100%;
    padding: 14px;
    border: 1px solid #222;
    border-radius: 8px;
    background: #0a0a0a;
    color: #fff;
    font-size: 15px;
    transition: 0.3s;
}
input:focus, select:focus, textarea:focus { border-color: #f0a500; outline: none; box-shadow: 0 0 10px rgba(240, 165, 0, 0.1); }

.calculation-box {
    background: #111;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #222;
    margin-bottom: 30px;
}
.calc-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #1a1a1a; font-size: 14px; }
.calc-row.total { border-top: 2px solid #f0a500; border-bottom: none; margin-top: 10px; color: #f0a500; font-size: 20px; font-weight: 800; }

.btn-group { display: flex; gap: 15px; }
.btn { padding: 16px 30px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.3s; text-align: center; border: none; flex: 1; text-transform: uppercase; letter-spacing: 1px; }
.btn-primary { background: #f0a500; color: #000; }
.btn-primary:hover { background: #ffc107; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(240, 165, 0, 0.2); }
.btn-secondary { background: #1a1a1a; color: #fff; text-decoration: none; }

@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .btn-group { flex-direction: column; } }
</style>
</head>
<body style="background: #000; color: #fff;">

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s; padding: 30px;">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="welcome" style="margin-bottom: 30px;">
        <h2 style="font-weight: 800; margin: 0;">📝 Capital Request</h2>
        <p style="color: #666; margin-top: 5px;">Submit your loan details for processing.</p>
    </div>

    <div class="form-container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 10px;"><a href="my-loans.php" style="color: #fff; text-decoration: underline;">Track Applications →</a></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="info-box">
            <h4>📋 Tactical Guidelines</h4>
            <p style="margin: 0; line-height: 1.8; font-size: 13px;">
                • Limits: <strong>KES 500 — KES 10,000</strong><br>
                • Interest: <strong><?php echo number_format($interest_rate, 2); ?>% Flat Rate</strong> per month<br>
                • Repayment: Monthly installments via dashboard
            </p>
        </div>

        <form method="POST" id="loanForm">
            <div class="form-grid">
                <div class="form-group">
                    <label for="amount">Requested Amount (KES) <span class="required">*</span></label>
                    <input type="number" id="amount" name="amount" min="500" max="10000" step="100" 
                           value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : '5000'; ?>" 
                           required oninput="calculateLoan()">
                    <div style="color:#444; font-size:11px; margin-top:5px;">Min: 500 | Max: 10,000</div>
                </div>

                <div class="form-group">
                    <label for="duration_months">Repayment Horizon <span class="required">*</span></label>
                    <select id="duration_months" name="duration_months" required onchange="calculateLoan()">
                        <?php 
                        $durations = [1, 2, 3, 6, 9, 12, 18, 24];
                        foreach($durations as $d){
                            $sel = (isset($_POST['duration_months']) && $_POST['duration_months'] == $d) || (!isset($_POST['duration_months']) && $d == 3) ? 'selected' : '';
                            echo "<option value='$d' $sel>$d Month".($d > 1 ? 's' : '')."</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="calculation-box">
                <h4 style="color:#666; font-size:12px; text-transform:uppercase; margin-top:0;">Financial Summary</h4>
                <div class="calc-row"><span>Principal</span><span id="principalAmount">KES 0</span></div>
                <div class="calc-row"><span>Flat Interest (<?php echo $interest_rate; ?>%)</span><span id="interestAmount">KES 0</span></div>
                <div class="calc-row"><span>Term Duration</span><span id="durationDisplay">3 Months</span></div>
                <div class="calc-row"><span>Monthly Installment</span><span id="monthlyPayment" style="font-weight:700;">KES 0</span></div>
                <div class="calc-row total"><span>Total Payable</span><span id="totalAmount">KES 0</span></div>
            </div>

            <div class="form-group full-width">
                <label for="purpose">Operational Purpose <span class="required">*</span></label>
                <textarea id="purpose" name="purpose" placeholder="Define the utility of these funds..." required><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Submit Application</button>
                <a href="dashboard.php" class="btn btn-secondary">Discard</a>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('sidebarToggle').addEventListener('click',()=>{
        document.getElementById('sidebar').classList.toggle('active');
    });

    const interestRate = <?php echo $interest_rate; ?>;

    function calculateLoan() {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const duration = parseInt(document.getElementById('duration_months').value) || 0;
        
        // Simple Interest Calculation based on your PHP logic: (P * R * T) / 100
        const interest = (amount * interestRate * duration) / 100;
        const total = amount + interest;
        const monthly = duration > 0 ? (total / duration) : 0;
        
        document.getElementById('principalAmount').textContent = 'KES ' + amount.toLocaleString();
        document.getElementById('interestAmount').textContent = 'KES ' + interest.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('durationDisplay').textContent = duration + ' Month' + (duration !== 1 ? 's' : '');
        document.getElementById('totalAmount').textContent = 'KES ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('monthlyPayment').textContent = 'KES ' + monthly.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    // Initialize calculation on load
    window.onload = calculateLoan;
    </script>
</main>
</body>
</html>