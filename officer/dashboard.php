<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';
$user = getCurrentUser();

$oid = intval($user['id']);

// Stats helper ,supports optional prepared statement params
function fetchValue($query, $params = [], $types = '') {
    global $conn;
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($query);
    }
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return $row ? ($row['total'] ?? 0) : 0;
}

// All stats scoped to this officer via assigned_officer_id / approved_by
$totalLoans = fetchValue(
    "SELECT COUNT(*) AS total FROM loans WHERE assigned_officer_id = ?",
    [$oid], 'i'
);
$pendingLoans = fetchValue(
    "SELECT COUNT(*) AS total FROM loans WHERE status = 'pending' AND assigned_officer_id = ?",
    [$oid], 'i'
);
$approvedLoans = fetchValue(
    "SELECT COUNT(*) AS total FROM loans WHERE approved_by = ? AND status = 'approved'",
    [$oid], 'i'
);
$totalDisbursed = fetchValue(
    "SELECT COALESCE(SUM(total_amount), 0) AS total FROM loans WHERE approved_by = ? AND status = 'approved'",
    [$oid], 'i'
);

// Loan trends, last 6 months, scoped to this officer
$loanTrends = [];
for ($i = 5; $i >= 0; $i--) {
    $month    = date('Y-m', strtotime("-$i month"));
    $approved = fetchValue(
        "SELECT COALESCE(SUM(total_amount), 0) AS total FROM loans WHERE approved_by = ? AND status = 'approved' AND DATE_FORMAT(created_at,'%Y-%m') = ?",
        [$oid, $month], 'is'
    );
    $pending = fetchValue(
        "SELECT COUNT(*) AS total FROM loans WHERE assigned_officer_id = ? AND status = 'pending' AND DATE_FORMAT(created_at,'%Y-%m') = ?",
        [$oid, $month], 'is'
    );
    $loanTrends[] = [
        'month'    => date('M Y', strtotime($month . '-01')),
        'approved' => $approved,
        'pending'  => $pending,
    ];
}

// Recent loans,scoped to this officer
$recentStmt = $conn->prepare(
    "SELECT l.id, u.full_name AS borrower, l.total_amount, l.status, l.created_at
     FROM loans l
     JOIN users u ON l.borrower_id = u.id
     WHERE l.assigned_officer_id = ?
     ORDER BY l.created_at DESC
     LIMIT 5"
);
$recentStmt->bind_param('i', $oid);
$recentStmt->execute();
$loans = $recentStmt->get_result();

$pageTitle = "Officer Dashboard";
$role      = "officer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/officer-dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body style="background: #000; color: #fff;">

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="welcome" style="margin-bottom: 30px;">
        <h2 style="font-weight: 800;">Officer Command</h2>
        <p style="color:#666;">Operational overview of loan cycles and liquidity.</p>
    </div>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div class="card" style="background:#111; padding:20px; border-radius:12px; border:1px solid #222; border-top: 3px solid #f0a500;">
            <div class="card-title" style="font-size:11px; color:#555; text-transform:uppercase;">Total Assigned</div>
            <div class="card-value" style="font-size:24px; font-weight:800;"><?php echo $totalLoans; ?></div>
        </div>
        <div class="card" style="background:#111; padding:20px; border-radius:12px; border:1px solid #222; border-top: 3px solid #22c55e;">
            <div class="card-title" style="font-size:11px; color:#555; text-transform:uppercase;">Approved</div>
            <div class="card-value" style="font-size:24px; font-weight:800;"><?php echo $approvedLoans; ?></div>
        </div>
        <div class="card" style="background:#111; padding:20px; border-radius:12px; border:1px solid #222; border-top: 3px solid #eab308;">
            <div class="card-title" style="font-size:11px; color:#555; text-transform:uppercase;">Pending</div>
            <div class="card-value" style="font-size:24px; font-weight:800;"><?php echo $pendingLoans; ?></div>
        </div>
        <div class="card" style="background:#111; padding:20px; border-radius:12px; border:1px solid #222; border-top: 3px solid #3b82f6;">
            <div class="card-title" style="font-size:11px; color:#555; text-transform:uppercase;">Disbursed</div>
            <div class="card-value" style="font-size:24px; font-weight:800;">KES <?php echo number_format($totalDisbursed); ?></div>
        </div>
    </div>

    <div class="quick-actions">
        <h3 style="font-size: 14px; color: #f0a500; text-transform: uppercase; margin-top: 40px; letter-spacing: 1px;">Actions</h3>
        <div class="action-grid">
            <a href="pending-loans.php" class="action-card">
                <div class="action-title">Review Pending</div>
                <div class="action-desc">Verify and process applications</div>
            </a>
            <a href="approved-loans.php" class="action-card">
                <div class="action-title">Record Payment</div>
                <div class="action-desc">Update loan repayment logs</div>
            </a>
            <a href="all-loans.php" class="action-card">
                <div class="action-title">Loan Registry</div>
                <div class="action-desc">Browse complete loan history</div>
            </a>
        </div>
    </div>

    <div class="chart-container">
        <h3 style="color: #666; margin-top: 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Volume Trend (6 Months)</h3>
        <canvas id="loanChart"></canvas>
    </div>

    <div class="table-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; font-size:16px;">Recent Transactions</h3>
            <a href="all-loans.php" style="color:#f0a500; text-decoration:none; font-size:13px; font-weight:600;">View Full History →</a>
        </div>
        <table>
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
            <?php if ($loans->num_rows > 0): ?>
                <?php while ($l = $loans->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $l['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($l['borrower']); ?></strong></td>
                        <td style="color: #22c55e; font-weight:700;">KES <?php echo number_format($l['total_amount']); ?></td>
                        <td><span style="font-size:10px; font-weight:900; background:rgba(255,255,255,0.05); padding:4px 8px; border-radius:4px;"><?php echo strtoupper($l['status']); ?></span></td>
                        <td style="color:#666; font-size:13px;"><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; color:#555; padding: 30px;">No loans assigned yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

const ctx      = document.getElementById('loanChart').getContext('2d');
const loanData = <?php echo json_encode($loanTrends); ?>;

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: loanData.map(d => d.month),
        datasets: [
            {
                label: 'Approved (KES)',
                data: loanData.map(d => d.approved),
                backgroundColor: '#f0a500',
                borderRadius: 4
            },
            {
                label: 'Pending Qty',
                data: loanData.map(d => d.pending),
                backgroundColor: '#333',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#666', font: { size: 10 } } }
        },
        scales: {
            x: { ticks: { color: '#444' }, grid: { display: false } },
            y: { ticks: { color: '#444' }, grid: { color: '#111' } }
        }
    }
});
</script>

</body>
</html>