<?php
/**
 * All Loans Command Center
 * Central interface for Loan Officers to review, approve, and audit applications.
 */
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';
$user = getCurrentUser();

if(!$user) die("Unauthorized access. Session expired.");

/**
 * Utility: Fetch aggregate stats for the Officer's specific portfolio
 */
function fetchValue($query, $default=0){
    global $conn;
    $res = $conn->query($query);
    if(!$res) return $default;
    $row = $res->fetch_assoc();
    return $row ? ($row['total'] ?? $default) : $default;
}

// Portfolio Analytics
$totalLoans     = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE approved_by=".intval($user['id'])." OR (status='pending' AND approved_by IS NULL)");
$pendingLoans   = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE status='pending'");
$approvedLoans  = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE approved_by=".intval($user['id'])." AND status='approved'");
$totalDisbursed = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE approved_by=".intval($user['id'])." AND status='approved'");

// Main Query: Fetch applications requiring attention or already managed by this officer
$loans = $conn->query("SELECT l.id, u.full_name AS borrower, l.total_amount, l.status, l.created_at, l.admin_remarks
                       FROM loans l
                       JOIN users u ON l.borrower_id = u.id
                       WHERE l.approved_by = " . intval($user['id']) . " 
                       OR (l.status = 'pending' AND l.approved_by IS NULL)
                       ORDER BY l.created_at DESC");

$pageTitle = "Loan Registry";
$role = "officer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<style>
/* Tactical Dashboard Overrides */
body { background: #050505; color: #fff; font-family: 'Inter', sans-serif; }

/* Analytics Cards */
.stat-card {
    background: #0a0a0a;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #1a1a1a;
    transition: 0.3s;
}
.stat-card:hover { border-color: #333; transform: translateY(-3px); }
.stat-label { font-size: 10px; color: #555; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px; }
.stat-value { font-size: 24px; font-weight: 900; font-family: 'Courier New', monospace; }

/* Registry Table */
.registry-container {
    background: #0a0a0a;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #1a1a1a;
    margin-top: 30px;
}

.search-bar {
    background: #000;
    border: 1px solid #222;
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    width: 300px;
    font-size: 13px;
    transition: 0.3s;
}
.search-bar:focus { border-color: #f0a500; outline: none; box-shadow: 0 0 10px rgba(240, 165, 0, 0.1); }

table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th { 
    text-align: left; 
    padding: 15px; 
    color: #444; 
    font-size: 11px; 
    text-transform: uppercase; 
    border-bottom: 1px solid #1a1a1a; 
    cursor: pointer;
}
th:hover { color: #f0a500; }
td { padding: 18px 15px; border-bottom: 1px solid #0f0f0f; font-size: 14px; vertical-align: middle; }

/* Operational Badges */
.status-pill {
    font-size: 10px;
    font-weight: 900;
    padding: 4px 10px;
    border-radius: 4px;
    text-transform: uppercase;
}
.status-pending { background: rgba(234, 179, 8, 0.1); color: #eab308; }
.status-approved { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.status-rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

/* Interaction UI */
.action-btn {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    cursor: pointer;
    transition: 0.2s;
    border: 1px solid transparent;
}
.btn-approve { background: rgba(34, 197, 94, 0.1); color: #22c55e; border-color: #22c55e; }
.btn-approve:hover { background: #22c55e; color: #000; }
.btn-reject { background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: #ef4444; }
.btn-reject:hover { background: #ef4444; color: #000; }
.btn-notes { background: #1a1a1a; color: #f0a500; border-color: #333; }

/* Review Modal */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); backdrop-filter: blur(10px); }
.modal-content { background: #0f0f0f; margin: 10% auto; padding: 35px; border: 1px solid #222; width: 450px; border-radius: 12px; }
.modal-title { color: #f0a500; font-weight: 900; text-transform: uppercase; margin-bottom: 20px; display: block; }
textarea { width: 100%; background: #000; border: 1px solid #222; color: #fff; padding: 15px; border-radius: 8px; resize: none; margin-bottom: 20px; }
</style>
</head>
<body>

<div id="remarksModal" class="modal">
    <div class="modal-content">
        <span class="modal-title" id="modalTitle">Application Review</span>
        <p id="modalLoanRef" style="font-size: 12px; color: #444; margin-bottom: 15px; font-family: monospace;"></p>
        <textarea id="officerRemarks" rows="4" placeholder="Enter justification or internal notes..."></textarea>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button id="cancelModal" style="background:transparent; color:#555; border:none; cursor:pointer; font-weight:800; font-size:12px;">DISCARD</button> 
            <button id="confirmAction" style="background:#f0a500; color:#000; padding:10px 25px; border:none; border-radius:6px; font-weight:900; cursor:pointer;">CONFIRM DECISION</button>
        </div>
    </div>
</div>

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; padding: 30px;">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="page-header" style="margin-bottom: 40px;">
        <h2 style="font-weight:900; text-transform:uppercase; letter-spacing:1px;">Loan Application Registry</h2>
        <p style="color:#444;">Review pending requests and audit historical disbursements.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card">
            <div class="stat-label">Assigned Portfolio</div>
            <div class="stat-value"><?php echo $totalLoans; ?></div>
        </div>
        <div class="stat-card" style="border-left: 3px solid #22c55e;">
            <div class="stat-label">Verified Approvals</div>
            <div class="stat-value"><?php echo $approvedLoans; ?></div>
        </div>
        <div class="stat-card" style="border-left: 3px solid #eab308;">
            <div class="stat-label">Awaiting Review</div>
            <div class="stat-value"><?php echo $pendingLoans; ?></div>
        </div>
        <div class="stat-card" style="border-left: 3px solid #f0a500;">
            <div class="stat-label">Capital Disbursed</div>
            <div class="stat-value">KES <?php echo number_format($totalDisbursed); ?></div>
        </div>
    </div>

    <div class="registry-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
            <h3 style="margin:0; font-size:14px; text-transform:uppercase; letter-spacing:1px;">Active Ledger</h3>
            <input type="text" id="loanSearch" placeholder="Search reference or borrower..." class="search-bar">
        </div>
        
        <table id="loansTable">
            <thead>
                <tr>
                    <th onclick="sortTable(0)">ID</th>
                    <th onclick="sortTable(1)">Borrower Name</th>
                    <th onclick="sortTable(2)">Capital Request</th>
                    <th onclick="sortTable(3)">Status</th>
                    <th onclick="sortTable(4)">Filed Date</th>
                    <th>Operations</th>
                </tr>
            </thead>
            <tbody>
            <?php while($l=$loans->fetch_assoc()): ?>
               <tr id="loanRow-<?php echo $l['id']; ?>">
                    <td style="font-family:monospace; color:#444;">#<?php echo str_pad($l['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><strong style="color:#ddd;"><?php echo htmlspecialchars($l['borrower']); ?></strong></td>
                    <td style="color:#22c55e; font-weight:800;">KES <?php echo number_format($l['total_amount']); ?></td>
                    <td><span class="status-pill status-<?php echo strtolower($l['status']); ?>"><?php echo $l['status']; ?></span></td>
                    <td style="color:#444; font-size:12px;"><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <?php if($l['status']=='pending'): ?>
                                <button class="action-btn btn-approve ui-trigger" data-id="<?php echo $l['id']; ?>" data-action="approve">Approve</button>
                                <button class="action-btn btn-reject ui-trigger" data-id="<?php echo $l['id']; ?>" data-action="reject">Reject</button>
                            <?php endif; ?>
                            <button class="action-btn btn-notes ui-trigger" data-id="<?php echo $l['id']; ?>" data-action="remark_only" data-remarks="<?php echo htmlspecialchars($l['admin_remarks'] ?? ''); ?>">Audit Notes</button>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>



<script>
let currentLoanId = null;
let currentAction = null;
const modal = document.getElementById('remarksModal');
const remarksInput = document.getElementById('officerRemarks');

// Tactical Event Delegation
document.querySelector('#loansTable').addEventListener('click', function(e) {
    const trigger = e.target.closest('.ui-trigger');
    if (!trigger) return;

    currentLoanId = trigger.dataset.id;
    currentAction = trigger.dataset.action;
    
    // UI Feedback Mapping
    const titles = {
        'approve': 'Finalize Approval',
        'reject': 'Confirm Rejection',
        'remark_only': 'Internal Ledger Notes'
    };

    document.getElementById('modalTitle').textContent = titles[currentAction];
    document.getElementById('modalLoanRef').textContent = `REFERENCE ID: #LN-${currentLoanId.padStart(4, '0')}`;
    remarksInput.value = trigger.dataset.remarks || '';
    modal.style.display = 'block';
});

document.getElementById('cancelModal').onclick = () => modal.style.display = 'none';

document.getElementById('confirmAction').onclick = function() {
    const btn = this;
    const remarks = remarksInput.value;
    btn.disabled = true;
    btn.textContent = 'COMMITTING...';

    // XHR to logical controller
    fetch('loan_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `loan_id=${currentLoanId}&loan_action=${currentAction}&admin_remarks=${encodeURIComponent(remarks)}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            if(currentAction !== 'remark_only') {
                location.reload();
            } else {
                modal.style.display = 'none';
                // Hot-update the remark data in the DOM
                document.querySelector(`#loanRow-${currentLoanId} .btn-notes`).dataset.remarks = remarks;
            }
        } else {
            alert('SYSTEM REJECTION: ' + data.error);
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'CONFIRM DECISION';
    });
};

// Search Logic
document.getElementById('loanSearch').onkeyup = function() {
    let filter = this.value.toUpperCase();
    let rows = document.querySelectorAll('#loansTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toUpperCase().includes(filter) ? '' : 'none';
    });
};

// Column Sort Logic
function sortTable(n) {
    const table = document.getElementById("loansTable");
    let rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
    switching = true;
    dir = "asc";
    while (switching) {
        switching = false;
        rows = table.rows;
        for (i = 1; i < (rows.length - 1); i++) {
            shouldSwitch = false;
            x = rows[i].getElementsByTagName("TD")[n];
            y = rows[i + 1].getElementsByTagName("TD")[n];
            if (dir == "asc") {
                if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) { shouldSwitch = true; break; }
            } else if (dir == "desc") {
                if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) { shouldSwitch = true; break; }
            }
        }
        if (shouldSwitch) {
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            switchcount ++;
        } else {
            if (switchcount == 0 && dir == "asc") { dir = "desc"; switching = true; }
        }
    }
}
</script>
</body>
</html>