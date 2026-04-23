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
            // Find officer with least pending loans (Round Robin)
            $officerQuery = "SELECT u.id, u.full_name, COUNT(l.id) as loan_count 
                            FROM users u 
                            LEFT JOIN loans l ON u.id = l.approved_by AND l.status='pending'
                            WHERE u.role='officer'
                            GROUP BY u.id, u.full_name
                            ORDER BY loan_count ASC, u.id ASC
                            LIMIT 1";
            
            $officerResult = $conn->query($officerQuery);
            
            if ($officerResult && $officerResult->num_rows > 0) {
                $officer = $officerResult->fetch_assoc();
                
                // Assign loan to officer with least pending loans
                $assignStmt = $conn->prepare("UPDATE loans SET approved_by = ? WHERE id = ?");
                $assignStmt->bind_param("ii", $officer['id'], $loan_id);
                
                if ($assignStmt->execute()) {
                    // Log auto-assignment for tracking
                    $assignLog = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                    $assignAction = "System auto-assigned loan #{$loan_id} to officer {$officer['full_name']} (ID: {$officer['id']})";
                    $systemUserId = 1; // System user ID (or use borrower's ID)
                    $assignLog->bind_param("is", $systemUserId, $assignAction);
                    $assignLog->execute();
                    
                    $success = "Your loan application has been submitted and assigned to an officer for review! Application ID: #" . $loan_id;
                } else {
                    // Assignment failed, but loan was created
                    $success = "Your loan application has been submitted successfully! It will be assigned to an officer shortly. Application ID: #" . $loan_id;
                }
                
                $assignStmt->close();
            } else {
                // No officers available,leave unassigned for admin to manually assign
                $success = "Your loan application has been submitted successfully! It will be assigned to an officer shortly. Application ID: #" . $loan_id;
                
                // Log that no officers were available
                $noOfficerLog = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                $noOfficerAction = "Loan #{$loan_id} submitted but no officers available for auto-assignment";
                $noOfficerLog->bind_param("is", $user['id'], $noOfficerAction);
                $noOfficerLog->execute();
            }
            // Log borrower's application activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $log_action = "Applied for loan of KES " . number_format($amount, 2);
            $log_stmt->bind_param("is", $user['id'], $log_action);
            $log_stmt->execute();
            
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
<link rel="stylesheet" href="../assets/css/apply-loan.css">
</head>
<body style="background: #000; color: #fff;">

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s; padding: 30px;">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="welcome" style="margin-bottom: 30px;">
        <h2 style="font-weight: 800; margin: 0;">Capital Request</h2>
        <p style="color: #666; margin-top: 5px;">Submit your loan details for processing.</p>
    </div>

    <div class="form-container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 10px;"><a href="my-loans.php" style="color: #fff; text-decoration: underline;">Track Applications →</a></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="info-box">
            <h4>Application Guidelines</h4>
            <p style="margin: 0; line-height: 1.8; font-size: 13px;">
                • Limits: <strong>KES 500 — KES 10,000</strong><br>
                • Interest: <strong><?php echo number_format($interest_rate, 2); ?>% Flat Rate</strong> per month<br>
                • Repayment: Monthly installments via dashboard<br>
                • <strong style="color: #f0a500;">Auto-Assignment:</strong> Your application will be automatically assigned to an available officer
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
                <label for="purpose">Loan Purpose <span class="required">*</span></label>
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
        
        //Interest Calculation using (P * R * T) / 100
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