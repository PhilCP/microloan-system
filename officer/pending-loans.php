<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
if(!$user){ die("User not found. Check session."); }

$sqlPending = "SELECT l.*, 
               u.full_name, u.email, u.phone,
               DATEDIFF(NOW(), l.created_at) as days_pending
               FROM loans l 
               JOIN users u ON l.borrower_id = u.id 
               WHERE l.status = 'pending' 
               AND l.assigned_officer_id = ?
               ORDER BY l.created_at ASC";

$stmt = $conn->prepare($sqlPending);
if(!$stmt){ die("Query prepare failed: " . $conn->error); }
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$pendingResult = $stmt->get_result();

// ── Parse pipe-separated collateral string into structured HTML ──
function parseCollateralDesc(string $desc): string {
    if (empty(trim($desc))) return '';
    $parts = explode(' | ', $desc);
    $lines = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $colonPos = strpos($part, ': ');
        if ($colonPos !== false) {
            $label = substr($part, 0, $colonPos);
            $value = substr($part, $colonPos + 2);
            $lines[] = '<span class="cd-label">' . htmlspecialchars($label) . ':</span> '
                     . '<span class="cd-value">' . htmlspecialchars($value) . '</span>';
        } else {
            $lines[] = '<span class="cd-value">' . htmlspecialchars($part) . '</span>';
        }
    }
    return implode('<br>', $lines);
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
<link rel="stylesheet" href="../assets/css/officer-dashboard.css">
<link rel="stylesheet" href="../assets/css/pending-loans.css">

</head>
<body style="background: #000;">

<!-- Action Modal -->
<div id="actionModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Review Loan Application</h3>
        <div class="modal-loan-details" id="modalLoanDetails"></div>
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

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s; padding: 30px;">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2 style="color: #fff;">Pending Loan Applications</h2>
    <p style="color: #999;">Review and process pending loan applications assigned to you</p>
</div>

<div class="loans-grid">
    <?php if ($pendingResult->num_rows > 0): ?>
        <?php while ($loan = $pendingResult->fetch_assoc()): ?>
            <?php
            $isUrgent       = $loan['days_pending'] >= 3;
            $monthlyPayment = $loan['total_amount'] / $loan['duration_months'];
            $penaltyRate    = (float)($loan['overdue_penalty_rate'] ?? 0);
            $monthlyPenalty = round($loan['amount'] * $penaltyRate / 100, 2);

            $collateralType = $loan['collateral_type'] ?? '';
            $collateralDesc = $loan['collateral_description'] ?? '';
            $collateralHTML = parseCollateralDesc($collateralDesc);
            ?>
            <div class="loan-card" id="loanCard-<?php echo $loan['id']; ?>">

                <!-- Header -->
                <div class="loan-card-header">
                    <div class="loan-id">Loan #<?php echo $loan['id']; ?></div>
                    <div class="days-pending <?php echo $isUrgent ? 'urgent' : ''; ?>">
                        <?php echo $loan['days_pending']; ?> day<?php echo $loan['days_pending'] != 1 ? 's' : ''; ?> pending
                    </div>
                </div>

                <!-- Borrower -->
                <div class="borrower-info">
                    <div class="borrower-name"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                    <div class="borrower-contact">
                        <span><?php echo htmlspecialchars($loan['email']); ?></span>
                        <span><?php echo htmlspecialchars($loan['phone']); ?></span>
                    </div>
                </div>

                <!-- Loan figures -->
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

                <!-- Purpose -->
                <?php if (!empty($loan['purpose'])): ?>
                    <div class="loan-purpose">
                        <div class="purpose-label">Loan Purpose</div>
                        <div class="purpose-text"><?php echo htmlspecialchars($loan['purpose']); ?></div>
                    </div>
                <?php endif; ?>

                <hr class="card-section-divider">

                <!-- ── COLLATERAL / SECURITY ── -->
                <div class="collateral-block">
                    <div class="block-label">🔒 Security / Collateral</div>
                    <?php if (!empty($collateralType)): ?>
                        <div class="collateral-type-badge"><?php echo htmlspecialchars($collateralType); ?></div>
                        <?php if (!empty($collateralHTML)): ?>
                            <div class="collateral-details"><?php echo $collateralHTML; ?></div>
                        <?php else: ?>
                            <div class="collateral-none" style="margin-top:4px;">No description provided</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="collateral-none">⚠ No collateral declared — assess risk before approving</div>
                    <?php endif; ?>
                </div>

                <!-- ── OVERDUE PENALTY INFO ── -->
                <div class="penalty-block">
                    <div class="penalty-icon">⚠️</div>
                    <div class="penalty-text">
                        <div class="penalty-title">Default Penalty Rate</div>
                        <div class="penalty-detail">
                            <?php echo $penaltyRate; ?>% per month on outstanding balance
                            &nbsp;|&nbsp; Est. KES <?php echo number_format($monthlyPenalty, 2); ?>/month if overdue
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="action-buttons">
                    <button class="action-btn approve"
                            data-id="<?php echo $loan['id']; ?>"
                            data-action="approve"
                            data-borrower="<?php echo htmlspecialchars($loan['full_name']); ?>"
                            data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                            data-collateral="<?php echo htmlspecialchars($collateralType); ?>"
                            data-collateral-desc="<?php echo htmlspecialchars($collateralDesc); ?>"
                            data-remarks="<?php echo htmlspecialchars($loan['admin_remarks'] ?? ''); ?>">
                        Approve
                    </button>
                    <button class="action-btn reject"
                            data-id="<?php echo $loan['id']; ?>"
                            data-action="reject"
                            data-borrower="<?php echo htmlspecialchars($loan['full_name']); ?>"
                            data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                            data-collateral="<?php echo htmlspecialchars($collateralType); ?>"
                            data-collateral-desc="<?php echo htmlspecialchars($collateralDesc); ?>"
                            data-remarks="<?php echo htmlspecialchars($loan['admin_remarks'] ?? ''); ?>">
                        Reject
                    </button>
                    <button class="action-btn notes-btn"
                            data-id="<?php echo $loan['id']; ?>"
                            data-action="remark_only"
                            data-borrower="<?php echo htmlspecialchars($loan['full_name']); ?>"
                            data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                            data-remarks="<?php echo htmlspecialchars($loan['admin_remarks'] ?? ''); ?>">
                        Notes
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"></div>
            <h3 style="color: #ddd;">All Caught Up!</h3>
            <p>No pending loan applications assigned to you at the moment.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

// ── Parse "Key: Value | Key: Value" into HTML table rows for modal ──
function parseCollateralForModal(desc) {
    if (!desc || !desc.trim()) return '<em style="color:#555;">No details provided</em>';
    const parts = desc.split(' | ');
    let html = '<table style="width:100%;border-collapse:collapse;margin-top:6px;font-size:13px;">';
    parts.forEach(part => {
        const idx = part.indexOf(': ');
        if (idx !== -1) {
            const label = part.substring(0, idx).trim();
            const value = part.substring(idx + 2).trim();
            html += `<tr>
                <td style="color:#888;font-size:10px;text-transform:uppercase;letter-spacing:.5px;
                           padding:4px 10px 4px 0;white-space:nowrap;vertical-align:top;
                           font-weight:600;">${label}</td>
                <td style="color:#ddd;padding:4px 0;">${value}</td>
            </tr>`;
        } else {
            html += `<tr><td colspan="2" style="color:#ddd;padding:4px 0;">${part.trim()}</td></tr>`;
        }
    });
    html += '</table>';
    return html;
}

let currentLoanId = null;
let currentAction = null;
const modal        = document.getElementById('actionModal');
const remarksInput = document.getElementById('officerRemarks');

document.querySelectorAll('.action-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        currentLoanId = this.dataset.id;
        currentAction = this.dataset.action;
        const borrower        = this.dataset.borrower;
        const amount          = this.dataset.amount;
        const collateral      = this.dataset.collateral || 'Not specified';
        const collateralDesc  = this.dataset.collateralDesc || '';
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
            const actionText  = currentAction === 'approve' ? 'Approve' : 'Reject';
            const actionColor = currentAction === 'approve' ? '#22c55e' : '#ef4444';

            document.getElementById('modalTitle').textContent = `${actionText} Loan Application`;
            document.getElementById('modalTitle').style.color = actionColor;
            document.getElementById('modalLoanDetails').innerHTML = `
                <p><strong>Loan ID:</strong> #${currentLoanId}</p>
                <p><strong>Borrower:</strong> ${borrower}</p>
                <p><strong>Amount:</strong> KES ${amount}</p>
                <p style="margin-top:8px;"><strong>Collateral Type:</strong>
                    <span style="background:rgba(240,165,0,0.15);color:#f0a500;
                                 border:1px solid rgba(240,165,0,0.3);border-radius:4px;
                                 padding:2px 8px;font-size:11px;font-weight:700;
                                 text-transform:uppercase;margin-left:6px;">${collateral}</span>
                </p>
                <div style="margin-top:6px;">${parseCollateralForModal(collateralDesc)}</div>
                <p style="margin-top:10px;"><strong>Action:</strong> ${actionText.toUpperCase()}</p>
            `;
        }

        remarksInput.value  = existingRemarks;
        modal.style.display = 'block';
    });
});

document.getElementById('cancelModal').onclick = () => modal.style.display = 'none';
window.onclick = (event) => { if (event.target == modal) modal.style.display = 'none'; };

document.getElementById('confirmAction').onclick = function () {
    const btn          = this;
    const remarks      = remarksInput.value.trim();
    btn.disabled       = true;
    const originalText = btn.textContent;
    btn.textContent    = 'Processing...';

    fetch('loan_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `loan_id=${currentLoanId}&loan_action=${currentAction}&admin_remarks=${encodeURIComponent(remarks)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (currentAction !== 'remark_only') {
                const loanCard = document.getElementById(`loanCard-${currentLoanId}`);
                if (loanCard) {
                    loanCard.style.transition = 'all 0.3s';
                    loanCard.style.opacity    = '0';
                    loanCard.style.transform  = 'translateX(-100%)';
                    setTimeout(() => {
                        loanCard.remove();
                        if (document.querySelectorAll('.loan-card').length === 0) location.reload();
                    }, 300);
                }
            } else {
                document.querySelectorAll(`[data-id="${currentLoanId}"]`).forEach(b => {
                    b.dataset.remarks = remarks;
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
        btn.disabled    = false;
        btn.textContent = originalText;
    });
};
</script>

</main>
</body>
</html>