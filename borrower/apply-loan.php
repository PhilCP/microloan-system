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

// Handle uploaded ID document
function saveIdDocument(int $userId): string {
    if (!isset($_FILES['id_document']) || $_FILES['id_document']['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $file = $_FILES['id_document'];
    if ($file['error'] !== UPLOAD_ERR_OK) return '';
    if ($file['size'] > 5 * 1024 * 1024) return '';

    $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowedMimes)) return '';

    $uploadDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'id_documents' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    if (!is_writable($uploadDir)) return '';

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeName = 'id_' . $userId . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $safeName)) {
        return $safeName;
    }
    return '';
}

function buildCollateralDescription(string $type, array $post): string {
    $parts = [];
    switch ($type) {
        case 'Mobile Phone':
            if (!empty($post['col_brand']))  $parts[] = "Brand/Model: " . trim($post['col_brand']);
            if (!empty($post['col_imei']))   $parts[] = "IMEI: "        . trim($post['col_imei']);
            if (!empty($post['col_colour'])) $parts[] = "Colour: "      . trim($post['col_colour']);
            break;
        case 'Household Electronics':
            if (!empty($post['col_item']))   $parts[] = "Item: "         . trim($post['col_item']);
            if (!empty($post['col_brand2'])) $parts[] = "Brand: "        . trim($post['col_brand2']);
            if (!empty($post['col_serial'])) $parts[] = "Serial/Model: " . trim($post['col_serial']);
            break;
        case 'Household Furniture':
            if (!empty($post['col_furnitems'])) $parts[] = "Items: "     . trim($post['col_furnitems']);
            if (!empty($post['col_furcond']))   $parts[] = "Condition: " . trim($post['col_furcond']);
            break;
        case 'Business Stock':
            if (!empty($post['col_biztype']))  $parts[] = "Business Type: " . trim($post['col_biztype']);
            if (!empty($post['col_bizgoods'])) $parts[] = "Stock/Goods: "   . trim($post['col_bizgoods']);
            if (!empty($post['col_bizval']))   $parts[] = "Est. Value: KES " . trim($post['col_bizval']);
            break;
        case 'Poultry / Livestock':
            if (!empty($post['col_animal'])) $parts[] = "Type: "  . trim($post['col_animal']);
            if (!empty($post['col_count']))  $parts[] = "Count: " . trim($post['col_count']);
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$qualError) {
    $amount       = floatval($_POST['amount']);
    $duration     = intval($_POST['duration_months']);
    $purpose      = trim($_POST['purpose']);
    $col_type     = trim($_POST['collateral_type'] ?? '');
    $col_desc     = buildCollateralDescription($col_type, $_POST);
    $penalty_rate = getPenaltyRate($amount);
    $id_number    = trim($_POST['id_number'] ?? '');

    if ($amount < 500)                       { $error = "Minimum loan amount is KES 500."; }
    elseif ($amount > 10000)                 { $error = "Maximum loan amount is KES 10,000."; }
    elseif ($duration < 1 || $duration > 24) { $error = "Loan duration must be between 1 and 24 months."; }
    elseif (empty($purpose))                 { $error = "Please provide the loan purpose."; }
    elseif (empty($col_type))                { $error = "Please select a collateral type."; }
    elseif (empty($col_desc))                { $error = "Please fill in all collateral details."; }
    elseif (empty($id_number))               { $error = "Please enter your National ID number."; }
    elseif (!preg_match('/^\d{7,8}$/', $id_number)) { $error = "National ID must be 7 or 8 digits."; }
    elseif (empty($_FILES['id_document']['name']) || $_FILES['id_document']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = "Please attach your ID document (photo or PDF) before submitting.";
    } else {
        $id_doc = saveIdDocument($user['id']);
        if (empty($id_doc)) { $error = "ID document upload failed. Please check the file is a JPG, PNG or PDF under 5MB."; }
    }

    if (empty($error) && isset($id_doc)) {

        $interest_amount   = ($amount * $interest_rate * $duration) / 100;
        $total_amount      = $amount + $interest_amount;
        $remaining_balance = $total_amount;

        $stmt = $conn->prepare(
            "INSERT INTO loans
             (borrower_id, amount, interest_rate, total_amount, remaining_balance,
              duration_months, purpose, collateral_type, collateral_description,
              overdue_penalty_rate, id_number, id_document, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->bind_param("iddddisssdss",
            $user['id'], $amount, $interest_rate,
            $total_amount, $remaining_balance,
            $duration, $purpose,
            $col_type, $col_desc, $penalty_rate,
            $id_number, $id_doc
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
                $al    = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                $aa    = "System auto-assigned loan #{$loan_id} to officer {$officer['full_name']}";
                $al->bind_param("is", $sysId, $aa);
                $al->execute();
                $success = "Application submitted and assigned for review! Reference: #LN-" . str_pad($loan_id, 5, '0', STR_PAD_LEFT);
                $assignStmt->close();
            } else {
                $success = "Application submitted! An officer will be assigned shortly. Reference: #LN-" . str_pad($loan_id, 5, '0', STR_PAD_LEFT);
            }

            $ll = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $la = "Applied for loan of KES " . number_format($amount, 2) . " | Penalty rate: {$penalty_rate}%/month";
            $ll->bind_param("is", $user['id'], $la);
            $ll->execute();
            $_POST = [];
        } else {
            $error = "Error submitting application: " . $conn->error;
        }
        $stmt->close();
    }
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

<!-- Overdue Banner -->
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
        Penalty (<?php echo $ol['overdue_penalty_rate']; ?>%/month × <?php echo $monthsOverdue; ?> month<?php echo $monthsOverdue > 1 ? 's' : ''; ?>):
        KES <?php echo number_format($penalty, 2); ?>
    </p>
    <p style="font-size:12px;margin-top:8px;">
        <a href="repay-loan.php?id=<?php echo $ol['id']; ?>" style="color:#ef4444;font-weight:700;">Make a payment now →</a>
    </p>
</div>
<?php endwhile; endif; ?>

<!-- Qualification Block -->
<?php if ($qualError): ?>
<div class="qual-block">
    <h4>Application Not Available</h4>
    <p><?php echo htmlspecialchars($qualError); ?></p>
    <a href="my-loans.php">View My Loans →</a>
</div>
<?php else: ?>

<div class="form-container">

    <?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
        <div style="margin-top:10px;">
            <a href="my-loans.php" style="color:#fff;text-decoration:underline;">Track Applications →</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="info-box">
        <h4>Application Guidelines</h4>
        <p style="margin:0;line-height:1.8;font-size:13px;">
            • Limits: <strong>KES 500 — KES 10,000</strong><br>
            • Interest: <strong><?php echo $interest_rate; ?>% Flat Rate</strong> per month<br>
            • Overdue Penalty: <strong>Varies by loan amount</strong> (highlighted below as you type)<br>
            • <strong style="color:#f0a500;">Collateral required</strong> as security for every application
        </p>
    </div>

    <form method="POST" id="loanForm" enctype="multipart/form-data">

        <!-- Amount & Duration -->
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
                        echo "<option value='$d' $sel>$d Month" . ($d > 1 ? 's' : '') . "</option>";
                    endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Penalty Tier Display -->
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

        <!-- Loan Purpose -->
        <div class="form-group full-width">
            <label for="purpose">
                Loan Purpose <span class="required">*</span>
                <span id="charCount" style="float:right;font-size:11px;color:#444;font-weight:400;">0 / 300</span>
            </label>
            <textarea id="purpose" name="purpose" rows="3"
                      placeholder="Define the utility of these funds..."
                      maxlength="300" required
                      oninput="updateCharCount()"><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
        </div>

        <!-- Collateral -->
        <div class="collateral-box">
            <h4>Security / Collateral</h4>
            <p>Collateral is an asset you pledge as security. If you are unable to repay, the lender has the right to recover the outstanding balance through your pledged asset. Provide accurate details — these will be verified before approval.</p>

            <div class="form-group">
                <label for="collateral_type">Collateral Type <span class="required">*</span></label>
                <select id="collateral_type" name="collateral_type" required onchange="showCollateralFields(this.value)">
                    <option value="">— Select Asset Type —</option>
                    <?php
                    $ctypes = [
                        'Mobile Phone'          => 'Mobile Phone',
                        'Household Electronics' => 'Household Electronics (TV, Radio, etc.)',
                        'Household Furniture'   => 'Household Furniture',
                        'Business Stock'        => 'Business Stock / Trade Goods',
                        'Poultry / Livestock'   => 'Poultry / Small Livestock',
                        'Guarantor'             => 'Personal Guarantor',
                        'Other'                 => 'Other',
                    ];
                    foreach ($ctypes as $val => $label):
                        $sel = (($_POST['collateral_type'] ?? '') === $val) ? 'selected' : '';
                        echo "<option value='$val' $sel>$label</option>";
                    endforeach;
                    ?>
                </select>
            </div>

            <!-- Mobile Phone -->
            <div id="fields-Mobile Phone" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group">
                        <label>Phone Brand &amp; Model <span class="required">*</span></label>
                        <input type="text" name="col_brand" placeholder="e.g. Samsung Galaxy A05" value="<?php echo htmlspecialchars($_POST['col_brand'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>IMEI Number <span class="required">*</span></label>
                        <input type="text" name="col_imei" placeholder="Dial *#06# to find it" maxlength="15" value="<?php echo htmlspecialchars($_POST['col_imei'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Colour <span class="required">*</span></label>
                        <input type="text" name="col_colour" placeholder="e.g. Black" value="<?php echo htmlspecialchars($_POST['col_colour'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Household Electronics -->
            <div id="fields-Household Electronics" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group">
                        <label>Item Description <span class="required">*</span></label>
                        <input type="text" name="col_item" placeholder="e.g. 32-inch TV, Radio, Iron Box" value="<?php echo htmlspecialchars($_POST['col_item'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Brand <span class="required">*</span></label>
                        <input type="text" name="col_brand2" placeholder="e.g. Ramtons, LG, Sony" value="<?php echo htmlspecialchars($_POST['col_brand2'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Serial / Model Number</label>
                        <input type="text" name="col_serial" placeholder="e.g. SN123456 (if available)" value="<?php echo htmlspecialchars($_POST['col_serial'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Household Furniture -->
            <div id="fields-Household Furniture" class="col-fields">
                <div class="fields-grid single">
                    <div class="form-group">
                        <label>List of Furniture Items <span class="required">*</span></label>
                        <textarea name="col_furnitems" rows="3" placeholder="e.g. 3-seater sofa set, Bed frame + mattress, Dining table with 4 chairs"><?php echo htmlspecialchars($_POST['col_furnitems'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Overall Condition <span class="required">*</span></label>
                        <select name="col_furcond">
                            <option value="">— Select —</option>
                            <?php foreach (['New','Good','Fair','Worn'] as $c):
                                $sel = (($_POST['col_furcond'] ?? '') === $c) ? 'selected' : '';
                                echo "<option value='$c' $sel>$c</option>";
                            endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Business Stock -->
            <div id="fields-Business Stock" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group">
                        <label>Type of Business <span class="required">*</span></label>
                        <input type="text" name="col_biztype" placeholder="e.g. Mama mboga, Mitumba, Chips stall" value="<?php echo htmlspecialchars($_POST['col_biztype'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Stock / Goods Description <span class="required">*</span></label>
                        <input type="text" name="col_bizgoods" placeholder="e.g. Assorted vegetables, Second-hand clothes" value="<?php echo htmlspecialchars($_POST['col_bizgoods'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estimated Stock Value (KES) <span class="required">*</span></label>
                        <input type="number" name="col_bizval" placeholder="e.g. 3000" min="0" value="<?php echo htmlspecialchars($_POST['col_bizval'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Poultry / Livestock -->
            <div id="fields-Poultry / Livestock" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group">
                        <label>Type of Animal <span class="required">*</span></label>
                        <input type="text" name="col_animal" placeholder="e.g. Chicken, Ducks, Rabbits, Goats" value="<?php echo htmlspecialchars($_POST['col_animal'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Number of Animals <span class="required">*</span></label>
                        <input type="number" name="col_count" placeholder="e.g. 10" min="1" value="<?php echo htmlspecialchars($_POST['col_count'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Guarantor -->
            <div id="fields-Guarantor" class="col-fields">
                <div class="fields-grid">
                    <div class="form-group">
                        <label>Guarantor Full Name <span class="required">*</span></label>
                        <input type="text" name="col_gname" placeholder="e.g. Jane Wanjiru Kamau" value="<?php echo htmlspecialchars($_POST['col_gname'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Guarantor Phone <span class="required">*</span></label>
                        <input type="text" name="col_gphone" placeholder="e.g. 0712 345 678" value="<?php echo htmlspecialchars($_POST['col_gphone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Guarantor ID Number <span class="required">*</span></label>
                        <input type="text" name="col_gid" placeholder="e.g. 12345678" value="<?php echo htmlspecialchars($_POST['col_gid'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Relationship to Borrower <span class="required">*</span></label>
                        <input type="text" name="col_grel" placeholder="e.g. Spouse, Neighbour, Chama member" value="<?php echo htmlspecialchars($_POST['col_grel'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Other -->
            <div id="fields-Other" class="col-fields">
                <div class="fields-grid single">
                    <div class="form-group">
                        <label>Describe Your Collateral <span class="required">*</span></label>
                        <textarea name="col_other" rows="3" placeholder="Describe the item clearly — what it is, its condition, and how it can be identified."><?php echo htmlspecialchars($_POST['col_other'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <p style="font-size:11px;color:#555;margin:16px 0 0;border-top:1px solid #1e3a16;padding-top:10px;">
                ⚠ By submitting, you acknowledge that your pledged collateral may be subject to recovery action if this loan remains unpaid beyond the agreed repayment period.
            </p>
        </div>

        <!-- ID Section -->
        <div class="collateral-box" style="border-color:rgba(59,130,246,0.3);margin-top:24px;">
            <h4 style="color:#3b82f6;">Identity Document</h4>
            <p style="font-size:13px;color:#888;margin-bottom:18px;">
                Enter your National ID number and attach a photo or scan.
                An officer will review it during loan assessment.
            </p>

            <div class="form-grid">
                <div class="form-group">
                    <label for="id_number">National ID Number <span class="required">*</span></label>
                    <input type="text" id="id_number" name="id_number"
                           placeholder="e.g. 12345678"
                           maxlength="8" inputmode="numeric"
                           value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>"
                           oninput="onIdInput()">
                    <div id="idFormatMsg" style="font-size:11px;margin-top:5px;color:#444;">7 or 8 digits — numbers only</div>
                </div>
                <div class="form-group">
                    <label for="id_document">ID Document <span class="required">*</span></label>
                    <input type="file" id="id_document" name="id_document"
                           accept="image/*,.pdf"
                           style="color:#aaa;">
                    <div style="font-size:11px;color:#444;margin-top:5px;">JPG, PNG or PDF — max 5MB</div>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="btn-group" style="margin-top:24px;">
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
    const fmt      = n => n.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});

    document.getElementById('principalAmount').textContent    = 'KES ' + amount.toLocaleString();
    document.getElementById('interestAmount').textContent     = 'KES ' + fmt(interest);
    document.getElementById('durationDisplay').textContent    = duration + ' Month' + (duration !== 1 ? 's' : '');
    document.getElementById('totalAmount').textContent        = 'KES ' + fmt(total);
    document.getElementById('monthlyPayment').textContent     = 'KES ' + fmt(monthly);
    document.getElementById('penaltyRateDisplay').textContent = penRate.toFixed(1) + '% / month';
    document.getElementById('penaltyAmount').textContent      = 'KES ' + fmt(penAmt) + ' / month';
    highlightTier(amount);
}

function updateCharCount() {
    const ta  = document.getElementById('purpose');
    const cnt = document.getElementById('charCount');
    const len = ta.value.length;
    cnt.textContent = len + ' / 300';
    cnt.style.color = len >= 280 ? '#ef4444' : len >= 200 ? '#eab308' : '#444';
}

function showCollateralFields(type) {
    document.querySelectorAll('.col-fields').forEach(el => el.classList.remove('active'));
    const t = document.getElementById('fields-' + type);
    if (t) t.classList.add('active');
}

function onIdInput() {
    const val = document.getElementById('id_number').value.trim();
    const msg = document.getElementById('idFormatMsg');
    if (val === '') {
        msg.textContent = '7 or 8 digits — numbers only';
        msg.style.color = '#444';
    } else if (/^\d{7,8}$/.test(val)) {
        msg.textContent = '✓ Format looks good';
        msg.style.color = '#22c55e';
    } else {
        msg.textContent = 'Must be exactly 7 or 8 digits';
        msg.style.color = '#ef4444';
    }
}

// localStorage autosave
const SAVE_KEY    = 'loanForm_draft';
const SAVE_FIELDS = [
    'amount','duration_months','purpose','collateral_type',
    'col_brand','col_imei','col_colour',
    'col_item','col_brand2','col_serial',
    'col_furnitems','col_furcond',
    'col_biztype','col_bizgoods','col_bizval',
    'col_animal','col_count',
    'col_gname','col_gphone','col_gid','col_grel',
    'col_other','id_number'
];

function saveDraft() {
    const draft = {};
    SAVE_FIELDS.forEach(name => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el) draft[name] = el.value;
    });
    localStorage.setItem(SAVE_KEY, JSON.stringify(draft));
}
function loadDraft() {
    const raw = localStorage.getItem(SAVE_KEY);
    if (!raw) return;
    try {
        const draft = JSON.parse(raw);
        SAVE_FIELDS.forEach(name => {
            const el = document.querySelector(`[name="${name}"]`);
            if (el && draft[name] !== undefined) el.value = draft[name];
        });
        const colType = document.getElementById('collateral_type')?.value;
        if (colType) showCollateralFields(colType);
        updateCharCount();
        calculateLoan();
        onIdInput();
    } catch(e) {}
}
function clearDraft() { localStorage.removeItem(SAVE_KEY); }

document.getElementById('loanForm').addEventListener('input',  saveDraft);
document.getElementById('loanForm').addEventListener('change', saveDraft);

<?php if (!$success): ?>
window.addEventListener('DOMContentLoaded', loadDraft);
<?php else: ?>
clearDraft();
<?php endif; ?>

(function() {
    const saved = document.getElementById('collateral_type')?.value;
    if (saved) showCollateralFields(saved);
    calculateLoan();
    updateCharCount();
    onIdInput();
})();
</script>
</main>
</body>
</html>