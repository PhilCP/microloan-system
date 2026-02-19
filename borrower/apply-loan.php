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
    
    // Validate inputs
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
            
            // Clear form
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
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 14px;
}

.alert-success {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.info-box {
    background: rgba(59, 130, 246, 0.1);
    border-left: 4px solid #3b82f6;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    color: #ddd;
}

.info-box h4 {
    color: #3b82f6;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.form-group {
    margin-bottom: 24px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

label {
    display: block;
    color: #ddd;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
}

.required {
    color: #ef4444;
}

input[type="number"],
select,
textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #333;
    border-radius: 8px;
    font-size: 15px;
    background: #111;
    color: #fff;
    font-family: inherit;
}

input:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: #f0a500;
}

textarea {
    resize: vertical;
    min-height: 100px;
}

.help-text {
    font-size: 12px;
    color: #888;
    margin-top: 4px;
}

.calculation-box {
    background: rgba(240, 165, 0, 0.05);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    border: 1px solid rgba(240, 165, 0, 0.2);
}

.calculation-box h4 {
    margin-bottom: 12px;
    color: #f0a500;
}

.calc-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
    color: #ddd;
}

.calc-row.total {
    border-top: 2px solid #333;
    margin-top: 10px;
    padding-top: 12px;
    font-weight: 700;
    font-size: 18px;
    color: #f0a500;
}

.calc-value {
    font-weight: 600;
}

.btn-group {
    display: flex;
    gap: 12px;
}

.btn {
    padding: 14px 28px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: #f0a500;
    color: #000;
    flex: 1;
}

.btn-primary:hover {
    background: #ffc107;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #333;
    color: #fff;
}

.btn-secondary:hover {
    background: #444;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .btn-group {
        flex-direction: column;
    }
}
</style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2>📝 Apply for a Loan</h2>
    <p style="color: #999;">Fill in the details below to submit your loan application</p>
</div>

<div class="form-container">
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
            <br><br>
            <a href="my-loans.php" style="color: #22c55e; font-weight: 600;">View My Loans →</a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <h4>📋 Loan Requirements</h4>
        <p style="margin: 0; line-height: 1.8;">
            • Minimum amount: KES 500<br>
            • Maximum amount: KES 10,000<br>
            • Loan period: 1-24 months<br>
            • Interest rate: <?php echo $interest_rate; ?>% flat rate<br>
            • Processing time: 1-3 business days
        </p>
    </div>

    <form method="POST" id="loanForm">
        <div class="form-grid">
            <div class="form-group">
                <label for="amount">
                    Loan Amount (KES) <span class="required">*</span>
                </label>
                <input 
                    type="number" 
                    id="amount" 
                    name="amount" 
                    min="1000" 
                    max="100000" 
                    step="100"
                    value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : '10000'; ?>"
                    required
                    oninput="calculateLoan()"
                >
                <div class="help-text">Enter amount between 1,000 and 100,000</div>
            </div>

            <div class="form-group">
                <label for="duration_months">
                    Repayment Period <span class="required">*</span>
                </label>
                <select 
                    id="duration_months" 
                    name="duration_months" 
                    required
                    onchange="calculateLoan()"
                >
                    <option value="">Select duration</option>
                    <option value="1">1 Month</option>
                    <option value="2">2 Months</option>
                    <option value="3" selected>3 Months</option>
                    <option value="6">6 Months</option>
                    <option value="9">9 Months</option>
                    <option value="12">12 Months</option>
                    <option value="18">18 Months</option>
                    <option value="24">24 Months</option>
                </select>
                <div class="help-text">Choose your repayment period</div>
            </div>
        </div>

        <div class="calculation-box" id="calculationBox">
            <h4>Loan Calculation</h4>
            <div class="calc-row">
                <span>Principal Amount:</span>
                <span class="calc-value" id="principalAmount">KES 10,000</span>
            </div>
            <div class="calc-row">
                <span>Interest (<?php echo $interest_rate; ?>%):</span>
                <span class="calc-value" id="interestAmount">KES 1,500</span>
            </div>
            <div class="calc-row">
                <span>Duration:</span>
                <span class="calc-value" id="durationDisplay">3 Months</span>
            </div>
            <div class="calc-row total">
                <span>Total Repayment:</span>
                <span id="totalAmount">KES 11,500</span>
            </div>
            <div class="calc-row">
                <span>Monthly Installment:</span>
                <span class="calc-value" id="monthlyPayment">KES 3,833</span>
            </div>
        </div>

        <div class="form-group full-width">
            <label for="purpose">
                Loan Purpose <span class="required">*</span>
            </label>
            <textarea 
                id="purpose" 
                name="purpose" 
                placeholder="Please describe how you intend to use this loan..."
                required
            ><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
            <div class="help-text">Provide details about your loan purpose (e.g., business expansion, emergency, education)</div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Submit Application</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click',()=>{
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('dashboardMain').classList.toggle('shifted');
});

const interestRate = <?php echo $interest_rate; ?>;

function calculateLoan() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const duration = parseInt(document.getElementById('duration_months').value) || 3;
    
    // Calculate interest (simple interest: Principal × Rate × Time / 100)
    const interest = (amount * interestRate * duration) / 100;
    const total = amount + interest;
    const monthly = total / duration;
    
    // Update display
    document.getElementById('principalAmount').textContent = 'KES ' + amount.toLocaleString();
    document.getElementById('interestAmount').textContent = 'KES ' + interest.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('durationDisplay').textContent = duration + ' Month' + (duration > 1 ? 's' : '');
    document.getElementById('totalAmount').textContent = 'KES ' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('monthlyPayment').textContent = 'KES ' + monthly.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Calculate on page load
window.onload = function() {
    calculateLoan();
};
</script>

</main>
</body>
</html>