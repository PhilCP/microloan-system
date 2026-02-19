<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';
$user = getCurrentUser();

// Stats
function fetchValue($query, $default=0){
    global $conn;
    $res = $conn->query($query);
    if(!$res) return $default;
    $row = $res->fetch_assoc();
    return $row ? ($row['total'] ?? $default) : $default;
}

$totalLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE approved_by=".intval($user['id'])." OR approved_by IS NULL");
$pendingLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE status='pending'");
$approvedLoans = fetchValue("SELECT COUNT(*) AS total FROM loans WHERE approved_by=".intval($user['id'])." AND status='approved'");
$totalDisbursed = fetchValue("SELECT SUM(total_amount) AS total FROM loans WHERE approved_by=".intval($user['id'])." AND status='approved'");

// Fetch all loans with remarks
$loans = $conn->query("SELECT l.id, u.full_name AS borrower, l.total_amount, l.status, l.created_at, l.admin_remarks
                       FROM loans l
                       JOIN users u ON l.borrower_id = u.id
                       WHERE l.approved_by = " . intval($user['id']) . " 
                       OR (l.status = 'pending' AND l.approved_by IS NULL)
                       ORDER BY l.created_at DESC");

$pageTitle="All Loans";
$role="officer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<style>
/* Table search bar styling */
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

/* Table styling */
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

/* Action buttons */
.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    margin-right: 5px;
    transition: all 0.3s;
}

.action-btn.approve {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    border: 1px solid #22c55e;
}

.action-btn.approve:hover {
    background: rgba(34, 197, 94, 0.3);
}

.action-btn.reject {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid #ef4444;
}

.action-btn.reject:hover {
    background: rgba(239, 68, 68, 0.3);
}

.remark-btn {
    background: #333;
    color: #f0a500;
    border: 1px solid #f0a500;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.3s;
}

.remark-btn:hover {
    background: rgba(240, 165, 0, 0.1);
}

/* MODAL CSS */
.modal { 
    display: none; 
    position: fixed; 
    z-index: 9999; 
    left: 0; 
    top: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.85); 
    backdrop-filter: blur(5px); 
}

.modal-content { 
    background: #1a1a1a; 
    margin: 10% auto; 
    padding: 30px; 
    border: 1px solid #f0a500; 
    width: 90%;
    max-width: 500px;
    border-radius: 12px; 
    color: white; 
    box-shadow: 0 10px 40px rgba(0,0,0,0.5); 
}

.modal-content h3 { 
    color: #f0a500; 
    margin-top: 0; 
}

.modal-content textarea { 
    width: 100%; 
    height: 100px; 
    background: #111; 
    border: 1px solid #333; 
    color: white; 
    padding: 12px; 
    margin: 15px 0; 
    border-radius: 6px; 
    resize: none;
    font-family: inherit;
}

.modal-content textarea:focus {
    outline: none;
    border-color: #f0a500;
}

.modal-actions { 
    display: flex; 
    gap: 10px; 
    justify-content: flex-end; 
}

.modal-actions button { 
    padding: 10px 20px; 
    border-radius: 6px; 
    cursor: pointer; 
    border: none; 
    font-weight: bold; 
}

.btn-confirm { 
    background: #f0a500; 
    color: #000; 
}

.btn-cancel { 
    background: #333; 
    color: #fff; 
}
</style>
</head>
<body>

<!-- Remarks Modal -->
<div id="remarksModal" class="modal">
    <div class="modal-content">
        <h3>Loan Review Remarks</h3>
        <p id="modalLoanText">Applying action to Loan #</p>
        <textarea id="officerRemarks" placeholder="Enter reason for approval or rejection... (Optional)"></textarea>
        <div class="modal-actions">
            <button id="cancelModal" class="btn-cancel">Cancel</button> 
            <button id="confirmAction" class="btn-confirm">Confirm Action</button>
        </div>
    </div>
</div>

<?php include '../includes/sidebar.php'; ?>
<main class="dashboard-main" id="dashboardMain">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome">
    <h2>📊 All Loans</h2>
    <p style="color:#999;">Manage and review all loan applications</p>
</div>

<div class="dashboard-grid">
    <div class="card"><div class="card-title">Total Loans</div><div class="card-value"><?php echo $totalLoans; ?></div></div>
    <div class="card"><div class="card-title">Approved</div><div class="card-value"><?php echo $approvedLoans; ?></div></div>
    <div class="card"><div class="card-title">Pending</div><div class="card-value"><?php echo $pendingLoans; ?></div></div>
    <div class="card"><div class="card-title">Total Disbursed</div><div class="card-value">KES <?php echo number_format($totalDisbursed); ?></div></div>
</div>

<div class="table-container">
    <h3>All Loan Applications</h3>
    <input type="text" id="loanSearch" placeholder="Search loans..." class="table-search">
    <table id="loansTable">
        <thead>
            <tr>
                <th onclick="sortTable('loansTable',0)">ID</th>
                <th onclick="sortTable('loansTable',1)">Borrower</th>
                <th onclick="sortTable('loansTable',2)">Amount</th>
                <th onclick="sortTable('loansTable',3)">Status</th>
                <th onclick="sortTable('loansTable',4)">Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while($l=$loans->fetch_assoc()): ?>
           <tr id="loanRow-<?php echo $l['id']; ?>">
                <td><?php echo $l['id']; ?></td>
                <td><?php echo htmlspecialchars($l['borrower']); ?></td>
                <td>KES <?php echo number_format($l['total_amount']); ?></td>
                <td class="loan-status"><?php echo strtoupper($l['status']); ?></td>
                <td><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                <td>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <?php if($l['status']=='pending'): ?>
                            <button class="action-btn approve" data-id="<?php echo $l['id']; ?>" data-action="approve" title="Approve">Approve</button>
                            <button class="action-btn reject" data-id="<?php echo $l['id']; ?>" data-action="reject" title="Reject">Reject</button>
                        <?php endif; ?>
                        
                        <button class="remark-btn" 
                                data-id="<?php echo $l['id']; ?>" 
                                data-remarks="<?php echo htmlspecialchars($l['admin_remarks'] ?? ''); ?>"
                                title="View/Add Remarks">
                            💬 Notes
                        </button>
                    </div>
                </td>
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

// Modal Logic via Event Delegation
let currentLoanId = null;
let currentAction = null;
const modal = document.getElementById('remarksModal');
const remarksInput = document.getElementById('officerRemarks');
const table = document.getElementById('loansTable');

if (table) {
    table.addEventListener('click', function(e) {
        const actionBtn = e.target.closest('.action-btn');
        const remarkBtn = e.target.closest('.remark-btn');

        if (actionBtn) {
            currentLoanId = actionBtn.dataset.id;
            currentAction = actionBtn.dataset.action;
            const existingRemarks = actionBtn.closest('tr').querySelector('.remark-btn').dataset.remarks;

            document.getElementById('modalLoanText').textContent =
                `Action: ${currentAction.toUpperCase()} | Loan #${currentLoanId}`;
            remarksInput.value = existingRemarks;
            modal.style.display = 'block';
        } 
        else if (remarkBtn) {
            currentLoanId = remarkBtn.dataset.id;
            currentAction = 'remark_only';

            document.getElementById('modalLoanText').textContent =
                `Internal Notes | Loan #${currentLoanId}`;
            remarksInput.value = remarkBtn.dataset.remarks || '';
            modal.style.display = 'block';
        }
    });
}

// Close Modal
document.getElementById('cancelModal').onclick = () => modal.style.display = 'none';
window.onclick = (event) => { if (event.target == modal) modal.style.display = 'none'; };

// Submit Action
document.getElementById('confirmAction').onclick = function() {
    const btn = this;
    const remarks = remarksInput.value;
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Processing...';

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
                const btnUpdate = document.querySelector(`#loanRow-${currentLoanId} .remark-btn`);
                if(btnUpdate) btnUpdate.dataset.remarks = remarks;
                alert('Notes updated successfully');
                modal.style.display = 'none';
            }
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error. Check console.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
    });
};

// Table search
function tableSearch(inputId,tableId){
    const filter=document.getElementById(inputId).value.toUpperCase();
    const trs=document.getElementById(tableId).tBodies[0].rows;
    for(let tr of trs){
        let show=false;
        for(let td of tr.cells){
            if(td.textContent.toUpperCase().includes(filter)){show=true;break;}
        }
        tr.style.display=show?'':'none';
    }
}
document.getElementById('loanSearch').addEventListener('keyup',()=>tableSearch('loanSearch','loansTable'));

// Table sort
function sortTable(tableId,col){
    const table=document.getElementById(tableId);
    let rows=Array.from(table.tBodies[0].rows);
    let asc=table.getAttribute('data-sort')!=='asc';
    rows.sort((a,b)=>{
        let x=a.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
        let y=b.cells[col].innerText.toLowerCase().replace(/[^0-9.]/g,'');
        return (parseFloat(x)>parseFloat(y)?1:-1)*(asc?1:-1);
    });
    rows.forEach(r=>table.tBodies[0].appendChild(r));
    table.setAttribute('data-sort',asc?'asc':'desc');
}
</script>

</main>
</body>
</html>