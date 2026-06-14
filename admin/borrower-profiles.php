<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$user = getCurrentUser();
$role = "admin";

// Filter
$search  = $_GET['search'] ?? '';
$risk    = $_GET['risk'] ?? '';
$sort    = $_GET['sort'] ?? 'total_borrowed';
$order   = $_GET['order'] ?? 'DESC';

$allowedSorts  = ['full_name','total_loans','approved','rejected','total_borrowed','outstanding','repayment_rate'];
$allowedOrders = ['ASC','DESC'];
if (!in_array($sort, $allowedSorts))  $sort  = 'total_borrowed';
if (!in_array($order, $allowedOrders)) $order = 'DESC';

// Build borrower stats query
$query = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at,
        u.is_active,
        COUNT(l.id)                                                        AS total_loans,
        SUM(l.status = 'approved')                                         AS approved,
        SUM(l.status = 'completed')                                        AS completed,
        SUM(l.status = 'rejected')                                         AS rejected,
        SUM(l.status = 'pending')                                          AS pending,
        COALESCE(SUM(CASE WHEN l.status IN ('approved','completed') THEN l.total_amount ELSE 0 END), 0) AS total_borrowed,
        COALESCE(SUM(CASE WHEN l.status IN ('approved','completed') THEN l.remaining_balance ELSE 0 END), 0) AS outstanding,
        COALESCE(SUM(r.amount_paid), 0)                                    AS total_repaid
    FROM users u
    LEFT JOIN loans l ON u.id = l.borrower_id
    LEFT JOIN repayments r ON l.id = r.loan_id
    WHERE u.role = 'borrower'
";

$params = [];
$types  = '';

if ($search) {
    $query   .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$query .= " GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at, u.is_active";

// Repayment rate computed column for HAVING filter
$query .= " HAVING 1=1";

if ($risk === 'good') {
    $query .= " AND (total_borrowed = 0 OR (total_repaid / total_borrowed) >= 0.75)
                AND rejected < 2";
} elseif ($risk === 'watch') {
    $query .= " AND ((total_borrowed > 0 AND (total_repaid / total_borrowed) BETWEEN 0.40 AND 0.74)
                OR rejected BETWEEN 1 AND 2)";
} elseif ($risk === 'high') {
    $query .= " AND ((total_borrowed > 0 AND (total_repaid / total_borrowed) < 0.40)
                OR rejected >= 3)";
}

$query .= " ORDER BY $sort $order";

$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$borrowers = $stmt->get_result();

// Summary stats
$totalBorrowers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='borrower'")->fetch_assoc()['c'];
$totalExposure  = $conn->query("SELECT COALESCE(SUM(remaining_balance),0) AS c FROM loans WHERE status='approved'")->fetch_assoc()['c'];
$totalRepaid    = $conn->query("SELECT COALESCE(SUM(amount_paid),0) AS c FROM repayments")->fetch_assoc()['c'];

$pageTitle = "Borrower Profiles";

function riskTag($row) {
    $rate     = $row['total_borrowed'] > 0 ? ($row['total_repaid'] / $row['total_borrowed']) : 1;
    $rejected = intval($row['rejected']);
    if ($rejected >= 3 || ($row['total_borrowed'] > 0 && $rate < 0.40)) {
        return ['label' => 'HIGH RISK', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)'];
    } elseif ($rejected >= 1 || ($row['total_borrowed'] > 0 && $rate < 0.75)) {
        return ['label' => 'WATCH',     'color' => '#eab308', 'bg' => 'rgba(234,179,8,0.1)'];
    }
    return     ['label' => 'GOOD',      'color' => '#22c55e', 'bg' => 'rgba(34,197,94,0.1)'];
}

function sortLink($col, $label, $current, $order) {
    $newOrder = ($current === $col && $order === 'DESC') ? 'ASC' : 'DESC';
    $arrow    = $current === $col ? ($order === 'DESC' ? ' ↓' : ' ↑') : '';
    $params   = array_merge($_GET, ['sort' => $col, 'order' => $newOrder]);
    return '<a href="?' . http_build_query($params) . '" style="color:inherit;text-decoration:none;">' . $label . $arrow . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/users-management.css">
<link rel="stylesheet" href="../assets/css/borrower-profiles.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left:240px; padding:30px;">
<?php include '../includes/dashboard_header.php'; ?>

<div style="margin-bottom:24px;">
    <h2 style="font-weight:900; text-transform:uppercase; letter-spacing:1px; margin:0;">Borrower Profiles</h2>
    <p style="color:#444; font-size:13px; margin-top:4px;">Credit standing, repayment history and risk classification of all borrowers.</p>
</div>

<!-- Summary -->
<div class="bp-summary">
    <div class="bp-card">
        <div class="lbl">Total Borrowers</div>
        <div class="val"><?php echo $totalBorrowers; ?></div>
    </div>
    <div class="bp-card" style="border-top-color:#ef4444;">
        <div class="lbl">Outstanding Exposure</div>
        <div class="val">KES <?php echo number_format($totalExposure); ?></div>
    </div>
    <div class="bp-card" style="border-top-color:#22c55e;">
        <div class="lbl">Total Repaid</div>
        <div class="val">KES <?php echo number_format($totalRepaid); ?></div>
    </div>
    <div class="bp-card" style="border-top-color:#3b82f6;">
        <div class="lbl">Recovery Rate</div>
        <div class="val">
            <?php
            $deployed = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS c FROM loans WHERE status IN ('approved','completed')")->fetch_assoc()['c'];
            echo $deployed > 0 ? number_format(($totalRepaid / $deployed) * 100, 1) . '%' : '—';
            ?>
        </div>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filters">
    <input type="text" name="search" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>">
    <input type="hidden" name="sort"  value="<?php echo htmlspecialchars($sort); ?>">
    <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
    <div class="risk-filter">
        <a href="?<?php echo http_build_query(array_merge($_GET, ['risk'=>''])); ?>"
           class="risk-btn btn-op <?php echo $risk==='' ? 'active-good' : ''; ?>">ALL</a>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['risk'=>'good'])); ?>"
           class="risk-btn btn-op <?php echo $risk==='good' ? 'active-good' : ''; ?>" style="color:#22c55e;">GOOD</a>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['risk'=>'watch'])); ?>"
           class="risk-btn btn-op <?php echo $risk==='watch' ? 'active-watch' : ''; ?>" style="color:#eab308;">WATCH</a>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['risk'=>'high'])); ?>"
           class="risk-btn btn-op <?php echo $risk==='high' ? 'active-high' : ''; ?>" style="color:#ef4444;">HIGH RISK</a>
    </div>
    <button type="submit">Search</button>
    <?php if ($search || $risk): ?>
        <a href="borrower-profiles.php" style="color:#ef4444; border-color:#ef4444;">Clear</a>
    <?php endif; ?>
</form>

<!-- Table -->
<div style="overflow-x:auto;">
<table class="bp-table">
    <thead>
        <tr>
            <th><?php echo sortLink('full_name','Borrower',$sort, $order); ?></th>
            <th>Risk</th>
            <th><?php echo sortLink('total_loans','Loans',$sort, $order); ?></th>
            <th><?php echo sortLink('total_borrowed','Total Borrowed', $sort, $order); ?></th>
            <th><?php echo sortLink('outstanding',  'Outstanding',$sort, $order); ?></th>
            <th><?php echo sortLink('repayment_rate','Repaid',$sort, $order); ?></th>
            <th>Breakdown</th>
            <th style="text-align:right;">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($borrowers->num_rows > 0): ?>
        <?php while ($b = $borrowers->fetch_assoc()):
            $tag  = riskTag($b);
            $rate = $b['total_borrowed'] > 0 ? min(($b['total_repaid'] / $b['total_borrowed']) * 100, 100) : 0;
            $rateColor = $rate >= 75 ? '#22c55e' : ($rate >= 40 ? '#eab308' : '#ef4444');
        ?>
        <tr>
            <td>
                <div style="font-weight:800; color:#fff;"><?php echo htmlspecialchars($b['full_name']); ?></div>
                <div style="color:#333; font-size:11px; font-family:monospace;"><?php echo htmlspecialchars($b['email']); ?></div>
                <div style="color:#333; font-size:10px;"><?php echo htmlspecialchars($b['phone']); ?></div>
            </td>
            <td>
                <span class="risk-pill" style="color:<?php echo $tag['color']; ?>; background:<?php echo $tag['bg']; ?>;">
                    <?php echo $tag['label']; ?>
                </span>
            </td>
            <td style="color:#fff; font-weight:800; font-size:18px;"><?php echo $b['total_loans']; ?></td>
            <td style="color:#f0a500; font-weight:700;">KES <?php echo number_format($b['total_borrowed']); ?></td>
            <td style="color:#ef4444; font-weight:700;">
                <?php echo $b['outstanding'] > 0 ? 'KES ' . number_format($b['outstanding']) : '<span style="color:#333">—</span>'; ?>
            </td>
            <td>
                <span style="color:<?php echo $rateColor; ?>; font-weight:700; font-size:13px;"><?php echo number_format($rate, 1); ?>%</span>
                <div class="repay-bar-wrap">
                    <div class="repay-bar" style="width:<?php echo $rate; ?>%; background:<?php echo $rateColor; ?>;"></div>
                </div>
            </td>
            <td>
                <div style="display:flex; gap:12px;">
                    <div class="stat-mini">
                        <div class="num" style="color:#22c55e;"><?php echo intval($b['approved']) + intval($b['completed']); ?></div>
                        <div class="lbl">Approved</div>
                    </div>
                    <div class="stat-mini">
                        <div class="num" style="color:#eab308;"><?php echo $b['pending']; ?></div>
                        <div class="lbl">Pending</div>
                    </div>
                    <div class="stat-mini">
                        <div class="num" style="color:#ef4444;"><?php echo $b['rejected']; ?></div>
                        <div class="lbl">Rejected</div>
                    </div>
                </div>
            </td>
            <td class="actions" style="text-align:right;">
                <a href="borrower_statement.php?id=<?php echo $b['id']; ?>">STATEMENT</a>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr class="empty-row"><td colspan="8">No borrowers found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

</main>

<script>
document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('dashboardMain').style.marginLeft =
        document.getElementById('sidebar').classList.contains('active') ? '240px' : '0';
});
</script>
</body>
</html>