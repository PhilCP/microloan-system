<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
$filter = $_GET['filter'] ?? 'all';

$pageTitle = "My Loan Portfolio";
$role = "borrower";

$query = "SELECT l.*, u.full_name as approved_by_name 
          FROM loans l 
          LEFT JOIN users u ON l.approved_by = u.id 
          WHERE l.borrower_id = ?";

if ($filter != 'all') {
    $query .= " AND l.status = ?";
}
$query .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($query);
if ($filter != 'all') {
    $stmt->bind_param("is", $user['id'], $filter);
} else {
    $stmt->bind_param("i", $user['id']);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/my-loans.css">
</head>
<body style="background: #000; color: #fff;">

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s; padding: 30px;">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="page-header">
        <div class="welcome">
            <h2 style="margin:0; font-weight:800;">Loan Portfolio</h2>
            <p style="color: #666; margin-top:5px;">Track and manage your operational capital.</p>
        </div>
        <div class="btn-group-header">
            <a href="print-loan-history.php" class="btn-action btn-report">Full Report</a>
            <a href="apply-loan.php" class="btn-action">＋ New Request</a>
        </div>
    </div>

    <div class="filters">
        <?php
        $statuses = ['all', 'pending', 'approved', 'rejected', 'completed'];
        foreach ($statuses as $s): ?>
            <a href="?filter=<?php echo $s; ?>" class="filter-btn <?php echo $filter == $s ? 'active' : ''; ?>">
                <?php echo ucfirst($s); ?> Loans
            </a>
        <?php endforeach; ?>
    </div>

    <div class="loans-container">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($loan = $result->fetch_assoc()): ?>
                <?php
                $total     = $loan['total_amount'];
                $remaining = $loan['remaining_balance'];
                $paid      = $total - $remaining;
                $progress  = ($total > 0) ? ($paid / $total) * 100 : 0;
                ?>
                <div class="loan-card" data-loan-id="<?php echo $loan['id']; ?>">
                        <?php
    $isOverdue = $loan['due_date']
        && strtotime(date('Y-m-d')) > strtotime($loan['due_date'])
        && $loan['status'] == 'approved'
        && $loan['remaining_balance'] > 0;

    if ($isOverdue):
        $daysOverdue = (int)ceil((time() - strtotime($loan['due_date'])) / 86400);
    ?>
    <div style="background:linear-gradient(135deg,#2b0a0a,#1a0000);
                border:1px solid #ef4444; border-left:5px solid #ef4444;
                border-radius:10px; padding:12px 16px; margin-bottom:16px;
                display:flex; justify-content:space-between; align-items:center;
                flex-wrap:wrap; gap:8px; animation: pulse-border 2s infinite;">
        <div>
            <div style="color:#ef4444; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.08em;">
                ⚠ Overdue — <?php echo $daysOverdue; ?> day<?php echo $daysOverdue != 1 ? 's' : ''; ?> past due
            </div>
            <div style="color:#f87171; font-size:12px; margin-top:3px;">
                Outstanding: <strong>KES <?php echo number_format($loan['remaining_balance'], 2); ?></strong>
            </div>
        </div>
        <a href="repay-loan.php?id=<?php echo $loan['id']; ?>"
           style="background:#ef4444; color:#fff; font-weight:800; font-size:12px;
                  padding:7px 16px; border-radius:7px; text-decoration:none; white-space:nowrap;">
            Pay Now →
        </a>
    </div>
    <?php endif; ?>

                    <!-- Header -->
                    <div class="loan-header">
                        <div class="loan-id">REF: #LN-<?php echo str_pad($loan['id'], 5, '0', STR_PAD_LEFT); ?></div>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <?php if ($loan['due_date'] && strtotime(date('Y-m-d')) > strtotime($loan['due_date']) && $loan['status'] != 'completed'): ?>
                                <span class="status-badge" style="background:rgba(239,68,68,0.2); color:#ef4444; border:1px solid #ef4444;">Overdue</span>
                            <?php endif; ?>
                            <span class="status-badge status-<?php echo $loan['status']; ?>">
                                <?php echo $loan['status']; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Loan Figures -->
                    <div class="loan-details-grid">
                        <div class="detail-box">
                            <div class="detail-label">Principal</div>
                            <div class="detail-value">KES <?php echo number_format($loan['amount']); ?></div>
                        </div>
                        <div class="detail-box">
                            <div class="detail-label">Total Debt</div>
                            <div class="detail-value">KES <?php echo number_format($loan['total_amount']); ?></div>
                        </div>
                        <div class="detail-box">
                            <div class="detail-label">Duration</div>
                            <div class="detail-value"><?php echo $loan['duration_months']; ?> Months</div>
                        </div>
                        <div class="detail-box">
                            <div class="detail-label">Rate</div>
                            <div class="detail-value"><?php echo $loan['interest_rate']; ?>%</div>
                        </div>
                    </div>

                    <!-- Repayment Progress -->
                    <?php if ($loan['status'] == 'approved' || $loan['status'] == 'completed'): ?>
                        <div class="repayment-progress">
                            <div class="progress-header">
                                <span style="font-weight:700; color:#888;">REPAYMENT PROGRESS</span>
                                <span style="color:#f0a500; font-weight:800;"><?php echo round($progress); ?>%</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-top:10px; font-size:11px; color:#444;">
                                <span>Paid: KES <?php echo number_format($paid); ?></span>
                                <span>Balance: KES <?php echo number_format($remaining); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Purpose -->
                    <?php if (!empty($loan['purpose'])): ?>
                        <div style="background:rgba(255,255,255,0.02); padding:12px; border-radius:6px; margin-bottom:15px; border:1px solid #111;">
                            <div class="detail-label" style="margin-bottom:8px;">Operational Purpose</div>
                            <div style="font-size:13px; color:#aaa; line-height:1.5;"><?php echo htmlspecialchars($loan['purpose']); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Collateral / Security -->
                    <div style="background:rgba(240,165,0,0.04); border:1px solid rgba(240,165,0,0.18); border-left:3px solid #f0a500; border-radius:8px; padding:12px 15px; margin-bottom:15px;">
                        <div class="detail-label" style="color:#f0a500; margin-bottom:8px;">Security / Collateral</div>
                        <?php if (!empty($loan['collateral_type'])): ?>
                            <span style="display:inline-block; background:rgba(240,165,0,0.12); color:#f0a500; border:1px solid rgba(240,165,0,0.3); border-radius:4px; padding:2px 9px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                                <?php echo htmlspecialchars($loan['collateral_type']); ?>
                            </span>
                            <div style="font-size:13px; color:#aaa; line-height:1.5;">
                                <?php echo !empty($loan['collateral_description'])
                                    ? htmlspecialchars($loan['collateral_description'])
                                    : '<em style="color:#444;">No description provided</em>'; ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size:12px; color:#444; font-style:italic;">No collateral recorded for this loan.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Rejection Reason -->
                    <?php if ($loan['status'] == 'rejected' && !empty($loan['admin_remarks'])): ?>
                        <div style="background:rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.25); border-left:3px solid #ef4444; border-radius:8px; padding:12px 15px; margin-bottom:15px;">
                            <div class="detail-label" style="color:#ef4444; margin-bottom:6px;">⚠ Rejection Reason</div>
                            <div style="font-size:13px; color:#f87171; line-height:1.5;">
                                <?php echo htmlspecialchars($loan['admin_remarks']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Card Footer -->
                    <div class="card-footer">
                        <div class="date-applied">
                            SUBMITTED: <?php echo strtoupper(date('d M Y', strtotime($loan['created_at']))); ?>
                            <?php if ($loan['approved_by_name']): ?>
                                <span style="margin-left:15px; color:#22c55e;">• VERIFIED BY: <?php echo strtoupper(htmlspecialchars($loan['approved_by_name'])); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions">
                            <?php if ($loan['status'] == 'approved' && $loan['remaining_balance'] > 0): ?>
                                <a href="repay-loan.php?id=<?php echo $loan['id']; ?>" class="btn-small" style="background:#f0a500; color:#000;">MAKE PAYMENT</a>
                            <?php endif; ?>
                            <?php if (in_array($loan['status'], ['approved', 'completed'])): ?>
                                <a href="print-statement.php?id=<?php echo $loan['id']; ?>" class="btn-small">STATEMENT</a>
                            <?php endif; ?>
                            <?php if ($loan['status'] == 'pending'): ?>
                                <button class="btn-small"
                                        style="background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.3); cursor:pointer;"
                                        onclick="cancelLoan(<?php echo $loan['id']; ?>)">
                                    CANCEL
                                </button>
                            <?php endif; ?>
                            <?php if ($loan['status'] == 'rejected'): ?>
                                <a href="apply-loan.php" class="btn-small"
                                style="background:rgba(240,165,0,0.1); color:#f0a500; border:1px solid rgba(240,165,0,0.3);">
                                    RE-APPLY
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 20px;">📂</div>
                <h3 style="color: #fff;">No records found</h3>
                <p style="color: #444; font-size: 14px; margin-bottom: 25px;">Adjust your filters or initiate a new capital request.</p>
                <a href="apply-loan.php" class="btn-action" style="display: inline-flex;">Start Application</a>
            </div>
        <?php endif; ?>
    </div>

</main>

<script>
document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

function cancelLoan(loanId) {
    if (!confirm('Are you sure you want to cancel this loan application? This cannot be undone.')) return;

    fetch('cancel-loan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'loan_id=' + loanId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const card = document.querySelector(`[data-loan-id="${loanId}"]`);
            if (card) {
                card.style.transition = 'all 0.3s';
                card.style.opacity    = '0';
                card.style.transform  = 'translateX(-100%)';
                setTimeout(() => card.remove(), 300);
            } else {
                location.reload();
            }
        } else {
            alert('Error: ' + (data.error || 'Could not cancel loan.'));
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

</body>
</html>