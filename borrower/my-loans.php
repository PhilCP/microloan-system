<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
$filter = $_GET['filter'] ?? 'all';

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
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.btn-new {
    background: #f0a500;
    color: #000;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-new:hover {
    background: #ffc107;
    transform: translateY(-2px);
}

.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.filter-btn {
    padding: 8px 20px;
    border: 1px solid #333;
    background: #111;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #ddd;
    text-decoration: none;
    transition: all 0.3s;
}

.filter-btn:hover {
    border-color: #f0a500;
    color: #f0a500;
}

.filter-btn.active {
    background: #f0a500;
    color: #000;
    border-color: #f0a500;
}

.loan-card {
    background: #111;
    border: 1px solid #222;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
    transition: all 0.3s;
}

.loan-card:hover {
    border-color: #f0a500;
    transform: translateY(-2px);
}

.loan-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.loan-id {
    font-size: 18px;
    font-weight: 700;
    color: #f0a500;
}

.status-badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending {
    background: rgba(234, 179, 8, 0.2);
    color: #eab308;
}

.status-approved {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.status-rejected {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.status-completed {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.loan-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin: 16px 0;
}

.detail-item {
    padding: 12px;
    background: rgba(240, 165, 0, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(240, 165, 0, 0.1);
}

.detail-label {
    font-size: 12px;
    color: #888;
    margin-bottom: 4px;
}

.detail-value {
    font-size: 16px;
    font-weight: 600;
    color: #ddd;
}

.loan-purpose {
    margin: 16px 0;
    padding: 12px;
    background: rgba(59, 130, 246, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(59, 130, 246, 0.1);
}

.loan-purpose-label {
    font-size: 12px;
    color: #888;
    margin-bottom: 4px;
}

.loan-purpose-text {
    color: #ddd;
    font-size: 14px;
    line-height: 1.6;
}

.loan-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #222;
    font-size: 12px;
    color: #888;
}

.progress-bar {
    margin-top: 12px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #888;
    margin-bottom: 6px;
}

.progress-track {
    background: #222;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    background: linear-gradient(90deg, #f0a500, #ffc107);
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s;
}

.empty-state {
    text-align: center;
    padding: 60px 40px;
    color: #888;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.empty-state h3 {
    color: #ddd;
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .filters {
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    
    .loan-details {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="page-header">
    <div class="welcome">
        <h2>📊 My Loan Applications</h2>
        <p style="color: #999;">View and track all your loan applications</p>
    </div>
    <a href="apply-loan.php" class="btn-new">+ New Application</a>
    <a href="print-loan-history.php" class="btn-new" style="background: #333; color: #fff;">
            📜 Full History Report
        </a>
</div>

<div class="filters">
    <a href="?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
        All Loans
    </a>
    <a href="?filter=pending" class="filter-btn <?php echo $filter == 'pending' ? 'active' : ''; ?>">
        Pending
    </a>
    <a href="?filter=approved" class="filter-btn <?php echo $filter == 'approved' ? 'active' : ''; ?>">
        Approved
    </a>
    <a href="?filter=rejected" class="filter-btn <?php echo $filter == 'rejected' ? 'active' : ''; ?>">
        Rejected
    </a>
    <a href="?filter=completed" class="filter-btn <?php echo $filter == 'completed' ? 'active' : ''; ?>">
        Completed
    </a>
    
</div>

<div class="loans-grid">
    <?php if ($result->num_rows > 0): ?>
        <?php while ($loan = $result->fetch_assoc()): ?>
            <?php
            // Calculate repayment progress
            $total = $loan['total_amount'];
            $remaining = $loan['remaining_balance'];
            $paid = $total - $remaining;
            $progress = ($paid / $total) * 100;
            ?>
            <div class="loan-card">
                <div class="loan-header">
                    <div class="loan-id">Loan #<?php echo $loan['id']; ?></div>
                    <span class="status-badge status-<?php echo $loan['status']; ?>">
                        <?php echo ucfirst($loan['status']); ?>
                    </span>
                </div>

                <div class="loan-details">
                    <div class="detail-item">
                        <div class="detail-label">Loan Amount</div>
                        <div class="detail-value">KES <?php echo number_format($loan['amount'], 2); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Repayment</div>
                        <div class="detail-value">KES <?php echo number_format($loan['total_amount'], 2); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value"><?php echo $loan['duration_months']; ?> Months</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Interest Rate</div>
                        <div class="detail-value"><?php echo $loan['interest_rate']; ?>%</div>
                    </div>
                </div>

                <?php if ($loan['status'] == 'approved'): ?>
                    <div class="progress-bar">
                        <div class="progress-label">
                            <span>Repayment Progress</span>
                            <span><strong>KES <?php echo number_format($remaining, 2); ?></strong> remaining</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($loan['purpose'])): ?>
                    <div class="loan-purpose">
                        <div class="loan-purpose-label">Purpose</div>
                        <div class="loan-purpose-text"><?php echo htmlspecialchars($loan['purpose']); ?></div>
                    </div>
                <?php endif; ?>

                <div class="loan-footer">
                    <span>Applied: <?php echo date('M d, Y', strtotime($loan['created_at'])); ?></span>
                    <?php if ($loan['approved_by_name']): ?>
                        <span>Approved by: <?php echo htmlspecialchars($loan['approved_by_name']); ?></span>
                    <?php endif; ?>
                    <?php if ($loan['status'] == 'approved' && $loan['remaining_balance'] > 0): ?>
    <div style="display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap;">
    <?php if ($loan['status'] == 'approved' && $loan['remaining_balance'] > 0): ?>
        <a href="repay-loan.php?id=<?php echo $loan['id']; ?>" class="btn-new">
            💳 Make Repayment
        </a>
    <?php endif; ?>

    <?php if (in_array($loan['status'], ['approved', 'completed'])): ?>
        <a href="print-statement.php?id=<?php echo $loan['id']; ?>" class="btn-new">📜 View Official Statement</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if($loan['due_date'] && strtotime(date('Y-m-d')) > strtotime($loan['due_date']) && $loan['status']!='completed'): ?>
    <span class="status-badge status-rejected">Overdue</span>
<?php endif; ?>

                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>No Loans Found</h3>
            <p>
                <?php if ($filter == 'all'): ?>
                    You haven't applied for any loans yet.
                <?php else: ?>
                    No <?php echo $filter; ?> loans found.
                <?php endif; ?>
            </p>
            <a href="apply-loan.php" class="btn-new" style="margin-top: 16px; display: inline-block;">Apply for Your First Loan</a>
        </div>
    <?php endif; ?>
</div>

<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click',()=>{
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('dashboardMain').classList.toggle('shifted');
});
</script>

</main>
</body>
</html>