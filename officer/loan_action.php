<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$loanId    = intval($_POST['loan_id']     ?? 0);
$action    = trim($_POST['loan_action']   ?? '');
$remarks   = trim($_POST['admin_remarks'] ?? '');
$officerId = $_SESSION['user_id'];

if (!$loanId) {
    echo json_encode(['success' => false, 'error' => 'Invalid loan ID']);
    exit;
}

// ── If approving, check ID has been verified by admin first ──
if ($action === 'approve') {
    $chk = $conn->prepare("SELECT id_verified, id_number, id_document FROM loans WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $loanId);
    $chk->execute();
    $loanData = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$loanData) {
        echo json_encode(['success' => false, 'error' => 'Loan not found.']);
        exit;
    }

    $hasIdInfo  = !empty($loanData['id_number']) || !empty($loanData['id_document']);
    $isVerified = (int)($loanData['id_verified'] ?? 0) === 1;

    if ($hasIdInfo && !$isVerified) {
        echo json_encode([
            'success' => false,
            'error'   => 'Cannot approve — the borrower\'s ID document has not been verified by an admin yet. Please ask an admin to verify the ID first.'
        ]);
        exit;
    }
}

// ── Build and execute the query ───────────────────────────────
if ($action === 'remark_only') {
    $stmt = $conn->prepare("UPDATE loans SET admin_remarks = ? WHERE id = ?");
    $stmt->bind_param("si", $remarks, $loanId);

} elseif ($action === 'approve') {
    $stmt = $conn->prepare(
        "UPDATE loans
         SET status        = 'approved',
             approved_by   = ?,
             admin_remarks = ?,
             approval_date = NOW(),
             due_date      = DATE_ADD(NOW(), INTERVAL duration_months MONTH)
         WHERE id = ?"
    );
    $stmt->bind_param("isi", $officerId, $remarks, $loanId);

} elseif ($action === 'reject') {
    $stmt = $conn->prepare(
        "UPDATE loans
         SET status        = 'rejected',
             approved_by   = ?,
             admin_remarks = ?
         WHERE id = ?"
    );
    $stmt->bind_param("isi", $officerId, $remarks, $loanId);

} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

if ($stmt->execute()) {
    $actionLabel = match($action) {
        'approve'     => 'Approved',
        'reject'      => 'Rejected',
        'remark_only' => 'Added notes to',
        default       => 'Updated'
    };
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logMsg  = "{$actionLabel} loan #{$loanId}" . ($remarks ? " — \"{$remarks}\"" : '');
    $logStmt->bind_param("is", $officerId, $logMsg);
    $logStmt->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}