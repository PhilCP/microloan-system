<?php
/**
 * Approved Loans & Repayments Management
 * Officer-side interface for tracking active debt and logging capital recovery.
 */
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

// Tactical Query: Fetch active loans with real-time aggregate repayment stats
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

$pageTitle = "Repayment Management";
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
/* Dashboard Theme Overrides */
body { background: #050505; color: #fff; }

/* Modal Tactical Styling */
.modal { 
    display: none; 
    position: fixed; 
    z-index: 9999; 
    left: 0; top: 0; width: 100%; height: 100%; 
    background: rgba(0,0,0,0.9); 
    backdrop-filter: blur(8px); 
}

.modal-content { 
    background: #0f0f0f; 
    margin: 5% auto;
    padding: 30px; 
    border: 1px solid #333; 
    width: 90%;
    max-width: 500px;
    border-radius: 12px; 
    box-shadow: 0 20px 50px rgba(0,0,0,1); 
}

.modal-header { border-bottom: 1px solid #222; margin-bottom: 20px; padding-bottom: 10px; }
.modal-header h3 { color: #f0a500; text-transform: uppercase; letter-spacing: 2px; font-weight: 900; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; color: #666; font-size: 11px; font-weight: 800; text-transform: uppercase; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 12px; background: #000; border: 1px solid #222; border-radius: 6px; color: #fff; font-size: 14px; transition: 0.3s;
}
.form-group input:focus { border-color: #f0a500; outline: none; }

.payment-summary-box {
    background: rgba(240, 165, 0, 0.05);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid rgba(240, 165, 0, 0.1);
}

/* Loan Card Grid */
.loans-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; }

.loan-card {
    background: #0a0a0a;
    border: 1px solid #1a1a1a;
    border-radius: 12px;
    padding: 24px;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}
.loan-card:hover { border-color: #f0a500; transform: translateY(-5px); }

.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.ref-id { font-family: 'Courier New', monospace; color: #f0a500; font-weight: 800; }

.borrower-tag { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 5px; }
.contact-tag { font-size: 12px; color: #444; margin-bottom: 20px; }

.stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.stat-item { background: #111; padding: 12px; border-radius: 8px; }
.stat-label { font-size: 9px; color: #444; text-transform: uppercase; font-weight: 800; }
.stat-value { font-size: 15px; font-weight: 700; }

.progress-track { height: 6px; background: #1a1a1a; border-radius: 10px; margin: 15px 0; overflow: hidden; }
.progress-fill { height: 100%; background: #f0a500; box-shadow: 0 0 10px rgba(240, 165, 0, 0.3); }

.action-btn {
    width: 100%; padding: 14px; background: #f0a500; color: #000; border: none; border-radius: 8px; 
    font-weight: 900; text-transform: uppercase; cursor: pointer; transition: 0.3s;
}
.action-btn:hover { background: #ffc107; box-shadow: 0 5px 15px rgba(240, 165, 0, 0.2); }
.action-btn:disabled { background: #111; color: #333; cursor: not-allowed; }

@media (max-width: 768px) { .loans-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Credit Entry</h3></div>
        <div id="paymentLoanInfo" class="payment-summary-box"></div>
        
        <form id="paymentForm">
            <input type="hidden" id="loanId" name="loan_id">
            <div class="form-group">
                <label>Recovery Amount (KES)</label>
                <input type="number" id="paymentAmount" name="amount_paid" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Transaction Date</label>
                <input type="date" id="paymentDate" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Settlement Method</label>
                <select name="payment_method" required>
                    <option value="cash">Field Cash</option>
                    <option value="bank_transfer">Direct Deposit</option>
                    <option value="mobile_money">M-PESA Utility</option>
                </select>
            </div>
            <div class="form-group">
                <label>Internal Reference / Receipt #</label>
                <input type="text" name="receipt_number" placeholder="Optional">
            </div>
            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="button" id="closeModal" class="action-btn" style="background:#1a1a1a; color:#fff;">Abort</button>
                <button type="submit" id="submitPayment" class="action-btn">Commit Entry</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; padding: 30px;">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="header-section" style="margin-bottom: 40px;">
        <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">Repayment Management</h2>
        <p style="color: #444;">Monitor operational liquidity and borrower performance.</p>
    </div>

    <div class="loans-grid">
        <?php if ($approvedResult->num_rows > 0): ?>
            <?php while ($loan = $approvedResult->fetch_assoc()): ?>
                <?php
                $totalPaid = $loan['total_paid'] ?? 0;
                $balance = $loan['remaining_balance'];
                $totalContract = $loan['total_amount'];
                $progressPercent = ($totalContract > 0) ? ($totalPaid / $totalContract) * 100 : 0;
                ?>
                <div class="loan-card">
                    <div class="card-header">
                        <span class="ref-id">#LN-<?php echo str_pad($loan['id'], 4, '0', STR_PAD_LEFT); ?></span>
                        <span style="font-size: 10px; color: #22c55e; font-weight: 900;">[ ACTIVE ]</span>
                    </div>

                    <div class="borrower-tag"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                    <div class="contact-tag">📞 <?php echo htmlspecialchars($loan['phone']); ?></div>

                    <div class="stat-grid">
                        <div class="stat-item">
                            <div class="stat-label">Total Contract</div>
                            <div class="stat-value">KES <?php echo number_format($totalContract); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Recovery (Paid)</div>
                            <div class="stat-value" style="color: #22c55e;">KES <?php echo number_format($totalPaid); ?></div>
                        </div>
                        <div class="stat-item" style="grid-column: span 2; border-top: 1px solid #222; margin-top: 5px; padding-top: 15px;">
                            <div class="stat-label">Outstanding Liability</div>
                            <div class="stat-value" style="color: #f0a500; font-size: 20px;">KES <?php echo number_format($balance); ?></div>
                        </div>
                    </div>

                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?php echo min($progressPercent, 100); ?>%"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:10px; color:#444; margin-bottom:20px; font-weight:800;">
                        <span>COLLECTED: <?php echo round($progressPercent); ?>%</span>
                        <span>TERMS: <?php echo $loan['duration_months']; ?> MONTHS</span>
                    </div>

                    <button class="action-btn trigger-payment"
                            data-id="<?php echo $loan['id']; ?>"
                            data-name="<?php echo htmlspecialchars($loan['full_name']); ?>"
                            data-balance="<?php echo $balance; ?>">
                        Record Capital Recovery
                    </button>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align:center; padding: 100px; color:#222;">
                <h3 style="text-transform:uppercase;">No Active Debt Found</h3>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
const modal = document.getElementById('paymentModal');

// Open Modal & Populate Data
document.querySelectorAll('.trigger-payment').forEach(btn => {
    btn.onclick = function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        const bal = this.dataset.balance;

        document.getElementById('loanId').value = id;
        document.getElementById('paymentAmount').max = bal;
        document.getElementById('paymentAmount').value = bal;
        document.getElementById('paymentLoanInfo').innerHTML = `
            <div style="font-size:12px; color:#666;">CREDITING ACCOUNT:</div>
            <div style="font-weight:900; color:#fff;">${name} (Ref: #LN-${id.padStart(4, '0')})</div>
            <div style="font-size:11px; color:#f0a500; margin-top:5px;">MAX RECOVERY: KES ${parseFloat(bal).toLocaleString()}</div>
        `;
        modal.style.display = 'block';
    };
});

// Close Logic
document.getElementById('closeModal').onclick = () => modal.style.display = 'none';
window.onclick = (e) => { if (e.target == modal) modal.style.display = 'none'; };

// AJAX Submission
document.getElementById('paymentForm').onsubmit = function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitPayment');
    btn.disabled = true;
    btn.innerHTML = "COMMITTING...";

    fetch('record_payment.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json())
    .then(data => {
        if (data.success) { 
            modal.style.display = 'none';
            location.reload(); 
        } else { 
            alert('SYSTEM REJECTION: ' + data.error); 
            btn.disabled = false;
            btn.innerHTML = "COMMIT ENTRY";
        }
    })
    .catch(() => { 
        alert('COMMUNICATION FAILURE'); 
        btn.disabled = false;
    });
};
</script>
</body>
</html>