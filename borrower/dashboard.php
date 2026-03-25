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
// Stats Helper
// --------------------
function fetchValue($query, $default=0){
    global $conn;
    $res = $conn->query($query);
    if(!$res) return $default;
    $row = $res->fetch_assoc();
    return $row ? ($row['total'] ?? $default) : $default;
}

$u_id = intval($user['id']);
$totalLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=$u_id");
$approvedLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=$u_id AND status='approved'");
$pendingLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=$u_id AND status='pending'");
$totalDisbursed = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE borrower_id=$u_id AND status='approved'");

// --------------------
// Loan trends (last 6 months)
// --------------------
$loanTrends = [];
for($i=5;$i>=0;$i--){
    $month = date('Y-m', strtotime("-$i month"));
    $amount = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE borrower_id=$u_id AND DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $loanTrends[] = ['month'=>date('M Y', strtotime($month.'-01')), 'total'=>$amount];
}

// --------------------
// Recent Loan History
// --------------------
$sqlLoans = "SELECT id, amount, total_amount, status, created_at 
             FROM loans 
             WHERE borrower_id=$u_id 
             ORDER BY created_at DESC
             LIMIT 5";
$loansResult = $conn->query($sqlLoans);

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
/* Dashboard Container Fixes */
.dashboard-main { 
    margin-left: 240px; 
    transition: 0.3s; 
    padding: 30px; 
    min-height: 100vh;
}

/* Quick Actions - Pure Gold Glow, No White Border */
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
    border: none; 
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(240, 165, 0, 0.5); /* Gold Glow Only */
}

/* Table Search UI */
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

/* Info Alert Styling */
.info-alert {
    background: rgba(59, 130, 246, 0.1);
    border-left: 4px solid #3b82f6;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    color: #ddd;
}

/* Tactical Table Styles */
.table-container {
    background: #0a0a0a;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #1a1a1a;
}

table { width: 100%; border-collapse: collapse; color: #fff; margin-top: 10px; }
th, td { padding: 12px; border: 1px solid #222; text-align: left; }
th { cursor: pointer; background: #1a1a1a; color: #f0a500; font-size: 11px; text-transform: uppercase; }
tr:hover { background: #111; }

@media (max-width: 992px) {
    .dashboard-main { margin-left: 0 !important; padding-top: 80px !important; }
}
</style>
</head>
<body style="background: #000;">

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="welcome" style="margin-bottom: 25px;">
        <h2 style="color: #fff; margin:0;">👋 Borrower Dashboard</h2>
        <p style="color: #666; margin-top: 5px;">Monitor your capital access and credit trends.</p>
    </div>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div class="card" style="background:#111; padding:20px; border-radius:12px; border-top: 3px solid #f0a500;">
            <div class="card-title" style="color:#555; font-size:12px; text-transform:uppercase;">Total Loans</div>
            <div class="card-value" style="color:#fff; font-size:24px; font-weight:800;"><?php echo $totalLoans; ?></div>
        </div>
        <div class="card" style="background:#111; padding:20px; border-radius:12px; border-top: 3px solid #22c55e;">
            <div class="card-title" style="color:#555; font-size:12px; text-transform:uppercase;">Approved Loans</div>
            <div class="card-value" style="color:#fff; font-size:24px; font-weight:800;"><?php echo $approvedLoans; ?></div>
        </div>
        <div class="card" style="background:#111; padding:20px; border-radius:12px; border-top: 3px solid #eab308;">
            <div class="card-title" style="color:#555; font-size:12px; text-transform:uppercase;">Pending Loans</div>
            <div class="card-value" style="color:#fff; font-size:24px; font-weight:800;"><?php echo $pendingLoans; ?></div>
        </div>
        <div class="card" style="background:#111; padding:20px; border-radius:12px; border-top: 3px solid #3b82f6;">
            <div class="card-title" style="color:#555; font-size:12px; text-transform:uppercase;">Total Disbursed</div>
            <div class="card-value" style="color:#fff; font-size:24px; font-weight:800;">KES <?php echo number_format($totalDisbursed); ?></div>
        </div>
    </div>

    <div class="quick-actions">
        <h3 style="color: #fff; font-size: 16px; margin-bottom: 16px;">⚡ Quick Actions</h3>
        <div class="action-grid">
            <a href="apply-loan.php" class="action-card">
                <div class="action-icon">📝</div>
                <div class="action-title">Apply for Loan</div>
                <div class="action-desc">Submit a new request</div>
            </a>
            <a href="my-loans.php" class="action-card">
                <div class="action-icon">📊</div>
                <div class="action-title">View My Loans</div>
                <div class="action-desc">Track all applications</div>
            </a>
            <a href="my-loans.php?filter=approved" class="action-card">
                <div class="action-icon">💰</div>
                <div class="action-title">Active Loans</div>
                <div class="action-desc">Review approved credits</div>
            </a>
            <a href="my-loans.php?filter=pending" class="action-card">
                <div class="action-icon">⏳</div>
                <div class="action-title">Pending Status</div>
                <div class="action-desc">Check review progress</div>
            </a>
        </div>
    </div>

    <?php if ($pendingLoans > 0): ?>
    <div class="info-alert">
        <strong>📌 Notice:</strong> You have <?php echo $pendingLoans; ?> pending application(s) awaiting review. 
        <a href="my-loans.php?filter=pending" style="color: #3b82f6; text-decoration: underline; font-weight:bold; margin-left:10px;">View status →</a>
    </div>
    <?php endif; ?>

    <div style="background: #0a0a0a; padding: 25px; border-radius: 12px; border: 1px solid #1a1a1a; margin-bottom: 30px;">
        <h3 style="margin-top:0; color:#f0a500; font-size:14px; text-transform:uppercase;">My Loan Trends (6 Months)</h3>
        <div style="height: 350px;">
            <canvas id="loanChart"></canvas>
        </div>
    </div>

    <div class="table-container">
        <h3 style="color:#fff; margin-bottom:15px;">Recent Loan History</h3>
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
            <?php if($loansResult && $loansResult->num_rows > 0): ?>
                <?php while($loan = $loansResult->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $loan['id']; ?></td>
                    <td style="font-weight:700;">KES <?php echo number_format($loan['amount']); ?></td>
                    <td style="color:#22c55e;">KES <?php echo number_format($loan['total_amount']); ?></td>
                    <td><span style="background:#1a1a1a; padding:4px 8px; border-radius:4px; font-size:10px; font-weight:800;"><?php echo strtoupper($loan['status']); ?></span></td>
                    <td style="color:#666;"><?php echo date('d M Y', strtotime($loan['created_at'])); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #444; padding: 40px;">
                        No loan applications yet. <a href="apply-loan.php" style="color: #f0a500;">Apply for your first loan →</a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        
        <?php if($totalLoans > 5): ?>
        <div style="text-align: center; margin-top: 20px;">
            <a href="my-loans.php" style="color:#f0a500; text-decoration:none; font-weight:700; font-size:13px;">VIEW ALL <?php echo $totalLoans; ?> LOANS →</a>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Sidebar Toggle
    document.getElementById('sidebarToggle').addEventListener('click',()=>{
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Trend Chart Initialization
    const ctx = document.getElementById('loanChart').getContext('2d');
    const loanData = <?php echo json_encode($loanTrends); ?>;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: loanData.map(d => d.month),
            datasets: [{
                label: 'KES Loaned',
                data: loanData.map(d => d.total),
                borderColor: '#f0a500',
                backgroundColor: 'rgba(240,165,0,0.1)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#fff' } } },
            scales: {
                x: { ticks: { color: '#666' }, grid: { color: '#111' } },
                y: { ticks: { color: '#666' }, grid: { color: '#111' } }
            }
        }
    });

    // Original Table Search Logic
    function tableSearch(inputId, tableId){
        const filter = document.getElementById(inputId).value.toUpperCase();
        const trs = document.getElementById(tableId).tBodies[0].rows;
        for(let tr of trs){
            let show = false;
            for(let td of tr.cells){
                if(td.textContent.toUpperCase().includes(filter)){ show = true; break; }
            }
            tr.style.display = show ? '' : 'none';
        }
    }
    document.getElementById('loanSearch').addEventListener('keyup', () => tableSearch('loanSearch','loansTable'));

    // Original Table Sort Logic
    function sortTable(tableId, col){
        const table = document.getElementById(tableId);
        let rows = Array.from(table.tBodies[0].rows);
        let asc = table.getAttribute('data-sort') !== 'asc';
        rows.sort((a,b) => {
            let x = a.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
            let y = b.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
            if(!isNaN(parseFloat(x))) return (parseFloat(x) - parseFloat(y)) * (asc ? 1 : -1);
            return a.cells[col].innerText.localeCompare(b.cells[col].innerText) * (asc ? 1 : -1);
        });
        rows.forEach(r => table.tBodies[0].appendChild(r));
        table.setAttribute('data-sort', asc ? 'asc' : 'desc');
    }
    </script>
</main>
</body>
</html>