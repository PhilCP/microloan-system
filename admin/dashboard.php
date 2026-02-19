<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';
$user = getCurrentUser();

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

$totalUsers = fetchValue("SELECT COUNT(*) AS total FROM users");
$totalLoans = fetchValue("SELECT SUM(total_amount) AS total FROM loans");
$pending = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE status='pending'");
$disbursed = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE status='approved'");

// --------------------
// Loan trends (last 6 months)
// --------------------
$loanTrends = [];
for($i=5; $i>=0; $i--){
    $month = date('Y-m', strtotime("-$i month"));
    $approved = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE status='approved' AND DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $pendingAmt = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE status='pending' AND DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $loanTrends[] = ['month'=>date('M Y', strtotime($month.'-01')), 'approved'=>$approved, 'pending'=>$pendingAmt];
}

// --------------------
// Users table
// --------------------
$users = $conn->query("SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC");

// --------------------
// Loans table
// --------------------
$loans = $conn->query("SELECT l.id, u.full_name AS borrower, l.total_amount, l.status, l.created_at 
                       FROM loans l 
                       JOIN users u ON l.borrower_id = u.id 
                       ORDER BY l.created_at DESC");

$pageTitle = "Admin Dashboard";
$role = "admin";
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
</style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2> Admin Dashboard</h2>
    <p style="color: #999;">Manage users, loans, and view trends.</p>
</div>

<div class="dashboard-grid">
    <div class="card"><div class="card-title">Total Users</div><div class="card-value"><?php echo $totalUsers; ?></div></div>
    <div class="card"><div class="card-title">Total Loans</div><div class="card-value">KES <?php echo number_format($totalLoans); ?></div></div>
    <div class="card"><div class="card-title">Pending Approvals</div><div class="card-value"><?php echo $pending; ?></div></div>
    <div class="card"><div class="card-title">Total Disbursed</div><div class="card-value">KES <?php echo number_format($disbursed); ?></div></div>
</div>

<h3>Loan Trends (Approved vs Pending, Last 6 Months)</h3>
<canvas id="loanChart"></canvas>

<!-- Users Table -->
<div class="table-container">
    <h3>All Users</h3>
    <input type="text" id="userSearch" placeholder="Search users..." class="table-search">
    <table id="usersTable">
        <thead>
            <tr>
                <th onclick="sortTable('usersTable',0)">ID</th>
                <th onclick="sortTable('usersTable',1)">Full Name</th>
                <th onclick="sortTable('usersTable',2)">Email</th>
                <th onclick="sortTable('usersTable',3)">Role</th>
                <th onclick="sortTable('usersTable',4)">Created</th>
            </tr>
        </thead>
        <tbody>
            <?php while($u=$users->fetch_assoc()): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo strtoupper($u['role']); ?></td>
                <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Loans Table -->
<div class="table-container">
    <h3>All Loans</h3>
    <input type="text" id="loanSearch" placeholder="Search loans..." class="table-search">
    <table id="loansTable">
        <thead>
            <tr>
                <th onclick="sortTable('loansTable',0)">ID</th>
                <th onclick="sortTable('loansTable',1)">Borrower</th>
                <th onclick="sortTable('loansTable',2)">Amount</th>
                <th onclick="sortTable('loansTable',3)">Status</th>
                <th onclick="sortTable('loansTable',4)">Created</th>
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
            {label:'Approved', data:loanData.map(d=>d.approved), backgroundColor:'#28a745'},
            {label:'Pending', data:loanData.map(d=>d.pending), backgroundColor:'#dc3545'}
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

// Table search
function tableSearch(inputId, tableId){
    const filter = document.getElementById(inputId).value.toUpperCase();
    const trs = document.getElementById(tableId).tBodies[0].rows;
    for(let tr of trs){
        let show=false;
        for(let td of tr.cells){
            if(td.textContent.toUpperCase().includes(filter)){show=true;break;}
        }
        tr.style.display = show ? '' : 'none';
    }
}
document.getElementById('userSearch').addEventListener('keyup',()=>tableSearch('userSearch','usersTable'));
document.getElementById('loanSearch').addEventListener('keyup',()=>tableSearch('loanSearch','loansTable'));

// Table sort
function sortTable(tableId,col){
    const table=document.getElementById(tableId);
    let rows=Array.from(table.tBodies[0].rows);
    let asc=table.getAttribute('data-sort')!=='asc';
    rows.sort((a,b)=>{
        let x=a.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
        let y=b.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
        return (parseFloat(x) > parseFloat(y) ? 1 : -1)*(asc?1:-1);
    });
    rows.forEach(r=>table.tBodies[0].appendChild(r));
    table.setAttribute('data-sort',asc?'asc':'desc');
}
</script>
</main>
</body>
</html>