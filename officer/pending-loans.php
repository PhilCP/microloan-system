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

// Get pending loans with borrower details
$sqlPending = "SELECT l.*, 
               u.full_name, u.email, u.phone,
               DATEDIFF(NOW(), l.created_at) as days_pending
               FROM loans l 
               JOIN users u ON l.borrower_id = u.id 
               WHERE l.status='pending' 
               ORDER BY l.created_at ASC";

$pendingResult = $conn->query($sqlPending);
if(!$pendingResult){
    die("Query failed: " . $conn->error);
}

$pageTitle = "Pending Loan Applications";
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
/* Modal CSS */
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
}

.modal-content { 
    background: #1a1a1a; 
    margin: 10% auto; 
    padding: 30px; 
    border: 1px solid #f0a500; 
    width: 90%;
    max-width: 500px;
    border-radius: 12px; 
    color: white; 
    box-shadow: 0 10px 40px rgba(0,0,0,0.5); 
}

.modal-content h3 { 
    color: #f0a500; 
    margin-top: 0; 
    margin-bottom: 20px;
}

.modal-loan-details {
    background: rgba(240, 165, 0, 0.1);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #f0a500;
}

.modal-loan-details p {
    margin: 8px 0;
    font-size: 14px;
}

.modal-loan-details strong {
    color: #f0a500;
}

.modal-content textarea { 
    width: 100%; 
    height: 100px; 
    background: #111; 
    border: 1px solid #333; 
    color: white; 
    padding: 12px; 
    margin: 15px 0; 
    border-radius: 6px; 
    resize: vertical;
    font-family: inherit;
}

.modal-content textarea:focus {
    outline: none;
    border-color: #f0a500;
}

.modal-actions { 
    display: flex; 
    gap: 10px; 
    justify-content: flex-end; 
}

.modal-actions button { 
    padding: 12px 24px; 
    border-radius: 6px; 
    cursor: pointer; 
    border: none; 
    font-weight: bold;
    transition: all 0.3s;
}

.btn-confirm { 
    background: #f0a500; 
    color: #000; 
}

.btn-confirm:hover {
    background: #ffc107;
}

.btn-cancel { 
    background: #333; 
    color: #fff; 
}

.btn-cancel:hover {
    background: #444;
}

/* Loan Cards */
.loans-grid {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.loan-card {
    background: #111;
    border: 1px solid #222;
    border-radius: 12px;
    padding: 24px;
    transition: all 0.3s;
}

.loan-card:hover {
    border-color: #f0a500;
    transform: translateY(-2px);
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

.days-pending {
    background: rgba(234, 179, 8, 0.2);
    color: #eab308;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.days-pending.urgent {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
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
    display: flex;
    flex-direction: column;
    gap: 4px;
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

.loan-purpose {
    background: #1a1a1a;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    border: 1px solid #222;
}

.purpose-label {
    font-size: 12px;
    color: #888;
    margin-bottom: 8px;
}

.purpose-text {
    color: #ddd;
    line-height: 1.6;
}

.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 10px;
}

.action-btn {
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.approve {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    border: 1px solid #22c55e;
}

.approve:hover {
    background: rgba(34, 197, 94, 0.3);
    transform: translateY(-2px);
}

.reject {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid #ef4444;
}

.reject:hover {
    background: rgba(239, 68, 68, 0.3);
    transform: translateY(-2px);
}

.notes-btn {
    background: #333;
    color: #f0a500;
    border: 1px solid #f0a500;
    padding: 12px 16px;
}

.notes-btn:hover {
    background: rgba(240, 165, 0, 0.1);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #888;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .loan-details-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- Modal -->
<div id="actionModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Review Loan Application</h3>
        <div class="modal-loan-details" id="modalLoanDetails">
            <!-- Details will be populated by JavaScript -->
        </div>
        <label for="officerRemarks" style="display: block; margin-bottom: 8px; color: #ddd;">
            Remarks / Reason (Optional)
        </label>
        <textarea id="officerRemarks" placeholder="Enter approval/rejection reason or additional notes..."></textarea>
        <div class="modal-actions">
            <button id="cancelModal" class="btn-cancel">Cancel</button>
            <button id="confirmAction" class="btn-confirm">Confirm Action</button>
        </div>
    </div>
</div>

<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2>⏳ Pending Loan Applications</h2>
    <p style="color: #999;">Review and process pending loan applications</p>
</div>

<div class="loans-grid">
    <?php if ($pendingResult->num_rows > 0): ?>
        <?php while ($loan = $pendingResult->fetch_assoc()): ?>
            <?php
            $isUrgent = $loan['days_pending'] >= 3;
            $monthlyPayment = $loan['total_amount'] / $loan['duration_months'];
            ?>
            <div class="loan-card" id="loanCard-<?php echo $loan['id']; ?>">
                <div class="loan-card-header">
                    <div class="loan-id">Loan #<?php echo $loan['id']; ?></div>
                    <div class="days-pending <?php echo $isUrgent ? 'urgent' : ''; ?>">
                        <?php echo $loan['days_pending']; ?> day<?php echo $loan['days_pending'] != 1 ? 's' : ''; ?> pending
                    </div>
                </div>

                <div class="borrower-info">
                    <div class="borrower-name"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                    <div class="borrower-contact">
                        <span>📧 <?php echo htmlspecialchars($loan['email']); ?></span>
                        <span>📱 <?php echo htmlspecialchars($loan['phone']); ?></span>
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
                        <div class="detail-label">Duration</div>
                        <div class="detail-value"><?php echo $loan['duration_months']; ?> Months</div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Monthly Payment</div>
                        <div class="detail-value">KES <?php echo number_format($monthlyPayment, 2); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Interest Rate</div>
                        <div class="detail-value"><?php echo $loan['interest_rate']; ?>%</div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Applied Date</div>
                        <div class="detail-value"><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></div>
                    </div>
                </div>

                <?php if (!empty($loan['purpose'])): ?>
                    <div class="loan-purpose">
                        <div class="purpose-label">Loan Purpose</div>
                        <div class="purpose-text"><?php echo htmlspecialchars($loan['purpose']); ?></div>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <button class="action-btn approve" 
                            data-id="<?php echo $loan['id']; ?>"
                            data-action="approve"
                            data-borrower="<?php echo htmlspecialchars($loan['full_name']); ?>"
                            data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                            data-remarks="<?php echo htmlspecialchars($loan['admin_remarks'] ?? ''); ?>">
                        ✓ Approve
                    </button>
                    <button class="action-btn reject" 
                            data-id="<?php echo $loan['id']; ?>"
                            data-action="reject"
                            data-borrower="<?php echo htmlspecialchars($loan['full_name']); ?>"
                            data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                            data-remarks="<?php echo htmlspecialchars($loan['admin_remarks'] ?? ''); ?>">
                        ✗ Reject
                    </button>
                    <button class="action-btn notes-btn" 
                            data-id="<?php echo $loan['id']; ?>"
                            data-action="remark_only"
                            data-borrower="<?php echo htmlspecialchars($loan['full_name']); ?>"
                            data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                            data-remarks="<?php echo htmlspecialchars($loan['admin_remarks'] ?? ''); ?>">
                        💬 Notes
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">✅</div>
            <h3 style="color: #ddd;">All Caught Up!</h3>
            <p>No pending loan applications at the moment.</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click',()=>{
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('dashboardMain').classList.toggle('shifted');
});

// Modal and Action Handling
let currentLoanId = null;
let currentAction = null;
const modal = document.getElementById('actionModal');
const remarksInput = document.getElementById('officerRemarks');

// Add click handlers to all action buttons
document.querySelectorAll('.action-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        currentLoanId = this.dataset.id;
        currentAction = this.dataset.action;
        const borrower = this.dataset.borrower;
        const amount = this.dataset.amount;
        const existingRemarks = this.dataset.remarks || '';
        
        if (currentAction === 'remark_only') {
            document.getElementById('modalTitle').textContent = 'Internal Notes';
            document.getElementById('modalTitle').style.color = '#f0a500';
            document.getElementById('modalLoanDetails').innerHTML = `
                <p><strong>Loan ID:</strong> #${currentLoanId}</p>
                <p><strong>Borrower:</strong> ${borrower}</p>
                <p><strong>Amount:</strong> KES ${amount}</p>
            `;
        } else {
            const actionText = currentAction === 'approve' ? 'Approve' : 'Reject';
            const actionColor = currentAction === 'approve' ? '#22c55e' : '#ef4444';
            
            document.getElementById('modalTitle').textContent = `${actionText} Loan Application`;
            document.getElementById('modalTitle').style.color = actionColor;
            document.getElementById('modalLoanDetails').innerHTML = `
                <p><strong>Loan ID:</strong> #${currentLoanId}</p>
                <p><strong>Borrower:</strong> ${borrower}</p>
                <p><strong>Amount:</strong> KES ${amount}</p>
                <p><strong>Action:</strong> ${actionText.toUpperCase()}</p>
            `;
        }
        
        remarksInput.value = existingRemarks;
        modal.style.display = 'block';
    });
});

// Close modal
document.getElementById('cancelModal').onclick = () => modal.style.display = 'none';
window.onclick = (event) => { 
    if (event.target == modal) modal.style.display = 'none'; 
};

// Confirm action
document.getElementById('confirmAction').onclick = function() {
    const btn = this;
    const remarks = remarksInput.value.trim();
    
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Processing...';
    
    fetch('loan_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `loan_id=${currentLoanId}&loan_action=${currentAction}&admin_remarks=${encodeURIComponent(remarks)}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            if(currentAction !== 'remark_only') {
                // Remove the loan card from view
                const loanCard = document.getElementById(`loanCard-${currentLoanId}`);
                if(loanCard) {
                    loanCard.style.transition = 'all 0.3s';
                    loanCard.style.opacity = '0';
                    loanCard.style.transform = 'translateX(-100%)';
                    setTimeout(() => {
                        loanCard.remove();
                        
                        // Check if no loans left
                        const remainingCards = document.querySelectorAll('.loan-card');
                        if(remainingCards.length === 0) {
                            location.reload();
                        }
                    }, 300);
                }
            } else {
                // Update notes button data
                const notesBtns = document.querySelectorAll(`[data-id="${currentLoanId}"]`);
                notesBtns.forEach(noteBtn => {
                    noteBtn.dataset.remarks = remarks;
                });
                alert('Notes updated successfully');
            }
            
            modal.style.display = 'none';
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error. Please try again.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
    });
};
</script>

</main>
</body>
</html>