<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$user = getCurrentUser();
$role = "admin";

// Handle manual assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loan_id'], $_POST['officer_id'])) {
    $loanId    = intval($_POST['loan_id']);
    $officerId = intval($_POST['officer_id']);

    $assignStmt = $conn->prepare("UPDATE loans SET assigned_officer_id = ? WHERE id = ? AND status = 'pending'");
    $assignStmt->bind_param("ii", $officerId, $loanId);

    if ($assignStmt->execute() && $assignStmt->affected_rows > 0) {
        // Get officer name for log
        $oRow = $conn->query("SELECT full_name FROM users WHERE id = $officerId")->fetch_assoc();
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $logAction = "ADMIN [{$user['id']}] manually assigned loan #$loanId to officer {$oRow['full_name']} (ID: $officerId)";
        $logStmt->bind_param("is", $user['id'], $logAction);
        $logStmt->execute();
    }
    header("Location: officer-overview.php?msg=assigned");
    exit;
}

// Officer workload stats
$officerStats = $conn->query("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.is_active,
        COUNT(l.id)                          AS total_assigned,
        SUM(l.status = 'pending')            AS pending,
        SUM(l.status = 'approved')           AS approved,
        SUM(l.status = 'rejected')           AS rejected,
        SUM(l.status = 'completed')          AS completed,
        ROUND(AVG(
            CASE WHEN l.status IN ('approved','rejected')
            THEN DATEDIFF(l.approval_date, l.created_at)
            ELSE NULL END
        ), 1)                                AS avg_days_to_action
    FROM users u
    LEFT JOIN loans l ON u.id = l.assigned_officer_id
    WHERE u.role = 'officer'
    GROUP BY u.id, u.full_name, u.email, u.is_active
    ORDER BY pending DESC
");

// Max pending for load bar scaling
$maxPending = 1;
$officerRows = [];
while ($row = $officerStats->fetch_assoc()) {
    $officerRows[] = $row;
    if (intval($row['pending']) > $maxPending) $maxPending = intval($row['pending']);
}

// Unassigned pending loans
$unassigned = $conn->query("
    SELECT l.*, u.full_name AS borrower, u.email
    FROM loans l
    JOIN users u ON l.borrower_id = u.id
    WHERE l.status = 'pending' AND l.assigned_officer_id IS NULL
    ORDER BY l.created_at ASC
");

// Officers list for assign dropdown
$officerList = $conn->query("SELECT id, full_name FROM users WHERE role='officer' AND is_active=1 ORDER BY full_name ASC");
$officers = [];
while ($o = $officerList->fetch_assoc()) $officers[] = $o;

$pageTitle = "Officer Overview";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/users-management.css">
<link rel="stylesheet" href="../assets/css/officer-overview.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left:240px; padding:30px;">
<?php include '../includes/dashboard_header.php'; ?>

<div style="margin-bottom:28px;">
    <h2 style="font-weight:900; text-transform:uppercase; letter-spacing:1px; margin:0;">Officer Overview</h2>
    <p style="color:#444; font-size:13px; margin-top:4px;">Workload distribution, performance metrics and unassigned loan queue.</p>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'assigned'): ?>
    <div class="msg-banner">Loan successfully assigned.</div>
<?php endif; ?>

<!-- Officer Cards -->
<div class="section-title">Officer Workload</div>
<div class="officer-grid">
<?php foreach ($officerRows as $o):
    $pending  = intval($o['pending']);
    $loadPct  = $maxPending > 0 ? ($pending / $maxPending) * 100 : 0;
    $barColor = $loadPct >= 75 ? '#ef4444' : ($loadPct >= 40 ? '#eab308' : '#22c55e');
    $avgDays  = $o['avg_days_to_action'] !== null ? $o['avg_days_to_action'] . 'd' : '—';
?>
<div class="officer-card <?php echo !$o['is_active'] ? 'inactive' : ''; ?>">
    <?php if (!$o['is_active']): ?>
        <span style="position:absolute;top:14px;right:14px;font-size:9px;color:#ef4444;border:1px solid #ef4444;padding:2px 7px;border-radius:3px;font-weight:900;">SUSPENDED</span>
    <?php else: ?>
        <div class="avg-badge">
            <div class="num"><?php echo $avgDays; ?></div>
            <div class="lbl">Avg Action</div>
        </div>
    <?php endif; ?>

    <div class="name"><?php echo htmlspecialchars($o['full_name']); ?></div>
    <div class="email"><?php echo htmlspecialchars($o['email']); ?></div>

    <div class="load-bar-label">
        <span>Pending Load</span>
        <span><?php echo $pending; ?> loan<?php echo $pending != 1 ? 's' : ''; ?></span>
    </div>
    <div class="load-bar-track">
        <div class="load-bar-fill" style="width:<?php echo $loadPct; ?>%; background:<?php echo $barColor; ?>;"></div>
    </div>

    <div class="officer-stats">
        <div class="o-stat">
            <div class="num" style="color:#fff;"><?php echo $o['total_assigned']; ?></div>
            <div class="lbl">Assigned</div>
        </div>
        <div class="o-stat">
            <div class="num" style="color:#eab308;"><?php echo $o['pending']; ?></div>
            <div class="lbl">Pending</div>
        </div>
        <div class="o-stat">
            <div class="num" style="color:#22c55e;"><?php echo intval($o['approved']) + intval($o['completed']); ?></div>
            <div class="lbl">Approved</div>
        </div>
        <div class="o-stat">
            <div class="num" style="color:#ef4444;"><?php echo $o['rejected']; ?></div>
            <div class="lbl">Rejected</div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($officerRows)): ?>
    <p style="color:#222; font-size:12px; text-transform:uppercase; font-weight:900;">No officers registered.</p>
<?php endif; ?>
</div>

<!-- Unassigned Loans -->
<div class="section-title">Unassigned Pending Loans <?php if ($unassigned->num_rows > 0) echo '<span style="color:#ef4444;">(' . $unassigned->num_rows . ')</span>'; ?></div>

<?php if ($unassigned->num_rows > 0): ?>
<div style="overflow-x:auto;">
<table class="ua-table">
    <thead>
        <tr>
            <th>Loan</th>
            <th>Borrower</th>
            <th>Amount</th>
            <th>Purpose</th>
            <th>Days Waiting</th>
            <th>Assign To Officer</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($loan = $unassigned->fetch_assoc()):
        $days    = intval((time() - strtotime($loan['created_at'])) / 86400);
        $urgent  = $days >= 3;
    ?>
    <tr>
        <td style="font-weight:800; color:#fff;">#<?php echo $loan['id']; ?></td>
        <td>
            <div style="font-weight:700; color:#fff;"><?php echo htmlspecialchars($loan['borrower']); ?></div>
            <div style="font-size:11px; color:#333; font-family:monospace;"><?php echo htmlspecialchars($loan['email']); ?></div>
        </td>
        <td style="color:#f0a500; font-weight:700;">KES <?php echo number_format($loan['amount'], 2); ?></td>
        <td style="color:#444; max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            <?php echo htmlspecialchars($loan['purpose']); ?>
        </td>
        <td class="<?php echo $urgent ? 'urgent' : ''; ?>">
            <?php echo $days; ?> day<?php echo $days != 1 ? 's' : ''; ?>
            <?php if ($urgent) echo ' ⚠'; ?>
        </td>
        <td>
            <?php if (!empty($officers)): ?>
            <form method="POST" class="assign-form">
                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                <select name="officer_id" required>
                    <option value="">— Select Officer —</option>
                    <?php foreach ($officers as $o): ?>
                        <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">ASSIGN</button>
            </form>
            <?php else: ?>
                <span style="color:#333; font-size:11px;">No active officers</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>
<?php else: ?>
    <div class="empty-ua">No unassigned loans — queue is clear.</div>
<?php endif; ?>

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