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

// --------------------
// Stats
// --------------------
function fetchValue($query, $default=0){
    global $conn;
    $res = $conn->query($query);
    if(!$res) return $default;
    $row = $res->fetch_assoc();
    return $row ? ($row['total'] ?? $default) : $default;
}

$totalLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=".intval($user['id']));
$approvedLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=".intval($user['id'])." AND status='approved'");
$pendingLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=".intval($user['id'])." AND status='pending'");
$totalDisbursed = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE borrower_id=".intval($user['id'])." AND status='approved'");

// --------------------
// Loan trends (last 6 months)
// --------------------
$loanTrends = [];
for($i=5;$i>=0;$i--){
    $month = date('Y-m', strtotime("-$i month"));
    $amount = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE borrower_id=".intval($user['id'])." AND DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $loanTrends[] = ['month'=>date('M Y', strtotime($month.'-01')), 'total'=>$amount];
}

// --------------------
// Loan history table
// --------------------
$sqlLoans = "SELECT id, amount, total_amount, status, created_at 
             FROM loans 
             WHERE borrower_id=".intval($user['id'])." 
             ORDER BY created_at DESC
             LIMIT 5";

$loansResult = $conn->query($sqlLoans);
if(!$loansResult){
    die("Loan query failed: " . $conn->error);
}

$pageTitle = "Borrower Dashboard";
$role = "borrower";
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
/* Table search bar styling */
.table-search {
    padding: 10px;
    margin-bottom: 12px;
    width: 100%;
    max-width: 400px;
    border-radius: 8px;
    border: 1px solid #333;
    background: #111;
    color: #fff;
}
.table-search::placeholder {
    color: #888;
}

/* Table styling */
.table-container table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: #111;
    color: #fff;
}
.table-container th, .table-container td {
    padding: 12px;
    border: 1px solid #222;
    text-align: left;
}
.table-container th {
    cursor: pointer;
    background: #222;
}
.table-container tr:hover {
    background: #222;
}

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
    border: 2px solid transparent;
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(240, 165, 0, 0.4);
    border-color: #ffc107;
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

/* View All Link */
.view-all-link {
    text-align: center;
    margin-top: 16px;
}

.view-all-link a {
    color: #f0a500;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: color 0.3s;
}

.view-all-link a:hover {
    color: #ffc107;
    text-decoration: underline;
}

/* Info Alert */
.info-alert {
    background: rgba(59, 130, 246, 0.1);
    border-left: 4px solid #3b82f6;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    color: #ddd;
}

.info-alert strong {
    color: #3b82f6;
}
</style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2>👋 Borrower Dashboard</h2>
    <p style="color: #999;">View your loans and trends.</p>
</div>

<div class="dashboard-grid">
    <div class="card"><div class="card-title">Total Loans</div><div class="card-value"><?php echo $totalLoans; ?></div></div>
    <div class="card"><div class="card-title">Approved Loans</div><div class="card-value"><?php echo $approvedLoans; ?></div></div>
    <div class="card"><div class="card-title">Pending Loans</div><div class="card-value"><?php echo $pendingLoans; ?></div></div>
    <div class="card"><div class="card-title">Total Disbursed</div><div class="card-value">KES <?php echo number_format($totalDisbursed); ?></div></div>
</div>

<!-- Quick Actions Section -->
<div class="quick-actions">
    <h3>⚡ Quick Actions</h3>
    <div class="action-grid">
        <a href="apply-loan.php" class="action-card">
            <div class="action-icon">📝</div>
            <div class="action-title">Apply for Loan</div>
            <div class="action-desc">Submit a new loan application</div>
        </a>
        
        <a href="my-loans.php" class="action-card">
            <div class="action-icon">📊</div>
            <div class="action-title">View My Loans</div>
            <div class="action-desc">Track all your loan applications</div>
        </a>
        
        <a href="my-loans.php?filter=approved" class="action-card">
            <div class="action-icon">💰</div>
            <div class="action-title">Active Loans</div>
            <div class="action-desc">View your approved loans</div>
        </a>
        
        <a href="my-loans.php?filter=pending" class="action-card">
            <div class="action-icon">⏳</div>
            <div class="action-title">Pending Applications</div>
            <div class="action-desc">Check application status</div>
        </a>
    </div>
</div>

<?php if ($pendingLoans > 0): ?>
<div class="info-alert">
    <strong>📌 Notice:</strong> You have <?php echo $pendingLoans; ?> pending loan application(s) awaiting review. 
    <a href="my-loans.php?filter=pending" style="color: #3b82f6; text-decoration: underline;">View pending loans →</a>
</div>
<?php endif; ?>

<h3>My Loan Trends (Last 6 Months)</h3>
<canvas id="loanChart"></canvas>

<div class="table-container">
    <h3>Recent Loan History</h3>
    <input type="text" id="loanSearch" placeholder="Search loans..." class="table-search">
    <table id="loansTable">
        <thead>
            <tr>
                <th onclick="sortTable('loansTable',0)">ID</th>
                <th onclick="sortTable('loansTable',1)">Amount</th>
                <th onclick="sortTable('loansTable',2)">Total Amount</th>
                <th onclick="sortTable('loansTable',3)">Status</th>
                <th onclick="sortTable('loansTable',4)">Created</th>
            </tr>
        </thead>
        <tbody>
        <?php if($loansResult->num_rows > 0): ?>
            <?php while($loan = $loansResult->fetch_assoc()): ?>
            <tr>
                <td><?php echo $loan['id']; ?></td>
                <td>KES <?php echo number_format($loan['amount']); ?></td>
                <td>KES <?php echo number_format($loan['total_amount']); ?></td>
                <td><?php echo strtoupper($loan['status']); ?></td>
                <td><?php echo date('d M Y', strtotime($loan['created_at'])); ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align: center; color: #888; padding: 40px;">
                    No loan applications yet. <a href="apply-loan.php" style="color: #f0a500;">Apply for your first loan →</a>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    
    <?php if($totalLoans > 5): ?>
    <div class="view-all-link">
        <a href="my-loans.php">View All <?php echo $totalLoans; ?> Loans →</a>
    </div>
    <?php endif; ?>
</div>

<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click',()=>{
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('dashboardMain').classList.toggle('shifted');
});

// Chart
const ctx=document.getElementById('loanChart').getContext('2d');
const loanData=<?php echo json_encode($loanTrends); ?>;
new Chart(ctx,{
    type:'line',
    data:{
        labels:loanData.map(d=>d.month),
        datasets:[{
            label:'KES Loaned',
            data:loanData.map(d=>d.total),
            borderColor:'#f0a500',
            backgroundColor:'rgba(240,165,0,0.2)',
            fill:true,
            tension:0.3
        }]
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

// Table search
function tableSearch(inputId,tableId){
    const filter=document.getElementById(inputId).value.toUpperCase();
    const trs=document.getElementById(tableId).tBodies[0].rows;
    for(let tr of trs){
        let show=false;
        for(let td of tr.cells){
            if(td.textContent.toUpperCase().includes(filter)){show=true;break;}
        }
        tr.style.display=show?'':'none';
    }
}
document.getElementById('loanSearch').addEventListener('keyup',()=>tableSearch('loanSearch','loansTable'));

// Table sort
function sortTable(tableId,col){
    const table=document.getElementById(tableId);
    let rows=Array.from(table.tBodies[0].rows);
    let asc=table.getAttribute('data-sort')!=='asc';
    rows.sort((a,b)=>{
        let x=a.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
        let y=b.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
        return (parseFloat(x)>parseFloat(y)?1:-1)*(asc?1:-1);
    });
    rows.forEach(r=>table.tBodies[0].appendChild(r));
    table.setAttribute('data-sort',asc?'asc':'desc');
}
</script>

</main>
</body>
</html>