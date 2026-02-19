<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
if(!$user){
    die("User not found. Check session.");
}

// Get approved loans with repayment details
$sqlApproved = "SELECT l.*, 
                u.full_name, u.email, u.phone,
                (SELECT SUM(amount_paid) FROM repayments WHERE loan_id = l.id) as total_paid,
                (SELECT COUNT(*) FROM repayments WHERE loan_id = l.id) as payment_count,
                (SELECT MAX(payment_date) FROM repayments WHERE loan_id = l.id) as last_payment_date
                FROM loans l 
                JOIN users u ON l.borrower_id = u.id 
                WHERE l.status='approved' 
                ORDER BY l.created_at DESC";

$approvedResult = $conn->query($sqlApproved);
if(!$approvedResult){
    die("Query failed: " . $conn->error);
}

$pageTitle = "Approved Loans & Repayments";
$role = "officer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<style>
/* Payment Modal Adjustments for Screen Fit */
.modal { 
    display: none; 
    position: fixed; 
    z-index: 9999; 
    left: 0; 
    top: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.85); 
    backdrop-filter: blur(5px); 
    overflow-y: auto; /* Enable scroll if modal is too tall */
    padding: 20px 0;
}

.modal-content { 
    background: #1a1a1a; 
    margin: 2% auto; /* Reduced margin to fit screen better */
    padding: 25px; 
    border: 1px solid #f0a500; 
    width: 95%;
    max-width: 480px;
    border-radius: 12px; 
    color: white; 
    box-shadow: 0 10px 40px rgba(0,0,0,0.5); 
    position: relative;
}

.modal-content h3 { 
    color: #f0a500; 
    margin-top: 0; 
    margin-bottom: 15px;
    font-size: 1.2rem;
}

.form-group {
    margin-bottom: 15px; /* Tighter spacing */
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #ddd;
    font-size: 14px;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    background: #111;
    border: 1px solid #333;
    border-radius: 6px;
    color: #fff;
    font-family: inherit;
    font-size: 14px;
}

.payment-summary {
    background: rgba(34, 197, 94, 0.1);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 4px solid #22c55e;
}

.payment-summary p {
    margin: 4px 0;
    font-size: 13px;
}

/* Container Spacing Fix */
.welcome {
    margin-bottom: 30px; /* Space between header and loan cards */
}

/* Loan Cards */
.loan-card {
    background: #111;
    border: 1px solid #222;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.3s;
}

.loan-card:hover {
    border-color: #f0a500;
}

.loan-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.loan-id {
    font-size: 20px;
    font-weight: 700;
    color: #f0a500;
}

.loan-status-badge {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.loan-status-badge.completed {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.borrower-info {
    background: rgba(59, 130, 246, 0.1);
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    border-left: 4px solid #3b82f6;
}

.borrower-name {
    font-size: 18px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
}

.borrower-contact {
    font-size: 13px;
    color: #888;
}

.loan-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.detail-box {
    background: rgba(240, 165, 0, 0.05);
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(240, 165, 0, 0.1);
}

.detail-label {
    font-size: 11px;
    color: #888;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.detail-value {
    font-size: 16px;
    font-weight: 600;
    color: #ddd;
}

.progress-section {
    margin: 20px 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #888;
    margin-bottom: 8px;
}

.progress-bar-container {
    background: #222;
    height: 12px;
    border-radius: 6px;
    overflow: hidden;
}

.progress-bar-fill {
    background: linear-gradient(90deg, #22c55e, #16a34a);
    height: 100%;
    border-radius: 6px;
    transition: width 0.3s;
}

.record-payment-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #f0a500, #ff8c00);
    color: #000;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.record-payment-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(240, 165, 0, 0.4);
}

.record-payment-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #888;
}

.modal-actions { 
    display: flex; 
    gap: 10px; 
    justify-content: flex-end;
    margin-top: 15px;
}

.modal-actions button { 
    padding: 10px 20px; 
    border-radius: 6px; 
    cursor: pointer; 
    border: none; 
    font-weight: bold;
}

.btn-submit { background: #22c55e; color: #000; }
.btn-cancel { background: #333; color: #fff; }

@media (max-height: 700px) {
    .modal-content { margin: 1% auto; padding: 15px; }
    .form-group { margin-bottom: 10px; }
}
</style>
</head>
<body>

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <h3>💰 Record Loan Repayment</h3>
        <div id="paymentLoanInfo" class="payment-summary">
            </div>
        
        <form id="paymentForm">
            <input type="hidden" id="loanId" name="loan_id">
            
            <div class="form-group">
                <label for="paymentAmount">Amount (KES) *</label>
                <input type="number" id="paymentAmount" name="amount_paid" min="1" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="paymentDate">Payment Date *</label>
                <input type="date" id="paymentDate" name="payment_date" required max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="paymentMethod">Method *</label>
                <select id="paymentMethod" name="payment_method" required>
                    <option value="">Select method</option>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_money">Mobile Money (M-Pesa)</option>
                    <option value="cheque">Cheque</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="receiptNumber">Receipt Reference</label>
                <input type="text" id="receiptNumber" name="receipt_number">
            </div>
            
            <div class="form-group">
                <label for="paymentNotes">Notes</label>
                <textarea id="paymentNotes" name="notes" rows="2"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" id="cancelPayment" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-submit">Record</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2>💰 Approved Loans & Repayments</h2>
    <p style="color: #999;">Manage active loans and record repayments</p>
</div>

<div class="loans-container">
    <?php if ($approvedResult->num_rows > 0): ?>
        <?php while ($loan = $approvedResult->fetch_assoc()): ?>
            <?php
            $totalPaid = $loan['total_paid'] ?? 0;
            $remaining = $loan['remaining_balance'];
            $totalAmount = $loan['total_amount'] > 0 ? $loan['total_amount'] : 1; 
            $progress = ($totalPaid / $totalAmount) * 100;
            $isCompleted = $remaining <= 0;
            ?>
            <div class="loan-card">
                <div class="loan-card-header">
                    <div class="loan-id">Loan #<?php echo $loan['id']; ?></div>
                    <div class="loan-status-badge <?php echo $isCompleted ? 'completed' : ''; ?>">
                        <?php echo $isCompleted ? 'COMPLETED' : 'ACTIVE'; ?>
                    </div>
                </div>

                <div class="borrower-info">
                    <div class="borrower-name"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                    <div class="borrower-contact">
                        📧 <?php echo htmlspecialchars($loan['email']); ?> | 
                        📱 <?php echo htmlspecialchars($loan['phone']); ?>
                    </div>
                </div>

                <div class="loan-details-grid">
                    <div class="detail-box">
                        <div class="detail-label">Loan Amount</div>
                        <div class="detail-value">KES <?php echo number_format($loan['amount'], 2); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Total Repayment</div>
                        <div class="detail-value">KES <?php echo number_format($loan['total_amount'], 2); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Total Paid</div>
                        <div class="detail-value" style="color: #22c55e;">KES <?php echo number_format($totalPaid, 2); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Remaining</div>
                        <div class="detail-value" style="color: #eab308;">KES <?php echo number_format($remaining, 2); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Payments Made</div>
                        <div class="detail-value"><?php echo $loan['payment_count']; ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Last Payment</div>
                        <div class="detail-value">
                            <?php echo $loan['last_payment_date'] ? date('M d, Y', strtotime($loan['last_payment_date'])) : 'None'; ?>
                        </div>
                    </div>
                </div>

                <div class="progress-section">
                    <div class="progress-label">
                        <span>Repayment Progress</span>
                        <span><strong><?php echo number_format($progress, 1); ?>%</strong></span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min($progress, 100); ?>%"></div>
                    </div>
                </div>

                <button class="record-payment-btn"
                        data-loan-id="<?php echo $loan['id']; ?>"
                        data-borrower="<?php echo htmlspecialchars($loan['full_name']); ?>"
                        data-total="<?php echo $loan['total_amount']; ?>"
                        data-paid="<?php echo $totalPaid; ?>"
                        data-remaining="<?php echo $remaining; ?>"
                        <?php echo $isCompleted ? 'disabled' : ''; ?>>
                    <?php echo $isCompleted ? '✓ Loan Fully Repaid' : '💳 Record Payment'; ?>
                </button>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3 style="color: #ddd;">No Approved Loans</h3>
            <p>There are no approved loans to manage at the moment.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('sidebarToggle').addEventListener('click',()=>{
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('dashboardMain').classList.toggle('shifted');
});

const modal = document.getElementById('paymentModal');
let currentLoanData = {};

document.querySelectorAll('.record-payment-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.disabled) return;
        
        currentLoanData = {
            id: this.dataset.loanId,
            borrower: this.dataset.borrower,
            total: parseFloat(this.dataset.total),
            paid: parseFloat(this.dataset.paid),
            remaining: parseFloat(this.dataset.remaining)
        };
        
        document.getElementById('loanId').value = currentLoanData.id;
        document.getElementById('paymentAmount').max = currentLoanData.remaining;
        document.getElementById('paymentDate').value = '<?php echo date('Y-m-d'); ?>';
        
        document.getElementById('paymentLoanInfo').innerHTML = `
            <p><strong>Loan ID:</strong> #${currentLoanData.id} | <strong>Borrower:</strong> ${currentLoanData.borrower}</p>
            <p><strong>Balance:</strong> KES ${currentLoanData.remaining.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
        `;
        
        modal.style.display = 'block';
    });
});

document.getElementById('cancelPayment').onclick = () => {
    modal.style.display = 'none';
    document.getElementById('paymentForm').reset();
};

window.onclick = (event) => { 
    if (event.target == modal) {
        modal.style.display = 'none';
        document.getElementById('paymentForm').reset();
    }
};

document.getElementById('paymentForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const amount = parseFloat(formData.get('amount_paid'));
    
    if (amount <= 0 || amount > currentLoanData.remaining) {
        alert('Invalid amount. Must be greater than 0 and not exceed balance.');
        return;
    }
    
    const submitBtn = this.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.textContent = '...';
    
    fetch('record_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Success!');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(err => alert('Network error.'))
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Record';
    });
};
</script>
</main>
</body>
</html>