<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

$user = getCurrentUser();
if (!$user) { die("User not found. Check session."); }

$officerId = $user['id'];

// ── Parse pipe-separated collateral string into structured HTML ──
function parseCollateralDesc(string $desc): string {
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
            $lines[] = '<tr>
                <td class="cd-label-cell">' . $label . '</td>
                <td class="cd-value-cell">' . $value . '</td>
            </tr>';
        } else {
            $lines[] = '<tr><td colspan="2" class="cd-value-cell">' . htmlspecialchars($part) . '</td></tr>';
        }
    }
    return empty($lines) ? '' : '<table class="cd-table">' . implode('', $lines) . '</table>';
}

// Fetch active loans — now includes collateral fields
$stmt = $conn->prepare(
    "SELECT l.*, 
            u.full_name, u.email, u.phone,
            (SELECT SUM(amount_paid) FROM repayments WHERE loan_id = l.id) as total_paid,
            (SELECT COUNT(*) FROM repayments WHERE loan_id = l.id) as payment_count,
            (SELECT MAX(payment_date) FROM repayments WHERE loan_id = l.id) as last_payment_date
     FROM loans l 
     JOIN users u ON l.borrower_id = u.id 
     WHERE l.status = 'approved'
       AND l.approved_by = ?
     ORDER BY l.created_at DESC"
);
$stmt->bind_param("i", $officerId);
$stmt->execute();
$approvedResult = $stmt->get_result();
if (!$approvedResult) { die("Query failed: " . $conn->error); }

$pageTitle = "Repayment Management";
$role = "officer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/approved-loans.css">
    <style>
    /* ── Collateral block on card ── */
    .collateral-block {
        background: rgba(240,165,0,0.05);
        border: 1px solid rgba(240,165,0,0.2);
        border-left: 3px solid #f0a500;
        border-radius: 8px;
        padding: 11px 14px;
        margin: 14px 0;
    }
    .collateral-block .block-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #f0a500;
        font-weight: 700;
        margin-bottom: 7px;
    }
    .collateral-type-badge {
        display: inline-block;
        background: rgba(240,165,0,0.12);
        color: #f0a500;
        border: 1px solid rgba(240,165,0,0.28);
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 7px;
    }
    /* Parsed detail table */
    .cd-table { border-collapse: collapse; width: 100%; }
    .cd-label-cell {
        color: #666;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        padding: 3px 10px 3px 0;
        vertical-align: top;
        white-space: nowrap;
    }
    .cd-value-cell {
        color: #ccc;
        font-size: 12px;
        padding: 3px 0;
        vertical-align: top;
        line-height: 1.4;
    }
    .collateral-none {
        font-size: 12px;
        color: #555;
        font-style: italic;
    }
    /* Overdue warning on card */
    .overdue-warn {
        background: rgba(239,68,68,0.07);
        border: 1px solid rgba(239,68,68,0.25);
        border-left: 3px solid #ef4444;
        border-radius: 8px;
        padding: 10px 14px;
        margin: 10px 0;
        font-size: 12px;
        color: #f87171;
    }
    .overdue-warn strong { color: #ef4444; }
    </style>
</head>
<body>

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Credit Entry</h3></div>
        <div id="paymentLoanInfo" class="payment-summary-box"></div>
        <form id="paymentForm">
            <input type="hidden" id="loanId" name="loan_id">
            <div class="form-group">
                <label>Recovery Amount (KES)</label>
                <input type="number" id="paymentAmount" name="amount_paid" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Transaction Date</label>
                <input type="date" id="paymentDate" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Settlement Method</label>
                <select name="payment_method" required>
                    <option value="cash">Field Cash</option>
                    <option value="bank_transfer">Direct Deposit</option>
                    <option value="mobile_money">M-PESA Utility</option>
                </select>
            </div>
            <div class="form-group">
                <label>Internal Reference / Receipt #</label>
                <input type="text" name="receipt_number" placeholder="Optional">
            </div>
            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="button" id="closeModal" class="action-btn" style="background:#1a1a1a; color:#fff;">Abort</button>
                <button type="submit" id="submitPayment" class="action-btn">Commit Entry</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; padding: 30px;">
    <?php include '../includes/dashboard_header.php'; ?>

    <div class="header-section" style="margin-bottom: 40px;">
        <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">Repayment Management</h2>
        <p style="color: #444;">Monitor your assigned loans and record borrower repayments.</p>
    </div>

    <div class="loans-grid">
        <?php if ($approvedResult->num_rows > 0): ?>
            <?php while ($loan = $approvedResult->fetch_assoc()): ?>
                <?php
                $totalPaid       = $loan['total_paid'] ?? 0;
                $balance         = $loan['remaining_balance'];
                $totalContract   = $loan['total_amount'];
                $progressPercent = ($totalContract > 0) ? ($totalPaid / $totalContract) * 100 : 0;

                // Overdue check
                $endDate        = date('Y-m-d', strtotime($loan['created_at'] . ' + ' . $loan['duration_months'] . ' months'));
                $isOverdue      = ($balance > 0 && $endDate < date('Y-m-d'));
                $daysOverdue    = $isOverdue ? (int)((time() - strtotime($endDate)) / 86400) : 0;
                $penaltyRate    = (float)($loan['overdue_penalty_rate'] ?? 2.00);
                $monthsOverdue  = $isOverdue ? max(1, (int)ceil($daysOverdue / 30)) : 0;
                $penaltyAccrued = $isOverdue ? round($balance * ($penaltyRate / 100) * $monthsOverdue, 2) : 0;

                // Collateral
                $collType   = $loan['collateral_type'] ?? '';
                $collDesc   = $loan['collateral_description'] ?? '';
                $collParsed = parseCollateralDesc($collDesc);
                ?>
                <div class="loan-card">
                    <div class="card-header">
                        <span class="ref-id">#LN-<?php echo str_pad($loan['id'], 4, '0', STR_PAD_LEFT); ?></span>
                        <span style="font-size: 10px; color: #22c55e; font-weight: 900;">[ ACTIVE ]</span>
                    </div>

                    <div class="borrower-tag"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                    <div class="contact-tag"><?php echo htmlspecialchars($loan['phone']); ?></div>

                    <div class="stat-grid">
                        <div class="stat-item">
                            <div class="stat-label">Total Contract</div>
                            <div class="stat-value">KES <?php echo number_format($totalContract); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Recovery (Paid)</div>
                            <div class="stat-value" style="color: #22c55e;">KES <?php echo number_format($totalPaid); ?></div>
                        </div>
                        <div class="stat-item" style="grid-column: span 2; border-top: 1px solid #222; margin-top: 5px; padding-top: 15px;">
                            <div class="stat-label">Outstanding Liability</div>
                            <div class="stat-value" style="color: #f0a500; font-size: 20px;">KES <?php echo number_format($balance); ?></div>
                        </div>
                    </div>

                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?php echo min($progressPercent, 100); ?>%"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:10px; color:#444; margin-bottom:16px; font-weight:800;">
                        <span>COLLECTED: <?php echo round($progressPercent); ?>%</span>
                        <span>TERMS: <?php echo $loan['duration_months']; ?> MONTHS</span>
                    </div>

                    <!-- ── OVERDUE WARNING ── -->
                    <?php if ($isOverdue): ?>
                    <div class="overdue-warn">
                        <strong>⚠ OVERDUE — <?php echo $daysOverdue; ?> days</strong><br>
                        Penalty accrued (<?php echo $penaltyRate; ?>%/mo × <?php echo $monthsOverdue; ?> month<?php echo $monthsOverdue > 1 ? 's' : ''; ?>):
                        <strong style="color:#ef4444;">KES <?php echo number_format($penaltyAccrued, 2); ?></strong>
                        &nbsp;— collateral recovery may apply
                    </div>
                    <?php endif; ?>

                    <!-- ── COLLATERAL / SECURITY ── -->
                    <div class="collateral-block">
                        <div class="block-label">🔒 Security / Collateral</div>
                        <?php if (!empty($collType)): ?>
                            <div class="collateral-type-badge"><?php echo htmlspecialchars($collType); ?></div>
                            <?php if (!empty($collParsed)): ?>
                                <?php echo $collParsed; ?>
                            <?php else: ?>
                                <div class="collateral-none">No detail on file</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="collateral-none">⚠ No collateral declared</div>
                        <?php endif; ?>
                    </div>

                    <button class="action-btn trigger-payment"
                            data-id="<?php echo $loan['id']; ?>"
                            data-name="<?php echo htmlspecialchars($loan['full_name']); ?>"
                            data-balance="<?php echo $balance; ?>"
                            data-collateral="<?php echo htmlspecialchars($collType); ?>"
                            data-collateral-desc="<?php echo htmlspecialchars($collDesc); ?>">
                        Record Capital Recovery
                    </button>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align:center; padding: 100px; color:#222;">
                <h3 style="text-transform:uppercase;">No Active Loans Assigned to You</h3>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
const modal = document.getElementById('paymentModal');

// Parse "Key: Value | Key: Value" into HTML rows for the payment modal
function parseCollateralForModal(desc) {
    if (!desc || !desc.trim()) return '<em style="color:#555;font-size:12px;">No details on file</em>';
    const parts = desc.split(' | ');
    let html = '<table style="width:100%;border-collapse:collapse;margin-top:4px;">';
    parts.forEach(part => {
        const idx = part.indexOf(': ');
        if (idx !== -1) {
            const label = part.substring(0, idx).trim();
            const value = part.substring(idx + 2).trim();
            html += `<tr>
                <td style="color:#666;font-size:10px;text-transform:uppercase;letter-spacing:.5px;
                           padding:3px 10px 3px 0;white-space:nowrap;vertical-align:top;font-weight:600;">${label}</td>
                <td style="color:#ccc;font-size:12px;padding:3px 0;">${value}</td>
            </tr>`;
        } else {
            html += `<tr><td colspan="2" style="color:#ccc;font-size:12px;padding:3px 0;">${part.trim()}</td></tr>`;
        }
    });
    html += '</table>';
    return html;
}

document.querySelectorAll('.trigger-payment').forEach(btn => {
    btn.onclick = function() {
        const id          = this.dataset.id;
        const name        = this.dataset.name;
        const bal         = this.dataset.balance;
        const collType    = this.dataset.collateral    || '';
        const collDesc    = this.dataset.collateralDesc || '';

        document.getElementById('loanId').value          = id;
        document.getElementById('paymentAmount').max     = bal;
        document.getElementById('paymentAmount').value   = bal;

        const collBadge = collType
            ? `<div style="margin-top:8px;">
                   <span style="display:inline-block;background:rgba(240,165,0,0.12);color:#f0a500;
                                border:1px solid rgba(240,165,0,0.28);border-radius:4px;
                                padding:2px 8px;font-size:10px;font-weight:700;
                                text-transform:uppercase;margin-bottom:5px;">${collType}</span>
                   ${parseCollateralForModal(collDesc)}
               </div>`
            : '<div style="font-size:12px;color:#555;margin-top:6px;">No collateral declared</div>';

        document.getElementById('paymentLoanInfo').innerHTML = `
            <div style="font-size:12px; color:#666;">CREDITING ACCOUNT:</div>
            <div style="font-weight:900; color:#fff;">${name} (Ref: #LN-${id.padStart(4, '0')})</div>
            <div style="font-size:11px; color:#f0a500; margin-top:5px;">MAX RECOVERY: KES ${parseFloat(bal).toLocaleString()}</div>
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#f0a500;
                        font-weight:700;margin-top:12px;margin-bottom:2px;">🔒 Pledged Security</div>
            ${collBadge}
        `;
        modal.style.display = 'block';
    };
});

document.getElementById('closeModal').onclick = () => modal.style.display = 'none';
window.onclick = (e) => { if (e.target == modal) modal.style.display = 'none'; };

document.getElementById('paymentForm').onsubmit = function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitPayment');
    btn.disabled  = true;
    btn.innerHTML = "COMMITTING...";

    fetch('record_payment.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            modal.style.display = 'none';
            location.reload();
        } else {
            alert('SYSTEM REJECTION: ' + data.error);
            btn.disabled  = false;
            btn.innerHTML = "COMMIT ENTRY";
        }
    })
    .catch(() => {
        alert('COMMUNICATION FAILURE');
        btn.disabled = false;
    });
};
</script>
</body>
</html>