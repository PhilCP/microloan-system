<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";

// ── Parse pipe-separated collateral string into structured HTML ──
function parseCollateralDesc(string $desc, bool $forTable = false): string {
    if (empty(trim($desc))) return '';
    $parts = explode(' | ', $desc);
    $lines = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $colonPos = strpos($part, ': ');
        if ($colonPos !== false) {
            $label = htmlspecialchars(substr($part, 0, $colonPos));
            $value = htmlspecialchars(substr($part, $colonPos + 2));
            if ($forTable) {
                $lines[] = '<tr>
                    <td class="cd-label-cell">' . $label . '</td>
                    <td class="cd-value-cell">' . $value . '</td>
                </tr>';
            } else {
                $lines[] = '<span class="cd-label">' . $label . ':</span> '
                         . '<span class="cd-value">' . $value . '</span>';
            }
        } else {
            if ($forTable) {
                $lines[] = '<tr><td colspan="2" class="cd-value-cell">' . htmlspecialchars($part) . '</td></tr>';
            } else {
                $lines[] = '<span class="cd-value">' . htmlspecialchars($part) . '</span>';
            }
        }
    }
    if ($forTable) {
        return '<table class="cd-table">' . implode('', $lines) . '</table>';
    }
    return implode('<br>', $lines);
}

// Status filtering and main query 
$allowedStatuses = ['all', 'pending', 'approved', 'rejected', 'completed'];
$filterStatus    = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses)
                   ? $_GET['status'] : 'all';

$whereClause = $filterStatus !== 'all' ? "WHERE l.status = ?" : "";

$sql = "SELECT 
            l.*,
            u.full_name     AS borrower_name,
            u.email         AS borrower_email,
            u.phone         AS borrower_phone,
            o.full_name     AS officer_name,
            CASE 
                WHEN l.status = 'approved'
                 AND l.remaining_balance > 0
                 AND DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH) < NOW()
                THEN 1 ELSE 0
            END AS is_overdue_calc,
            CASE 
                WHEN l.status = 'approved'
                 AND l.remaining_balance > 0
                 AND DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH) < NOW()
                THEN DATEDIFF(NOW(), DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH))
                ELSE 0
            END AS days_overdue,
            CASE 
                WHEN l.status = 'approved'
                 AND l.remaining_balance > 0
                 AND DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH) < NOW()
                THEN ROUND(
                    l.remaining_balance * (COALESCE(l.overdue_penalty_rate, 2.00) / 100) *
                    CEIL(DATEDIFF(NOW(), DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH)) / 30),
                    2
                )
                ELSE 0
            END AS penalty_accrued
        FROM loans l
        JOIN users u ON l.borrower_id = u.id
        LEFT JOIN users o ON l.assigned_officer_id = o.id
        $whereClause
        ORDER BY is_overdue_calc DESC, l.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) { die("Query prepare failed: " . $conn->error); }
if ($filterStatus !== 'all') { $stmt->bind_param('s', $filterStatus); }
$stmt->execute();
$loansResult = $stmt->get_result();
$loans       = $loansResult->fetch_all(MYSQLI_ASSOC);

//Summary counts 
$countSql = "SELECT status, COUNT(*) as cnt FROM loans GROUP BY status";
$countRes = $conn->query($countSql);
$counts   = ['pending'=>0,'approved'=>0,'rejected'=>0,'completed'=>0,'all'=>0];
while ($row = $countRes->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}

$pageTitle = "Loan Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> — Microloan Admin</title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/admin-loans.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; transition: 0.3s; padding: 30px;">
<?php include '../includes/dashboard_header.php'; ?>

<!-- Print-only header -->
<div class="print-header">
    <h1>Microloan Management System — Loan Register</h1>
    <p>Generated: <?php echo date('d M Y, H:i'); ?> &nbsp;|&nbsp; Admin: <?php echo htmlspecialchars($user['full_name']); ?> &nbsp;|&nbsp; Filter: <?php echo strtoupper($filterStatus); ?></p>
</div>

<!-- Toolbar -->
<div class="page-toolbar no-print">
    <div>
        <h2>Loan Management</h2>
        <p>All loan applications — collateral details, overdue status, and penalty tracking</p>
    </div>
    <div class="toolbar-right">
        <button class="btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
        <button class="btn-export" onclick="exportCSV()">⬇ Export CSV</button>
    </div>
</div>

<!-- Status filter pills -->
<div class="filter-pills no-print">
    <?php
    $pillLabels = [
        'all'       => 'All',
        'pending'   => 'Pending',
        'approved'  => 'Active',
        'rejected'  => 'Rejected',
        'completed' => 'Completed',
    ];
    foreach ($pillLabels as $val => $label):
        $isActive = $filterStatus === $val;
        $cnt      = $counts[$val] ?? 0;
    ?>
    <a href="?status=<?php echo $val; ?>"
       class="filter-pill <?php echo $isActive ? 'active' : ''; ?>">
        <?php echo $label; ?>
        <span class="badge"><?php echo $cnt; ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="table-wrapper">
    <table class="loans-table" id="loansTable">
        <thead>
            <tr>
                <th>#</th>
                <th>Borrower</th>
                <th>Amount (KES)</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Assigned Officer</th>
                <th>Security / Collateral</th>
                <th>Penalty Info</th>
                <th>Date Applied</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($loans)): ?>
            <tr class="empty-row">
                <td colspan="9">No loans found for the selected filter.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($loans as $loan):
                $isOverdue      = (int)$loan['is_overdue_calc'];
                $daysOverdue    = (int)$loan['days_overdue'];
                $penaltyAccrued = (float)$loan['penalty_accrued'];
                $penaltyRate    = (float)($loan['overdue_penalty_rate'] ?? 2.00);
                $collType       = $loan['collateral_type'] ?? '';
                $collDesc       = $loan['collateral_description'] ?? '';
                $collParsed     = parseCollateralDesc($collDesc, true); // table format
            ?>
            <tr>
                <!-- Loan ID -->
                <td style="color:#555; font-size:12px; white-space:nowrap;">#<?php echo $loan['id']; ?></td>

                <!-- Borrower -->
                <td class="borrower-cell">
                    <div class="b-name"><?php echo htmlspecialchars($loan['borrower_name']); ?></div>
                    <div class="b-email"><?php echo htmlspecialchars($loan['borrower_email']); ?></div>
                    <div class="b-phone"><?php echo htmlspecialchars($loan['borrower_phone']); ?></div>
                </td>

                <!-- Amount -->
                <td>
                    <div class="amount-cell"><?php echo number_format($loan['amount'], 2); ?></div>
                    <div style="font-size:11px; color:#555; margin-top:2px;">
                        Total: <?php echo number_format($loan['total_amount'], 2); ?>
                    </div>
                </td>

                <!-- Duration -->
                <td style="white-space:nowrap; color:#888; font-size:13px;">
                    <?php echo $loan['duration_months']; ?> mo
                </td>

                <!-- Status -->
                <td>
                    <span class="status-badge status-<?php echo $loan['status']; ?>">
                        <?php echo ucfirst($loan['status']); ?>
                    </span>
                    <?php if ($isOverdue): ?>
                        <br><span class="overdue-badge">⚠ <?php echo $daysOverdue; ?>d overdue</span>
                    <?php endif; ?>
                </td>

                <!-- Officer -->
                <td class="officer-cell">
                    <?php echo !empty($loan['officer_name'])
                        ? htmlspecialchars($loan['officer_name'])
                        : '<span style="color:#3a3a3a;font-style:italic;">Unassigned</span>'; ?>
                </td>

                <!-- Collateral — parsed structured detail -->
                <td class="collateral-cell">
                    <?php if (!empty($collType)): ?>
                        <div class="c-type"><?php echo htmlspecialchars($collType); ?></div>
                        <?php if (!empty($collParsed)): ?>
                            <?php echo $collParsed; ?>
                        <?php else: ?>
                            <span style="font-size:12px;color:#555;font-style:italic;">No details on file</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="c-none">None declared</span>
                    <?php endif; ?>
                </td>

                <!-- Penalty -->
                <td class="penalty-cell">
                    <?php if ($isOverdue && $penaltyAccrued > 0): ?>
                        <div class="p-rate"><?php echo $penaltyRate; ?>%/mo rate</div>
                        <div class="p-accrued">KES <?php echo number_format($penaltyAccrued, 2); ?> accrued</div>
                    <?php elseif ($loan['status'] === 'approved'): ?>
                        <span class="p-none">Not overdue</span>
                        <div class="p-rate" style="margin-top:2px;"><?php echo $penaltyRate; ?>%/mo if late</div>
                    <?php else: ?>
                        <span class="p-none">—</span>
                    <?php endif; ?>
                </td>

                <!-- Date -->
                <td style="font-size:12px; color:#666; white-space:nowrap;">
                    <?php echo date('d M Y', strtotime($loan['created_at'])); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function exportCSV() {
    const table = document.getElementById('loansTable');
    const rows  = table.querySelectorAll('tr');
    let csv     = '';
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        const rowData = Array.from(cells).map(cell => {
            let text = cell.innerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
            return '"' + text.replace(/"/g, '""') + '"';
        });
        csv += rowData.join(',') + '\n';
    });
    const blob    = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url     = URL.createObjectURL(blob);
    const link    = document.createElement('a');
    link.href     = url;
    link.download = 'loans_export_<?php echo date('Y-m-d'); ?>.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

</main>
</body>
</html>