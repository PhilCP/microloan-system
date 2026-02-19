<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';
$user = getCurrentUser();

// Stats
function fetchValue($query, $default=0){
    global $conn;
    $res = $conn->query($query);
    if(!$res) return $default;
    $row = $res->fetch_assoc();
    return $row ? ($row['total'] ?? $default) : $default;
}

$totalLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE approved_by=".intval($user['id'])." OR approved_by IS NULL");
$pendingLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE status='pending'");
$approvedLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE approved_by=".intval($user['id'])." AND status='approved'");
$totalDisbursed = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE approved_by=".intval($user['id'])." AND status='approved'");

// Loan trends (last 6 months)
$loanTrends = [];
for($i=5;$i>=0;$i--){
    $month=date('Y-m',strtotime("-$i month"));
    $approved = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE approved_by=".intval($user['id'])." AND status='approved' AND DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $pending = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE status='pending' AND DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $loanTrends[]=['month'=>date('M Y',strtotime($month.'-01')),'approved'=>$approved,'pending'=>$pending];
}

// Recent loans
$loans = $conn->query("SELECT l.id, u.full_name AS borrower, l.total_amount, l.status, l.created_at
                       FROM loans l
                       JOIN users u ON l.borrower_id = u.id
                       WHERE l.approved_by = " . intval($user['id']) . " 
                       OR (l.status = 'pending' AND l.approved_by IS NULL)
                       ORDER BY l.created_at DESC
                       LIMIT 5");

$pageTitle="Officer Dashboard";
$role="officer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Quick Actions Section */
.quick-actions {
    margin: 30px 0;
}

.quick-actions h3 {
    margin-bottom: 16px;
    color: #fff;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.action-card {
    background: linear-gradient(135deg, #f0a500 0%, #ff8c00 100%);
    padding: 24px;
    border-radius: 12px;
    text-decoration: none;
    color: #000;
    transition: all 0.3s;
    display: block;
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(240, 165, 0, 0.4);
}

.action-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.action-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 8px;
}

.action-desc {
    font-size: 14px;
    opacity: 0.9;
}

.view-all-link {
    text-align: center;
    margin-top: 16px;
}

.view-all-link a {
    color: #f0a500;
    text-decoration: none;
    font-weight: 600;
}
</style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2>👔 Officer Dashboard</h2>
    <p style="color:#999;">Manage loan applications and repayments</p>
</div>

<div class="dashboard-grid">
    <div class="card"><div class="card-title">Total Loans</div><div class="card-value"><?php echo $totalLoans; ?></div></div>
    <div class="card"><div class="card-title">Approved</div><div class="card-value"><?php echo $approvedLoans; ?></div></div>
    <div class="card"><div class="card-title">Pending</div><div class="card-value"><?php echo $pendingLoans; ?></div></div>
    <div class="card"><div class="card-title">Total Disbursed</div><div class="card-value">KES <?php echo number_format($totalDisbursed); ?></div></div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <h3>⚡ Quick Actions</h3>
    <div class="action-grid">
        <a href="pending-loans.php" class="action-card">
            <div class="action-icon">⏳</div>
            <div class="action-title">Pending Applications</div>
            <div class="action-desc">Review and approve loans</div>
        </a>
        
        <a href="approved-loans.php" class="action-card">
            <div class="action-icon">💰</div>
            <div class="action-title">Record Repayment</div>
            <div class="action-desc">Process loan payments</div>
        </a>
        
        <a href="all-loans.php" class="action-card">
            <div class="action-icon">📊</div>
            <div class="action-title">All Loans</div>
            <div class="action-desc">View complete loan history</div>
        </a>
        
        <a href="reports.php" class="action-card">
            <div class="action-icon">📈</div>
            <div class="action-title">Reports</div>
            <div class="action-desc">View analytics & statistics</div>
        </a>
    </div>
</div>

<h3>Loan Trends (Last 6 Months)</h3>
<canvas id="loanChart"></canvas>

<div class="table-container">
    <h3>Recent Loans</h3>
    <table id="loansTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Borrower</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php while($l=$loans->fetch_assoc()): ?>
           <tr>
    <td><?php echo $l['id']; ?></td>
    <td><?php echo htmlspecialchars($l['borrower']); ?></td>
    <td>KES <?php echo number_format($l['total_amount']); ?></td>
    <td><?php echo strtoupper($l['status']); ?></td>
    <td><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
</tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    
    <div class="view-all-link">
        <a href="all-loans.php">View All Loans →</a>
    </div>
</div>

<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click',()=>{
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('dashboardMain').classList.toggle('shifted');
});

// Loan Chart
const ctx=document.getElementById('loanChart').getContext('2d');
const loanData=<?php echo json_encode($loanTrends); ?>;
new Chart(ctx,{
    type:'bar',
    data:{
        labels:loanData.map(d=>d.month),
        datasets:[
            {label:'Approved (KES)', data:loanData.map(d=>d.approved), backgroundColor:'#22c55e'},
            {label:'Pending', data:loanData.map(d=>d.pending), backgroundColor:'#eab308'}
        ]
    },
    options:{
        responsive:true,
        plugins:{legend:{labels:{color:'#fff'}}},
        scales:{
            x:{ticks:{color:'#fff'},grid:{color:'#222'}},
            y:{ticks:{color:'#fff'},grid:{color:'#222'}}
        }
    }
});
</script>

</main>
</body>
</html>