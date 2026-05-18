<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

// Debugging & Error Handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

$user = getCurrentUser();
$role = "admin";

function fetchValue($query, $default = 0) {
    global $conn;
    $res = $conn->query($query);
    if (!$res) return $default;
    $row = $res->fetch_assoc();
    return $row ? ($row['total'] ?? $default) : $default;
}

//Macro-Level Statistics
$totalUsers       = fetchValue("SELECT COUNT(*) AS total FROM users");
$totalVolume      = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE status IN ('approved', 'completed')");
$totalRecovered   = fetchValue("SELECT SUM(amount_paid) AS total FROM repayments");
$pendingApprovals = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE status='pending'");

//Loan Portfolio Counts (For Doughnut Chart)
$composition = [
    'approved'  => fetchValue("SELECT COUNT(*) as total FROM loans WHERE status='approved'"),
    'completed' => fetchValue("SELECT COUNT(*) as total FROM loans WHERE status='completed'"),
    'pending'   => $pendingApprovals,
    'rejected'  => fetchValue("SELECT COUNT(*) as total FROM loans WHERE status='rejected'")
];

//Financial Volume Breakdown (Money tied/correspondant to each status)
$volumes = [
    'pending'   => fetchValue("SELECT SUM(total_amount) as total FROM loans WHERE status='pending'"),
    'approved'  => fetchValue("SELECT SUM(total_amount) as total FROM loans WHERE status='approved'"),
    'completed' => fetchValue("SELECT SUM(total_amount) as total FROM loans WHERE status='completed'"),
    'rejected'  => fetchValue("SELECT SUM(total_amount) as total FROM loans WHERE status='rejected'")
];

//6-Month Financial Liquidity Trends 
$trends = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i month"));
    $label = date('M Y', strtotime($month . '-01'));
    
    $disbursed = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE status IN ('approved', 'completed') AND DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $recovered = fetchValue("SELECT SUM(amount_paid) AS total FROM repayments WHERE DATE_FORMAT(payment_date,'%Y-%m')='$month'");
    
    $trends[] = ['label' => $label, 'disbursed' => $disbursed, 'recovered' => $recovered];
}

//Audit Feed 
$recentActivity = $conn->query("SELECT a.*, u.full_name FROM activity_logs a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 8");

$pageTitle = "System Command Center";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="welcome" style="margin-bottom: 30px;">
            <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">System Intelligence</h2>
            <p style="color: #444; font-size: 14px;">Master oversight of capital deployment and personnel activity logs.</p>
        </div>

        <div class="admin-stats-grid">
            <div class="stat-card"><div class="label">Total Personnel</div><div class="value"><?php echo $totalUsers; ?></div></div>
            <div class="stat-card" style="border-top-color: #3b82f6;"><div class="label">Capital Deployed</div><div class="value">KES <?php echo number_format($totalVolume); ?></div></div>
            <div class="stat-card" style="border-top-color: #22c55e;"><div class="label">Recovery Total</div><div class="value">KES <?php echo number_format($totalRecovered); ?></div></div>
            <div class="stat-card" style="border-top-color: #ef4444;"><div class="label">Pending Queue</div><div class="value"><?php echo $pendingApprovals; ?></div></div>
        </div>

        <div class="charts-row">
            <div class="chart-box">
                <h3 style="font-size: 11px; color: var(--gold); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px;">Liquidity Trend (6 Months)</h3>
                <div class="chart-container"><canvas id="trendChart"></canvas></div>
            </div>
            <div class="chart-box">
                <h3 style="font-size: 11px; color: var(--gold); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px;">Portfolio Mix</h3>
                <div class="chart-container"><canvas id="compChart"></canvas></div>
            </div>
        </div>

        <div class="portfolio-details-grid">
            <div class="detail-card border-pending">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h4>Pending Review</h4>
                    <span class="detail-count-tag"><?php echo $composition['pending']; ?></span>
                </div>
                <div class="vol-label">Volume in Pipeline:</div>
                <div class="vol-value">KES <?php echo number_format($volumes['pending'], 2); ?></div>
                <div class="detail-desc">Value of requested applications currently under risk assessment.</div>
            </div>

            <div class="detail-card border-approved">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h4>Active / Approved</h4>
                    <span class="detail-count-tag"><?php echo $composition['approved']; ?></span>
                </div>
                <div class="vol-label">Active Exposure:</div>
                <div class="vol-value" style="color: var(--gold);">KES <?php echo number_format($volumes['approved'], 2); ?></div>
                <div class="detail-desc">Total liquid assets currently deployed and awaiting repayment.</div>
            </div>

            <div class="detail-card border-completed">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h4>Fully Recovered</h4>
                    <span class="detail-count-tag"><?php echo $composition['completed']; ?></span>
                </div>
                <div class="vol-label">Recovered Capital:</div>
                <div class="vol-value" style="color: #22c55e;">KES <?php echo number_format($volumes['completed'], 2); ?></div>
                <div class="detail-desc">Successful cycles where principal and interest have been cleared.</div>
            </div>

            <div class="detail-card border-rejected">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h4>Denied / Rejected</h4>
                    <span class="detail-count-tag"><?php echo $composition['rejected']; ?></span>
                </div>
                <div class="vol-label">Mitigated Risk:</div>
                <div class="vol-value" style="color: #ef4444;">KES <?php echo number_format($volumes['rejected'], 2); ?></div>
                <div class="detail-desc">Value of applications blocked by system security or credit policy.</div>
            </div>
        </div>

        <div class="activity-feed">
            <h3 style="font-size: 11px; color: var(--gold); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px;">Live Audit Stream</h3>
            <?php if ($recentActivity && $recentActivity->num_rows > 0): ?>
                <?php while($log = $recentActivity->fetch_assoc()): ?>
                <div class="activity-item">
                    <span><strong style="color: #fff;"><?php echo htmlspecialchars($log['full_name']); ?></strong> <span style="color: #555; font-family: monospace;">> <?php echo htmlspecialchars($log['action']); ?></span></span>
                    <span style="color: #333; font-size: 11px;"><?php echo date('H:i | d M', strtotime($log['created_at'])); ?></span>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #222; font-size: 12px;">NO RECENT ACTIVITY RECORDED.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const chartOptions = {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#444', font: { size: 10, weight: 'bold' } } } },
            scales: {
                y: { grid: { color: '#111' }, ticks: { color: '#444', font: { family: 'monospace' } } },
                x: { grid: { display: false }, ticks: { color: '#444', font: { family: 'monospace' } } }
            }
        };

        const trendData = <?php echo json_encode($trends); ?>;
        new Chart(document.getElementById('trendChart'), {
            type: 'bar',
            data: {
                labels: trendData.map(d => d.label),
                datasets: [
                    { label: 'Disbursed', data: trendData.map(d => d.disbursed), backgroundColor: '#f0a500', borderRadius: 4 },
                    { label: 'Recovered', data: trendData.map(d => d.recovered), type: 'line', borderColor: '#22c55e', borderWidth: 2, tension: 0.4 }
                ]
            },
            options: chartOptions
        });

        new Chart(document.getElementById('compChart'), {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Completed', 'Pending', 'Rejected'],
                datasets: [{
                    data: [<?php echo implode(',', $composition); ?>],
                    backgroundColor: ['#f0a500', '#22c55e', '#3b82f6', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: { maintainAspectRatio: false, cutout: '80%', plugins: { legend: { position: 'bottom' } } }
        });

        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('dashboardMain');
            sidebar.classList.toggle('active');
            main.style.marginLeft = sidebar.classList.contains('active') ? '240px' : '0';
        });
    </script>
</body>
</html>