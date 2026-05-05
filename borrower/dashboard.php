<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
if(!$user){ die("User not found. Check session."); }

function fetchValue($query, $default=0){
    global $conn;
    $res = $conn->query($query);
    if(!$res) return $default;
    $row = $res->fetch_assoc();
    return $row ? ($row['total'] ?? $default) : $default;
}

$u_id = intval($user['id']);

//Stats 
$totalLoans     = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=$u_id");
$approvedLoans  = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=$u_id AND status='approved'");
$pendingLoans   = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE borrower_id=$u_id AND status='pending'");
$totalDisbursed = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE borrower_id=$u_id AND status='approved'");

//Active loan (for repayment progress card) 
$activeStmt = $conn->prepare(
    "SELECT l.*,
            COALESCE((SELECT SUM(amount_paid) FROM repayments WHERE loan_id = l.id), 0) AS total_paid,
            DATE_ADD(DATE(l.approval_date), INTERVAL l.duration_months MONTH) AS end_date
     FROM loans l
     WHERE l.borrower_id = ? AND l.status = 'approved' AND l.remaining_balance > 0
     ORDER BY l.created_at DESC LIMIT 1"
);
$activeStmt->bind_param("i", $u_id);
$activeStmt->execute();
$activeLoan = $activeStmt->get_result()->fetch_assoc();

//Overdue loans 
$overdueStmt = $conn->prepare(
    "SELECT id, remaining_balance, duration_months, approval_date, overdue_penalty_rate
     FROM loans
     WHERE borrower_id = ? AND status = 'approved' AND remaining_balance > 0
       AND approval_date IS NOT NULL
       AND DATE_ADD(DATE(approval_date), INTERVAL duration_months MONTH) < CURDATE()"
);
$overdueStmt->bind_param("i", $u_id);
$overdueStmt->execute();
$overdueLoans = $overdueStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Loan trends (last 6 months)
$loanTrends = [];
for($i=5;$i>=0;$i--){
    $month  = date('Y-m', strtotime("-$i month"));
    $amount = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE borrower_id=$u_id AND DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $loanTrends[] = ['month'=>date('M Y', strtotime($month.'-01')), 'total'=>$amount];
}

//  Recent Loan History
$sqlLoans    = "SELECT id, amount, total_amount, status, created_at FROM loans WHERE borrower_id=$u_id ORDER BY created_at DESC LIMIT 5";
$loansResult = $conn->query($sqlLoans);

$pageTitle = "Borrower Dashboard";
$role      = "borrower";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/borrower-dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body style="background: #000;">

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="welcome" style="margin-bottom: 25px;">
        <h2 style="color: #fff; margin:0;">Borrower Dashboard</h2>
        <p style="color: #666; margin-top: 5px;">Monitor your capital access and current loans.</p>
    </div>

    <?php foreach ($overdueLoans as $ol):
        $daysOverdue   = (int)((time() - strtotime($ol['approval_date'])) / 86400) - ($ol['duration_months'] * 30);
        $monthsOverdue = max(1, (int)ceil($daysOverdue / 30));
        $penalty       = round($ol['remaining_balance'] * ($ol['overdue_penalty_rate'] / 100) * $monthsOverdue, 2);
    ?>
    <div class="overdue-banner">
        <h4>⚠ Overdue Loan — #<?php echo $ol['id']; ?></h4>
        <div class="ob-row">
            <div>
                <div class="ob-detail">Outstanding balance: <strong>KES <?php echo number_format($ol['remaining_balance'], 2); ?></strong></div>
                <div class="ob-detail"><?php echo $daysOverdue; ?> days overdue</div>
            </div>
            <div class="ob-penalty">
                Penalty accrued: KES <?php echo number_format($penalty, 2); ?>
                <div style="font-size:11px;font-weight:400;color:#f87171;">
                    <?php echo $ol['overdue_penalty_rate']; ?>%/mo × <?php echo $monthsOverdue; ?> month<?php echo $monthsOverdue > 1 ? 's' : ''; ?>
                </div>
            </div>
            <a href="repay-loan.php?id=<?php echo $ol['id']; ?>" class="ob-action">Pay Now →</a>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
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

    <?php if ($activeLoan):
        $totalPaid      = (float)$activeLoan['total_paid'];
        $balance        = (float)$activeLoan['remaining_balance'];
        $totalContract  = (float)$activeLoan['total_amount'];
        $progress       = $totalContract > 0 ? min(100, ($totalPaid / $totalContract) * 100) : 0;
        $monthly        = $totalContract / $activeLoan['duration_months'];
        $endDate        = $activeLoan['end_date'] ?? date('Y-m-d', strtotime('+' . $activeLoan['duration_months'] . ' months', strtotime($activeLoan['created_at'])));
        $daysLeft       = (int)ceil((strtotime($endDate) - time()) / 86400);
        $dayClass       = $daysLeft > 30 ? 'days-ok' : ($daysLeft > 7 ? 'days-warning' : 'days-urgent');
        $dayLabel       = $daysLeft > 0 ? "{$daysLeft} days remaining" : abs($daysLeft) . " days overdue";
    ?>
    <div class="active-loan-card">
        <div class="alc-header">
            <div class="alc-title"> Active Loan & Repayment Progress</div>
            <div class="alc-id">Loan #<?php echo $activeLoan['id']; ?> &nbsp;|&nbsp; <?php echo $activeLoan['duration_months']; ?> months</div>
        </div>

        <div class="alc-stats">
            <div class="alc-stat">
                <div class="alc-stat-label">Total Contract</div>
                <div class="alc-stat-value">KES <?php echo number_format($totalContract, 2); ?></div>
            </div>
            <div class="alc-stat">
                <div class="alc-stat-label">Amount Paid</div>
                <div class="alc-stat-value" style="color:#22c55e;">KES <?php echo number_format($totalPaid, 2); ?></div>
            </div>
            <div class="alc-stat">
                <div class="alc-stat-label">Balance Remaining</div>
                <div class="alc-stat-value" style="color:#f0a500;">KES <?php echo number_format($balance, 2); ?></div>
            </div>
            <div class="alc-stat">
                <div class="alc-stat-label">End Date</div>
                <div class="alc-stat-value" style="font-size:14px;"><?php echo date('d M Y', strtotime($endDate)); ?></div>
            </div>
        </div>

        <div class="alc-progress-wrap">
            <div class="alc-progress-label">
                <span>Repaid: <?php echo round($progress, 1); ?>%</span>
                <span>Remaining: <?php echo round(100 - $progress, 1); ?>%</span>
            </div>
            <div class="alc-progress-track">
                <div class="alc-progress-fill" style="width:<?php echo $progress; ?>%"></div>
            </div>
        </div>

        <span class="days-pill <?php echo $dayClass; ?>">
             <?php echo $dayLabel; ?>
        </span>

        <div class="alc-instalment">
            <span>Monthly instalment</span>
            <strong>KES <?php echo number_format($monthly, 2); ?></strong>
        </div>
        <a href="repay-loan.php?id=<?php echo $activeLoan['id']; ?>" class="alc-pay-btn">
            Make a Payment →
            </a>
    </div>
    <?php endif; ?>

    <div class="quick-actions">
        <h3 style="color: #fff; font-size: 16px; margin-bottom: 16px;">Quick Actions</h3>
        <div class="action-grid">
            <a href="apply-loan.php" class="action-card">
                <div class="action-title">Apply for Loan</div>
                <div class="action-desc">Submit a new request</div>
            </a>
            <a href="my-loans.php" class="action-card">
                <div class="action-title">View My Loans</div>
                <div class="action-desc">Track all applications</div>
            </a>
            <a href="my-loans.php?filter=approved" class="action-card">
                <div class="action-title">Active Loans</div>
                <div class="action-desc">Review approved credits</div>
            </a>
            <a href="my-loans.php?filter=pending" class="action-card">
                <div class="action-title">Pending Status</div>
                <div class="action-desc">Check review progress</div>
            </a>
        </div>
    </div>

    <?php if ($pendingLoans > 0): ?>
    <div class="info-alert">
        <strong>Notice:</strong> You have <?php echo $pendingLoans; ?> pending application(s) awaiting review.
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
                <?php while($loan = $loansResult->fetch_assoc()):
                    $statusColors = [
                        'approved'  => '#22c55e',
                        'pending'   => '#eab308',
                        'rejected'  => '#ef4444',
                        'completed' => '#818cf8',
                    ];
                    $sColor = $statusColors[$loan['status']] ?? '#888';
                ?>
                <tr>
                    <td>#<?php echo $loan['id']; ?></td>
                    <td style="font-weight:700;">KES <?php echo number_format($loan['amount']); ?></td>
                    <td style="color:#22c55e;">KES <?php echo number_format($loan['total_amount']); ?></td>
                    <td>
                        <span style="background:<?php echo $sColor; ?>22; color:<?php echo $sColor; ?>;
                                     border:1px solid <?php echo $sColor; ?>55;
                                     padding:3px 9px; border-radius:10px;
                                     font-size:10px; font-weight:800; text-transform:uppercase;">
                            <?php echo $loan['status']; ?>
                        </span>
                    </td>
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
</main>

<script>
document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

// Trend Chart
const ctx      = document.getElementById('loanChart').getContext('2d');
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
            fill: true, tension: 0.3,
            pointBackgroundColor: '#fff'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#fff' } } },
        scales: {
            x: { ticks: { color: '#666' }, grid: { color: '#111' } },
            y: { ticks: { color: '#666' }, grid: { color: '#111' } }
        }
    }
});

function tableSearch(inputId, tableId){
    const filter = document.getElementById(inputId).value.toUpperCase();
    const trs    = document.getElementById(tableId).tBodies[0].rows;
    for(let tr of trs){
        let show = false;
        for(let td of tr.cells){ if(td.textContent.toUpperCase().includes(filter)){ show=true; break; } }
        tr.style.display = show ? '' : 'none';
    }
}
document.getElementById('loanSearch').addEventListener('keyup', () => tableSearch('loanSearch','loansTable'));

function sortTable(tableId, col){
    const table = document.getElementById(tableId);
    let rows = Array.from(table.tBodies[0].rows);
    let asc  = table.getAttribute('data-sort') !== 'asc';
    rows.sort((a,b) => {
        let x = a.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
        let y = b.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
        if(!isNaN(parseFloat(x))) return (parseFloat(x)-parseFloat(y))*(asc?1:-1);
        return a.cells[col].innerText.localeCompare(b.cells[col].innerText)*(asc?1:-1);
    });
    rows.forEach(r => table.tBodies[0].appendChild(r));
    table.setAttribute('data-sort', asc ? 'asc' : 'desc');
}
</script>
</body>
</html>