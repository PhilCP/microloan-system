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
                $lines[] = '<span class="cd-label">' . $label . ':</span> <span class="cd-value">' . $value . '</span>';
            }
        } else {
            if ($forTable) {
                $lines[] = '<tr><td colspan="2" class="cd-value-cell">' . htmlspecialchars($part) . '</td></tr>';
            } else {
                $lines[] = '<span class="cd-value">' . htmlspecialchars($part) . '</span>';
            }
        }
    }
    return $forTable
        ? '<table class="cd-table">' . implode('', $lines) . '</table>'
        : implode('<br>', $lines);
}

function idDocUrl(string $filename): string {
    return '../uploads/id_documents/' . rawurlencode($filename);
}
function idDocIsImage(string $filename): bool {
    return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']);
}

// Fetch all active officers for the reassign dropdown
$officersRes = $conn->query(
    "SELECT id, full_name FROM users WHERE role = 'officer' AND is_active = 1 ORDER BY full_name"
);
$officers = $officersRes->fetch_all(MYSQLI_ASSOC);

$allowedStatuses = ['all','pending','approved','rejected','completed'];
$filterStatus    = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses)
                   ? $_GET['status'] : 'all';
$whereClause     = $filterStatus !== 'all' ? "WHERE l.status = ?" : "";

$sql = "SELECT
            l.*,
            u.full_name  AS borrower_name,
            u.email      AS borrower_email,
            u.phone      AS borrower_phone,
            o.full_name  AS officer_name,
            CASE
                WHEN l.status = 'approved' AND l.remaining_balance > 0
                 AND DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH) < NOW()
                THEN 1 ELSE 0
            END AS is_overdue_calc,
            CASE
                WHEN l.status = 'approved' AND l.remaining_balance > 0
                 AND DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH) < NOW()
                THEN DATEDIFF(NOW(), DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH))
                ELSE 0
            END AS days_overdue,
            CASE
                WHEN l.status = 'approved' AND l.remaining_balance > 0
                 AND DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH) < NOW()
                THEN ROUND(
                    l.remaining_balance * (COALESCE(l.overdue_penalty_rate,2.00)/100) *
                    CEIL(DATEDIFF(NOW(), DATE_ADD(l.created_at, INTERVAL l.duration_months MONTH))/30), 2)
                ELSE 0
            END AS penalty_accrued
        FROM loans l
        JOIN  users u ON l.borrower_id = u.id
        LEFT JOIN users o ON l.assigned_officer_id = o.id
        $whereClause
        ORDER BY is_overdue_calc DESC, l.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) { die("Query prepare failed: " . $conn->error); }
if ($filterStatus !== 'all') { $stmt->bind_param('s', $filterStatus); }
$stmt->execute();
$loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$countRes = $conn->query("SELECT status, COUNT(*) as cnt FROM loans GROUP BY status");
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
<style>
/* ── ID column ───────────────────────────────────────────────── */
.id-cell { min-width: 160px; }
.id-number-display { font-size:13px; color:#ccc; margin-bottom:6px; letter-spacing:.04em; }
.id-number-display strong { color:#fff; font-size:14px; }
.id-thumb {
    width:100%; max-width:120px; height:70px; object-fit:cover;
    border-radius:5px; border:1px solid rgba(255,255,255,0.08);
    cursor:pointer; transition:opacity .2s; display:block; margin-bottom:6px;
}
.id-thumb:hover { opacity:.8; }
.id-pdf-link {
    display:inline-flex; align-items:center; gap:5px;
    font-size:11px; font-weight:700; color:#60a5fa;
    background:rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.3);
    border-radius:5px; padding:4px 9px; text-decoration:none; margin-bottom:6px;
}
.id-pdf-link:hover { background:rgba(59,130,246,.22); }
.id-none { font-size:11px; color:#3a3a3a; font-style:italic; }

/* Verify / Reject buttons */
.btn-verify {
    display:inline-flex; align-items:center; gap:5px;
    font-size:11px; font-weight:800; padding:5px 11px;
    border-radius:5px; border:1px solid transparent;
    cursor:pointer; transition:.2s;
    text-transform:uppercase; letter-spacing:.04em;
    margin-right:4px; margin-top:4px;
}
.btn-verify.do-verify {
    background:rgba(34,197,94,.12); color:#22c55e; border-color:rgba(34,197,94,.3);
}
.btn-verify.do-verify:hover { background:rgba(34,197,94,.22); }
.btn-verify.do-reject {
    background:rgba(239,68,68,.1); color:#ef4444; border-color:rgba(239,68,68,.3);
}
.btn-verify.do-reject:hover { background:rgba(239,68,68,.2); }

/* ID status badges */
.id-status-badge {
    display:inline-block; font-size:10px; font-weight:800;
    text-transform:uppercase; letter-spacing:.05em;
    padding:2px 7px; border-radius:4px; margin-bottom:6px;
}
.id-status-badge.verified { background:rgba(34,197,94,.12);  color:#22c55e; border:1px solid rgba(34,197,94,.3); }
.id-status-badge.rejected { background:rgba(239,68,68,.1);   color:#ef4444; border:1px solid rgba(239,68,68,.3); }
.id-status-badge.pending  { background:rgba(234,179,8,.1);   color:#eab308; border:1px solid rgba(234,179,8,.25); }

/* Officer reassign dropdown */
.officer-select {
    background:#111; color:#ccc;
    border:1px solid #2a2a2a; border-radius:5px;
    padding:4px 6px; font-size:12px;
    width:100%; min-width:130px; cursor:pointer;
}
.officer-select:focus { outline:none; border-color:#444; }

/* Lightbox */
#adminLightbox {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.93); z-index:9999;
    align-items:center; justify-content:center;
    flex-direction:column; gap:14px;
}
#adminLightbox.open { display:flex; }
#adminLightbox img {
    max-width:88vw; max-height:78vh;
    border-radius:8px; box-shadow:0 0 60px rgba(0,0,0,.8);
}
#lbClose {
    position:absolute; top:18px; right:24px;
    font-size:34px; color:#fff; cursor:pointer; opacity:.7; line-height:1;
}
#lbClose:hover { opacity:1; }
#lbCaption { color:#aaa; font-size:13px; }
</style>
</head>
<body>

<!-- Lightbox -->
<div id="adminLightbox">
    <span id="lbClose" onclick="closeLb()">✕</span>
    <img id="lbImg" src="" alt="ID Document">
    <div id="lbCaption"></div>
</div>

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left:240px;transition:0.3s;padding:30px;">
<?php include '../includes/dashboard_header.php'; ?>

<!-- Print header -->
<div class="print-header">
    <h1>Microloan Management System — Loan Register</h1>
    <p>Generated: <?php echo date('d M Y, H:i'); ?> &nbsp;|&nbsp;
       Admin: <?php echo htmlspecialchars($user['full_name']); ?> &nbsp;|&nbsp;
       Filter: <?php echo strtoupper($filterStatus); ?></p>
</div>

<!-- Toolbar -->
<div class="page-toolbar no-print">
    <div>
        <h2>Loan Management</h2>
        <p>All loan applications — collateral details, overdue status, ID verification, and penalty tracking</p>
    </div>
    <div class="toolbar-right">
        <button class="btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
        <button class="btn-export" onclick="exportCSV()">⬇ Export CSV</button>
    </div>
</div>

<!-- Filter pills -->
<div class="filter-pills no-print">
    <?php
    $pillLabels = ['all'=>'All','pending'=>'Pending','approved'=>'Active','rejected'=>'Rejected','completed'=>'Completed'];
    foreach ($pillLabels as $val => $label):
        $cnt = $counts[$val] ?? 0;
    ?>
    <a href="?status=<?php echo $val; ?>"
       class="filter-pill <?php echo $filterStatus === $val ? 'active' : ''; ?>">
        <?php echo $label; ?> <span class="badge"><?php echo $cnt; ?></span>
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
                <th>Officer</th>
                <th>Security / Collateral</th>
                <th class="no-print">🪪 ID Verification</th>
                <th>Penalty Info</th>
                <th>Date Applied</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($loans)): ?>
            <tr class="empty-row">
                <td colspan="10">No loans found for the selected filter.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($loans as $loan):
                $isOverdue      = (int)$loan['is_overdue_calc'];
                $daysOverdue    = (int)$loan['days_overdue'];
                $penaltyAccrued = (float)$loan['penalty_accrued'];
                $penaltyRate    = (float)($loan['overdue_penalty_rate'] ?? 2.00);
                $collType       = $loan['collateral_type'] ?? '';
                $collDesc       = $loan['collateral_description'] ?? '';
                $collParsed     = parseCollateralDesc($collDesc, true);

                $idNumber   = $loan['id_number']   ?? '';
                $idDoc      = $loan['id_document'] ?? '';
                $idVerified = (int)($loan['id_verified'] ?? 0);  // 0=pending, 1=verified, -1=rejected
                $idDocUrl   = $idDoc ? idDocUrl($idDoc) : '';
                $idIsImage  = $idDoc && idDocIsImage($idDoc);

                // Buttons are only shown while decision is still pending (0)
                $idDecisionMade = ($idVerified === 1 || $idVerified === -1);
            ?>
            <tr id="loanRow-<?php echo $loan['id']; ?>">
                <!-- Row # -->
                <td style="color:#555;font-size:12px;white-space:nowrap;">#<?php echo $loan['id']; ?></td>

                <!-- Borrower -->
                <td class="borrower-cell">
                    <div class="b-name"><?php echo htmlspecialchars($loan['borrower_name']); ?></div>
                    <div class="b-email"><?php echo htmlspecialchars($loan['borrower_email']); ?></div>
                    <div class="b-phone"><?php echo htmlspecialchars($loan['borrower_phone']); ?></div>
                </td>

                <!-- Amount -->
                <td>
                    <div class="amount-cell"><?php echo number_format($loan['amount'], 2); ?></div>
                    <div style="font-size:11px;color:#555;margin-top:2px;">
                        Total: <?php echo number_format($loan['total_amount'], 2); ?>
                    </div>
                </td>

                <!-- Duration -->
                <td style="white-space:nowrap;color:#888;font-size:13px;">
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

                <!-- Officer — reassign dropdown for pending/approved loans -->
                <td class="officer-cell">
                    <?php if (in_array($loan['status'], ['pending', 'approved'])): ?>
                        <select class="officer-select no-print"
                                onchange="reassignOfficer(<?php echo $loan['id']; ?>, this.value)"
                                title="Reassign loan officer">
                            <option value="">— Assign Officer —</option>
                            <?php foreach ($officers as $o): ?>
                                <option value="<?php echo $o['id']; ?>"
                                    <?php echo ($loan['assigned_officer_id'] == $o['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Print-only plain text -->
                        <span class="print-only" style="display:none;">
                            <?php echo !empty($loan['officer_name'])
                                ? htmlspecialchars($loan['officer_name'])
                                : 'Unassigned'; ?>
                        </span>
                    <?php else: ?>
                        <?php echo !empty($loan['officer_name'])
                            ? htmlspecialchars($loan['officer_name'])
                            : '<span style="color:#3a3a3a;font-style:italic;">Unassigned</span>'; ?>
                    <?php endif; ?>
                </td>

                <!-- Collateral -->
                <td class="collateral-cell">
                    <?php if (!empty($collType)): ?>
                        <div class="c-type"><?php echo htmlspecialchars($collType); ?></div>
                        <?php echo !empty($collParsed)
                            ? $collParsed
                            : '<span style="font-size:12px;color:#555;font-style:italic;">No details on file</span>'; ?>
                    <?php else: ?>
                        <span class="c-none">None declared</span>
                    <?php endif; ?>
                </td>

                <!-- ID Verification -->
                <td class="id-cell no-print">
                    <?php if (!empty($idNumber)): ?>
                        <div class="id-number-display">
                            ID: <strong><?php echo htmlspecialchars($idNumber); ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="id-none">No ID number</div>
                    <?php endif; ?>

                    <?php if ($idDoc): ?>
                        <?php if ($idIsImage): ?>
                            <img class="id-thumb"
                                 src="<?php echo htmlspecialchars($idDocUrl); ?>"
                                 alt="ID"
                                 onclick="openLb('<?php echo htmlspecialchars($idDocUrl); ?>',
                                          '<?php echo htmlspecialchars($loan['borrower_name']); ?> — ID #<?php echo htmlspecialchars($idNumber); ?>')">
                        <?php else: ?>
                            <a class="id-pdf-link"
                               href="<?php echo htmlspecialchars($idDocUrl); ?>"
                               target="_blank">📄 View PDF</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="id-none" style="margin-bottom:6px;">No document uploaded</div>
                    <?php endif; ?>

                    <?php if (!empty($idNumber) || $idDoc): ?>

                        <!-- Status badge — always visible -->
                        <div id="idStatus-<?php echo $loan['id']; ?>">
                            <?php if ($idVerified === 1): ?>
                                <span class="id-status-badge verified">✓ Verified</span><br>
                            <?php elseif ($idVerified === -1): ?>
                                <span class="id-status-badge rejected">✗ ID Rejected</span><br>
                            <?php else: ?>
                                <span class="id-status-badge pending">⏳ Pending Review</span><br>
                            <?php endif; ?>
                        </div>

                        <?php if (!$idDecisionMade): ?>
                            <!-- Only show action buttons while still pending -->
                            <div id="idBtns-<?php echo $loan['id']; ?>">
                                <button class="btn-verify do-verify"
                                        onclick="setIdStatus(<?php echo $loan['id']; ?>, 'verify')">
                                    ✓ Verify
                                </button>
                                <button class="btn-verify do-reject"
                                        onclick="setIdStatus(<?php echo $loan['id']; ?>, 'reject_id')">
                                    ✗ Reject ID
                                </button>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <span class="id-none">No ID submitted</span>
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
                <td style="font-size:12px;color:#666;white-space:nowrap;">
                    <?php echo date('d M Y', strtotime($loan['created_at'])); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
//  Lightbox 
function openLb(src, caption) {
    document.getElementById('lbImg').src             = src;
    document.getElementById('lbCaption').textContent = caption;
    document.getElementById('adminLightbox').classList.add('open');
}
function closeLb() {
    document.getElementById('adminLightbox').classList.remove('open');
    document.getElementById('lbImg').src = '';
}
document.getElementById('adminLightbox').addEventListener('click', e => {
    if (e.target === document.getElementById('adminLightbox')) closeLb();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLb(); });

// Officer Reassignment
async function reassignOfficer(loanId, officerId) {
    if (!officerId) return;
    try {
        const res  = await fetch('/microloan-system/admin/reassign-officer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `loan_id=${loanId}&officer_id=${officerId}`
        });
        const data = await res.json();
        if (!data.success) {
            alert('Reassignment failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error during reassignment.');
    }
}

// ── ID Verify / Reject
async function setIdStatus(loanId, action) {
    const statusDiv = document.getElementById('idStatus-' + loanId);
    const btnsDiv   = document.getElementById('idBtns-'   + loanId);
    const btns      = btnsDiv ? btnsDiv.querySelectorAll('.btn-verify') : [];

    btns.forEach(b => { b.disabled = true; });

    try {
        const res  = await fetch('/microloan-system/admin/verify-id-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `loan_id=${loanId}&action=${action}`
        });
        const data = await res.json();

        if (data.success) {
            const v = data.id_verified;

            // If rejecting the ID also flipped the loan's status to 'rejected',
            // reload so the row/filters/counts reflect the new state correctly.
            if (v === -1 && data.loan_status === 'rejected') {
                location.reload();
                return;
            }

            let badge = '';
            if (v === 1)       badge = '<span class="id-status-badge verified">✓ Verified</span><br>';
            else if (v === -1) badge = '<span class="id-status-badge rejected">✗ ID Rejected</span><br>';
            else               badge = '<span class="id-status-badge pending">⏳ Pending Review</span><br>';

            statusDiv.innerHTML = badge;

            // Decision is final — remove the action buttons entirely
            if ((v === 1 || v === -1) && btnsDiv) {
                btnsDiv.remove();
            }
        } else {
            alert('Error: ' + (data.error || 'Could not update'));
            btns.forEach(b => { b.disabled = false; });
        }
    } catch (err) {
        alert('Network error. Please try again.');
        btns.forEach(b => { b.disabled = false; });
    }
}

//CSV Export 
function exportCSV() {
    const table = document.getElementById('loansTable');
    let csv = '';
    table.querySelectorAll('tr').forEach(row => {
        const cells = Array.from(row.querySelectorAll('th:not(.no-print), td:not(.no-print)'));
        const rowData = cells.map(cell => {
            let text = cell.innerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
            return '"' + text.replace(/"/g, '""') + '"';
        });
        csv += rowData.join(',') + '\n';
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = 'loans_<?php echo date('Y-m-d'); ?>.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

</main>
</body>
</html>