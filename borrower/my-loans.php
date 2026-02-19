<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
$filter = $_GET['filter'] ?? 'all';

// Page identity
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
<style>
/* Header Actions */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.btn-group-header {
    display: flex;
    gap: 10px;
}

.btn-action {
    background: #f0a500;
    color: #000;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    transition: 0.3s;
    border: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-action:hover {
    background: #ffc107;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(240, 165, 0, 0.3);
}

.btn-report {
    background: #1a1a1a;
    color: #fff;
    border: 1px solid #333;
}

/* Tactical Filters */
.filters {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    overflow-x: auto;
    padding-bottom: 5px;
}

.filter-btn {
    padding: 8px 18px;
    background: #0a0a0a;
    border: 1px solid #222;
    border-radius: 6px;
    color: #666;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    transition: 0.3s;
}

.filter-btn:hover, .filter-btn.active {
    border-color: #f0a500;
    color: #f0a500;
}

.filter-btn.active {
    background: rgba(240, 165, 0, 0.1);
}

/* Loan Card UI */
.loan-card {
    background: #0a0a0a;
    border: 1px solid #1a1a1a;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    transition: 0.3s;
}

.loan-card:hover {
    border-color: #333;
}

.loan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #1a1a1a;
}

.loan-id {
    font-size: 14px;
    font-weight: 800;
    color: #f0a500;
    letter-spacing: 1px;
}

.status-badge {
    font-size: 10px;
    font-weight: 900;
    padding: 4px 12px;
    border-radius: 4px;
    text-transform: uppercase;
}

.status-pending { background: rgba(234, 179, 8, 0.1); color: #eab308; }
.status-approved { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.status-rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.status-completed { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

.loan-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.detail-box {
    background: #111;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #1a1a1a;
}

.detail-label { font-size: 10px; color: #555; text-transform: uppercase; margin-bottom: 5px; font-weight: 700; }
.detail-value { font-size: 15px; color: #fff; font-weight: 700; }

/* Repayment Progress */
.repayment-progress {
    margin: 20px 0;
    background: #111;
    padding: 15px;
    border-radius: 8px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 10px;
}

.progress-track {
    height: 6px;
    background: #222;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #f0a500;
    box-shadow: 0 0 10px rgba(240, 165, 0, 0.4);
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #1a1a1a;
}

.date-applied { font-size: 11px; color: #444; }

.card-actions {
    display: flex;
    gap: 10px;
}

.btn-small {
    padding: 6px 12px;
    font-size: 11px;
    background: #222;
    color: #fff;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 700;
    transition: 0.2s;
}

.btn-small:hover {
    background: #f0a500;
    color: #000;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    border: 2px dashed #1a1a1a;
    border-radius: 12px;
}
</style>
</head>
<body style="background: #000; color: #fff;">

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s; padding: 30px;">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="page-header">
        <div class="welcome">
            <h2 style="margin:0; font-weight:800;">📊 Loan Portfolio</h2>
            <p style="color: #666; margin-top:5px;">Track and manage your operational capital.</p>
        </div>
        <div class="btn-group-header">
            <a href="print-loan-history.php" class="btn-action btn-report">📜 Full Report</a>
            <a href="apply-loan.php" class="btn-action">＋ New Request</a>
        </div>
    </div>

    <div class="filters">
        <?php 
        $statuses = ['all', 'pending', 'approved', 'rejected', 'completed'];
        foreach($statuses as $s): ?>
            <a href="?filter=<?php echo $s; ?>" class="filter-btn <?php echo $filter == $s ? 'active' : ''; ?>">
                <?php echo ucfirst($s); ?> Loans
            </a>
        <?php endforeach; ?>
    </div>

    <div class="loans-container">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($loan = $result->fetch_assoc()): ?>
                <?php
                $total = $loan['total_amount'];
                $remaining = $loan['remaining_balance'];
                $paid = $total - $remaining;
                $progress = ($total > 0) ? ($paid / $total) * 100 : 0;
                ?>
                <div class="loan-card">
                    <div class="loan-header">
                        <div class="loan-id">REF: #LN-<?php echo str_pad($loan['id'], 5, '0', STR_PAD_LEFT); ?></div>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <?php if($loan['due_date'] && strtotime(date('Y-m-d')) > strtotime($loan['due_date']) && $loan['status']!='completed'): ?>
                                <span class="status-badge" style="background:rgba(239,68,68,0.2); color:#ef4444; border:1px solid #ef4444;">Overdue</span>
                            <?php endif; ?>
                            <span class="status-badge status-<?php echo $loan['status']; ?>">
                                <?php echo $loan['status']; ?>
                            </span>
                        </div>
                    </div>

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

                    <?php if (!empty($loan['purpose'])): ?>
                        <div style="background:rgba(255,255,255,0.02); padding:12px; border-radius:6px; margin-bottom:15px; border:1px solid #111;">
                            <div class="detail-label" style="margin-bottom:8px;">Operational Purpose</div>
                            <div style="font-size:13px; color:#aaa; line-height:1.5;"><?php echo htmlspecialchars($loan['purpose']); ?></div>
                        </div>
                    <?php endif; ?>

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

    <script>
    document.getElementById('sidebarToggle').addEventListener('click',()=>{
        document.getElementById('sidebar').classList.toggle('active');
    });
    </script>

</main>
</body>
</html>