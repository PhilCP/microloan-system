<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();
if (!$user) { die("User not found. Check session."); }

$interest_rate = 5.00;

// penalty rates based on loan amount 
//   KES 500 – 2,000  will be 1.5% / month
//   KES 2,001 – 5,000  will be 2.0% / month
//   KES 5,001 – 10,000 will be 3.0% / month
// 
function getPenaltyRate(float $amount): float {
    if ($amount <= 2000) return 1.5;
    if ($amount <= 5000) return 2.0;
    return 3.0;
}

$success   = '';
$error     = '';
$qualError = '';

// Qualification checks
$chkOverdue = $conn->prepare(
    "SELECT id FROM loans 
     WHERE borrower_id = ? AND status = 'approved' AND remaining_balance > 0
       AND approval_date IS NOT NULL
       AND DATE_ADD(DATE(approval_date), INTERVAL duration_months MONTH) < CURDATE()
     LIMIT 1"
);
$chkOverdue->bind_param("i", $user['id']);
$chkOverdue->execute();
if ($chkOverdue->get_result()->num_rows > 0)
    $qualError = "You have an overdue loan. Please clear your outstanding balance before applying for a new loan.";
$chkOverdue->close();

if (!$qualError) {
    $chkPending = $conn->prepare("SELECT id FROM loans WHERE borrower_id = ? AND status = 'pending' LIMIT 1");
    $chkPending->bind_param("i", $user['id']);
    $chkPending->execute();
    if ($chkPending->get_result()->num_rows > 0)
        $qualError = "You already have a loan application under review. Please wait for it to be processed.";
    $chkPending->close();
}

if (!$qualError) {
    $chkActive = $conn->prepare("SELECT id FROM loans WHERE borrower_id = ? AND status = 'approved' AND remaining_balance > 0 LIMIT 1");
    $chkActive->bind_param("i", $user['id']);
    $chkActive->execute();
    if ($chkActive->get_result()->num_rows > 0)
        $qualError = "You currently have an active loan in repayment. You may apply once your current loan is fully repaid.";
    $chkActive->close();
}

//process application if form submitted and no qualification errors
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$qualError) {
    $amount       = floatval($_POST['amount']);
    $duration     = intval($_POST['duration_months']);
    $purpose      = trim($_POST['purpose']);
    $col_type     = trim($_POST['collateral_type'] ?? '');
    $col_desc     = buildCollateralDescription($col_type, $_POST);
    $penalty_rate = getPenaltyRate($amount); // dynamic

    if ($amount < 500)                      { $error = "Minimum loan amount is KES 500"; }
    elseif ($amount > 10000)                { $error = "Maximum loan amount is KES 10,000"; }
    elseif ($duration < 1 || $duration > 24){ $error = "Loan duration must be between 1 and 24 months"; }
    elseif (empty($purpose))               { $error = "Please provide loan purpose"; }
    elseif (empty($col_type))              { $error = "Please select a collateral type"; }
    elseif (empty($col_desc))              { $error = "Please fill in all collateral details"; }
    else {
        $interest_amount   = ($amount * $interest_rate * $duration) / 100;
        $total_amount      = $amount + $interest_amount;
        $remaining_balance = $total_amount;

        $stmt = $conn->prepare(
            "INSERT INTO loans 
             (borrower_id, amount, interest_rate, total_amount, remaining_balance,
              duration_months, purpose, collateral_type, collateral_description, overdue_penalty_rate, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->bind_param("iddddisssd",
            $user['id'], $amount, $interest_rate,
            $total_amount, $remaining_balance,
            $duration, $purpose,
            $col_type, $col_desc, $penalty_rate
        );

        if ($stmt->execute()) {
            $loan_id = $stmt->insert_id;

            // Round-robin officer assignment
            $officerResult = $conn->query(
                "SELECT u.id, u.full_name, COUNT(l.id) AS loan_count 
                 FROM users u 
                 LEFT JOIN loans l ON u.id = l.assigned_officer_id AND l.status = 'pending'
                 WHERE u.role = 'officer' AND u.is_active = 1
                 GROUP BY u.id, u.full_name
                 ORDER BY loan_count ASC, u.id ASC LIMIT 1"
            );
            if ($officerResult && $officerResult->num_rows > 0) {
                $officer    = $officerResult->fetch_assoc();
                $assignStmt = $conn->prepare("UPDATE loans SET assigned_officer_id = ? WHERE id = ?");
                $assignStmt->bind_param("ii", $officer['id'], $loan_id);
                $assignStmt->execute();
                $sysId = 1;
                $al = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                $aa = "System auto-assigned loan #{$loan_id} to officer {$officer['full_name']}";
                $al->bind_param("is", $sysId, $aa);
                $al->execute();
                $success = "Application submitted and assigned for review!";
                $assignStmt->close();
            } else {
                $success = "Application submitted! An officer will be assigned shortly.";
            }

            $ll = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $la = "Applied for loan of KES " . number_format($amount, 2) . " | Penalty rate: {$penalty_rate}%/month";
            $ll->bind_param("is", $user['id'], $la);
            $ll->execute();
            $_POST = [];
        } else {
            $error = "Error submitting application. " . $conn->error;
        }
        $stmt->close();
    }
}

function buildCollateralDescription(string $type, array $post): string {
    $parts = [];
    switch ($type) {
        case 'Vehicle':
            if (!empty($post['col_reg']))     $parts[] = "Reg: "        . trim($post['col_reg']);
            if (!empty($post['col_make']))    $parts[] = "Make/Model: " . trim($post['col_make']);
            if (!empty($post['col_year']))    $parts[] = "Year: "       . trim($post['col_year']);
            if (!empty($post['col_logbook'])) $parts[] = "Logbook No: " . trim($post['col_logbook']);
            break;
        case 'Land Title':
            if (!empty($post['col_title']))   $parts[] = "Title Deed: " . trim($post['col_title']);
            if (!empty($post['col_county']))  $parts[] = "County: "     . trim($post['col_county']);
            if (!empty($post['col_plot']))    $parts[] = "Plot No: "    . trim($post['col_plot']);
            break;
        case 'Equipment':
            if (!empty($post['col_item']))    $parts[] = "Item: "      . trim($post['col_item']);
            if (!empty($post['col_serial']))  $parts[] = "Serial No: " . trim($post['col_serial']);
            break;
        case 'Livestock':
            if (!empty($post['col_animal'])) $parts[] = "Type: "  . trim($post['col_animal']);
            if (!empty($post['col_count']))  $parts[] = "Count: " . trim($post['col_count']);
            break;
        case 'Household Goods':
            if (!empty($post['col_items']))  $parts[] = "Items: " . trim($post['col_items']);
            break;
        case 'Guarantor':
            if (!empty($post['col_gname']))  $parts[] = "Name: "         . trim($post['col_gname']);
            if (!empty($post['col_gphone'])) $parts[] = "Phone: "        . trim($post['col_gphone']);
            if (!empty($post['col_gid']))    $parts[] = "ID No: "        . trim($post['col_gid']);
            if (!empty($post['col_grel']))   $parts[] = "Relationship: " . trim($post['col_grel']);
            break;
        default:
            if (!empty($post['col_other']))  $parts[] = trim($post['col_other']);
    }
    return implode(' | ', $parts);
}

// Overdue banner data
$overdueStmt = $conn->prepare(
    "SELECT id, remaining_balance, duration_months, approval_date, overdue_penalty_rate
     FROM loans WHERE borrower_id = ? AND status = 'approved' AND remaining_balance > 0
       AND approval_date IS NOT NULL
       AND DATE_ADD(DATE(approval_date), INTERVAL duration_months MONTH) < CURDATE()"
);
$overdueStmt->bind_param("i", $user['id']);
$overdueStmt->execute();
$overdueLoans = $overdueStmt->get_result();

$pageTitle = "Apply for Loan";
$role      = "borrower";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/apply-loan.css">

</head>
<body style="background:#000;color:#fff;">
<?php include '../includes/sidebar.php'; ?>

<main class="dashboard-main" id="dashboardMain" style="margin-left:240px;transition:0.3s;padding:30px;">
<?php include '../includes/dashboard_header.php'; ?>

<div class="welcome" style="margin-bottom:30px;">
    <h2 style="font-weight:800;margin:0;">Capital Request</h2>
    <p style="color:#666;margin-top:5px;">Submit your loan details for processing.</p>
</div>

<!-- overdue banner -->
<?php if ($overdueLoans && $overdueLoans->num_rows > 0):
    while ($ol = $overdueLoans->fetch_assoc()):
        $daysOverdue   = max(0, (int)(((time() - strtotime($ol['approval_date'])) / 86400) - ($ol['duration_months'] * 30)));
        $monthsOverdue = max(1, (int)ceil($daysOverdue / 30));
        $penalty       = round($ol['remaining_balance'] * ($ol['overdue_penalty_rate'] / 100) * $monthsOverdue, 2);
?>
<div class="overdue-banner">
    <h4>⚠ Overdue Loan — Loan #<?php echo $ol['id']; ?></h4>
    <p>Repayment is <strong><?php echo $daysOverdue; ?> days overdue</strong>. Outstanding: <strong>KES <?php echo number_format($ol['remaining_balance'], 2); ?></strong></p>
    <p style="color:#fca5a5;font-weight:700;">
        Penalty (<?php echo $ol['overdue_penalty_rate']; ?>%/month × <?php echo $monthsOverdue; ?> month<?php echo $monthsOverdue > 1 ? 's':''; ?>):
        KES <?php echo number_format($penalty, 2); ?>
    </p>
    <p style="font-size:12px;margin-top:8px;">
        <a href="repay-loan.php?id=<?php echo $ol['id']; ?>" style="color:#ef4444;font-weight:700;">Make a payment now →</a>
    </p>
</div>
<?php endwhile; endif; ?>

<!-- qualification -->
<?php if ($qualError): ?>
<div class="qual-block">
    <h4> Application Not Available</h4>
    <p><?php echo htmlspecialchars($qualError); ?></p>
    <a href="my-loans.php">View My Loans →</a>
</div>
<?php else: ?>

<div class="form-container">
    <?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
        <div style="margin-top:10px;"><a href="my-loans.php" style="color:#fff;text-decoration:underline;">Track Applications →</a></div>
    </div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="info-box">
        <h4>Application Guidelines</h4>
        <p style="margin:0;line-height:1.8;font-size:13px;">
            • Limits: <strong>KES 500 — KES 10,000</strong><br>
            • Interest: <strong><?php echo $interest_rate; ?>% Flat Rate</strong> per month<br>
            • Overdue Penalty: <strong>Varies by loan amount</strong> (highlighted below as you type)<br>
            • <strong style="color:#f0a500;">Collateral required</strong> as security for every application
        </p>
    </div>

    <form method="POST" id="loanForm">
        <div class="form-grid">
            <div class="form-group">
                <label for="amount">Requested Amount (KES) <span class="required">*</span></label>
                <input type="number" id="amount" name="amount" min="500" max="10000" step="100"
                       value="<?php echo $_POST['amount'] ?? '5000'; ?>"
                       required oninput="calculateLoan()">
                <div style="color:#444;font-size:11px;margin-top:5px;">Min: 500 | Max: 10,000</div>
            </div>
            <div class="form-group">
                <label for="duration_months">Repayment Duration <span class="required">*</span></label>
                <select id="duration_months" name="duration_months" required onchange="calculateLoan()">
                    <?php foreach ([1,2,3,6,9,12,18,24] as $d):
                        $sel = (isset($_POST['duration_months']) && $_POST['duration_months'] == $d) ||
                               (!isset($_POST['duration_months']) && $d == 6) ? 'selected' : '';
                        echo "<option value='$d' $sel>$d Month".($d > 1 ? 's':'')."</option>";
                    endforeach; ?>
                </select>
            </div>
        </div>

        <!-- penalty tier display -->
        <div class="tier-section">
            <div class="tier-label">Overdue Penalty Tiers — your tier highlights as you enter the amount</div>
            <div class="penalty-tier">
                <div class="tier-box" id="tier1">
                    <div class="tier-range">KES 500 – 2,000</div>
                    <div class="tier-rate">1.5%</div>
                    <div class="tier-sub">per month if overdue</div>
                </div>
                <div class="tier-box" id="tier2">
                    <div class="tier-range">KES 2,001 – 5,000</div>
                    <div class="tier-rate">2.0%</div>
                    <div class="tier-sub">per month if overdue</div>
                </div>
                <div class="tier-box" id="tier3">
                    <div class="tier-range">KES 5,001 – 10,000</div>
                    <div class="tier-rate">3.0%</div>
                    <div class="tier-sub">per month if overdue</div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="calculation-box">
            <h4 style="color:#666;font-size:12px;text-transform:uppercase;margin-top:0;">Financial Summary</h4>
            <div class="calc-row"><span>Principal</span><span id="principalAmount">KES 0</span></div>
            <div class="calc-row"><span>Flat Interest (<?php echo $interest_rate; ?>%/month)</span><span id="interestAmount">KES 0</span></div>
            <div class="calc-row"><span>Term Duration</span><span id="durationDisplay">-</span></div>
            <div class="calc-row"><span>Monthly Instalment</span><span id="monthlyPayment" style="font-weight:700;">KES 0</span></div>
            <div class="calc-row" style="color:#f0a500;font-size:12px;">
                <span>Your Overdue Penalty Rate</span>
                <span id="penaltyRateDisplay">-</span>
            </div>
            <div class="calc-row" style="color:#ef4444;font-size:12px;">
                <span>Est. Monthly Penalty (if missed)</span>
                <span id="penaltyAmount">KES 0 / month</span>
            </div>
            <div class="calc-row total"><span>Total Payable</span><span id="totalAmount">KES 0</span></div>
        </div>

        <!-- loan purpose -->
        <div class="form-group full-width">
            <label for="purpose">Loan Purpose <span class="required">*</span></label>
            <textarea id="purpose" name="purpose" rows="3" placeholder="Define the utility of these funds..." required><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
        </div>

        <!-- collateral -->
        <div class="collateral-box">
            <h4>Security / Collateral</h4>
            <p>Collateral is an asset you pledge as security. If you are unable to repay, the lender has the right to recover the outstanding balance through your pledged asset. Provide accurate details, these will be verified before approval.</p>

            <div class="form-group">
                <label for="collateral_type">Collateral Type <span class="required">*</span></label>
                <select id="collateral_type" name="collateral_type" required onchange="showCollateralFields(this.value)">
                    <option value="">— Select Asset Type —</option>
                    <?php
                    $ctypes = ['Vehicle'=>'Vehicle (Car, Motorcycle, etc.)', 'Land Title'=>'Land Title / Property',
                               'Equipment'=>'Business Equipment / Machinery', 'Livestock'=>'Livestock',
                               'Household Goods'=>'Household Goods / Electronics', 'Guarantor'=>'Personal Guarantor', 'Other'=>'Other'];
                    foreach ($ctypes as $val => $label):
                        $sel = (($_POST['collateral_type'] ?? '') === $val) ? 'selected' : '';
                        echo "<option value='$val' $sel>$label</option>";
                    endforeach;
                    ?>
                </select>
            </div>

            <div id="fields-Vehicle" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group"><label>Registration Plate <span class="required">*</span></label><input type="text" name="col_reg" placeholder="e.g. KCA 001A" value="<?php echo htmlspecialchars($_POST['col_reg']??''); ?>"></div>
                    <div class="form-group"><label>Make &amp; Model <span class="required">*</span></label><input type="text" name="col_make" placeholder="e.g. Toyota Fielder" value="<?php echo htmlspecialchars($_POST['col_make']??''); ?>"></div>
                    <div class="form-group"><label>Year of Manufacture <span class="required">*</span></label><input type="number" name="col_year" placeholder="e.g. 2018" min="1980" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($_POST['col_year']??''); ?>"></div>
                    <div class="form-group"><label>Logbook Number <span class="required">*</span></label><input type="text" name="col_logbook" placeholder="e.g. LB/2018/123456" value="<?php echo htmlspecialchars($_POST['col_logbook']??''); ?>"></div>
                </div>
            </div>
            <div id="fields-Land Title" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group"><label>Title Deed Number <span class="required">*</span></label><input type="text" name="col_title" placeholder="e.g. Nairobi/Block 12/3456" value="<?php echo htmlspecialchars($_POST['col_title']??''); ?>"></div>
                    <div class="form-group"><label>County <span class="required">*</span></label><input type="text" name="col_county" placeholder="e.g. Nairobi" value="<?php echo htmlspecialchars($_POST['col_county']??''); ?>"></div>
                    <div class="form-group"><label>Plot / LR Number <span class="required">*</span></label><input type="text" name="col_plot" placeholder="e.g. LR No. 209/8765" value="<?php echo htmlspecialchars($_POST['col_plot']??''); ?>"></div>
                </div>
            </div>
            <div id="fields-Equipment" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group"><label>Item Description <span class="required">*</span></label><input type="text" name="col_item" placeholder="e.g. Welding Machine" value="<?php echo htmlspecialchars($_POST['col_item']??''); ?>"></div>
                    <div class="form-group"><label>Serial Number <span class="required">*</span></label><input type="text" name="col_serial" placeholder="e.g. SN/2021/XYZ789" value="<?php echo htmlspecialchars($_POST['col_serial']??''); ?>"></div>
                </div>
            </div>
            <div id="fields-Livestock" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group"><label>Type of Animal <span class="required">*</span></label><input type="text" name="col_animal" placeholder="e.g. Dairy Cattle, Goats" value="<?php echo htmlspecialchars($_POST['col_animal']??''); ?>"></div>
                    <div class="form-group"><label>Number of Animals <span class="required">*</span></label><input type="number" name="col_count" placeholder="e.g. 5" min="1" value="<?php echo htmlspecialchars($_POST['col_count']??''); ?>"></div>
                </div>
            </div>
            <div id="fields-Household Goods" class="col-fields">
                <div class="fields-grid single">
                    <div class="form-group"><label>List of Items <span class="required">*</span></label><textarea name="col_items" rows="3" placeholder="e.g. 55-inch Samsung TV (SN: ABC123), LG Fridge"><?php echo htmlspecialchars($_POST['col_items']??''); ?></textarea></div>
                </div>
            </div>
            <div id="fields-Guarantor" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group"><label>Guarantor Full Name <span class="required">*</span></label><input type="text" name="col_gname" placeholder="e.g. Jane Wanjiru Kamau" value="<?php echo htmlspecialchars($_POST['col_gname']??''); ?>"></div>
                    <div class="form-group"><label>Guarantor Phone <span class="required">*</span></label><input type="text" name="col_gphone" placeholder="e.g. 0712 345 678" value="<?php echo htmlspecialchars($_POST['col_gphone']??''); ?>"></div>
                    <div class="form-group"><label>Guarantor ID Number <span class="required">*</span></label><input type="text" name="col_gid" placeholder="e.g. 12345678" value="<?php echo htmlspecialchars($_POST['col_gid']??''); ?>"></div>
                    <div class="form-group"><label>Relationship to Borrower <span class="required">*</span></label><input type="text" name="col_grel" placeholder="e.g. Spouse, Sibling, Employer" value="<?php echo htmlspecialchars($_POST['col_grel']??''); ?>"></div>
                </div>
            </div>
            <div id="fields-Other" class="col-fields">
                <div class="fields-grid single">
                    <div class="form-group"><label>Describe Your Collateral <span class="required">*</span></label><textarea name="col_other" rows="3" placeholder="Describe the asset in enough detail to identify and recover it."><?php echo htmlspecialchars($_POST['col_other']??''); ?></textarea></div>
                </div>
            </div>

            <p style="font-size:11px;color:#555;margin:16px 0 0;border-top:1px solid #1e3a16;padding-top:10px;">
                 By submitting, you acknowledge that your pledged collateral may be subject to recovery action if this loan remains unpaid beyond the agreed repayment period.
            </p>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Submit Application</button>
            <a href="dashboard.php" class="btn btn-secondary">Discard</a>
        </div>
    </form>
</div>

<?php endif; ?>

<script>
document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

const INTEREST_RATE = <?php echo $interest_rate; ?>;

function getPenaltyRate(amount) {
    if (amount <= 2000) return 1.5;
    if (amount <= 5000) return 2.0;
    return 3.0;
}

function highlightTier(amount) {
    ['tier1','tier2','tier3'].forEach(id => document.getElementById(id).classList.remove('active-tier'));
    if      (amount <= 2000) document.getElementById('tier1').classList.add('active-tier');
    else if (amount <= 5000) document.getElementById('tier2').classList.add('active-tier');
    else                     document.getElementById('tier3').classList.add('active-tier');
}

function calculateLoan() {
    const amount   = parseFloat(document.getElementById('amount').value) || 0;
    const duration = parseInt(document.getElementById('duration_months').value) || 0;
    const interest = (amount * INTEREST_RATE * duration) / 100;
    const total    = amount + interest;
    const monthly  = duration > 0 ? total / duration : 0;
    const penRate  = getPenaltyRate(amount);
    const penAmt   = amount * (penRate / 100);

    const fmt = (n) => n.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});

    document.getElementById('principalAmount').textContent    = 'KES ' + amount.toLocaleString();
    document.getElementById('interestAmount').textContent     = 'KES ' + fmt(interest);
    document.getElementById('durationDisplay').textContent    = duration + ' Month' + (duration !== 1 ? 's' : '');
    document.getElementById('totalAmount').textContent        = 'KES ' + fmt(total);
    document.getElementById('monthlyPayment').textContent     = 'KES ' + fmt(monthly);
    document.getElementById('penaltyRateDisplay').textContent = penRate.toFixed(1) + '% / month';
    document.getElementById('penaltyAmount').textContent      = 'KES ' + fmt(penAmt) + ' / month';

    highlightTier(amount);
}

function showCollateralFields(type) {
    document.querySelectorAll('.col-fields').forEach(el => el.classList.remove('active'));
    const t = document.getElementById('fields-' + type);
    if (t) t.classList.add('active');
}

(function () {
    const saved = document.getElementById('collateral_type') ? document.getElementById('collateral_type').value : '';
    if (saved) showCollateralFields(saved);
    calculateLoan();
})();
</script>
</main>
</body>
</html>