<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$loanId  = intval($_POST['loan_id'] ?? 0);
$action  = $_POST['action'] ?? ''; // 'verify' | 'reject_id'
$adminId = $_SESSION['user_id'];

if (!$loanId || !in_array($action, ['verify', 'reject_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// 1 = verified, -1 = rejected
$value = $action === 'verify' ? 1 : -1;

if ($action === 'reject_id') {
    // Rejecting the ID also rejects the loan outright, and only if it's
    // still pending (don't clobber an already-approved/completed loan).
    $remarkNote = "ID verification failed — the submitted ID number does not match the uploaded document.";

    $stmt = $conn->prepare(
        "UPDATE loans
            SET id_verified   = ?,
                status         = CASE WHEN status = 'pending' THEN 'rejected' ELSE status END,
                admin_remarks   = CASE
                                      WHEN status = 'pending' AND (admin_remarks IS NULL OR admin_remarks = '')
                                      THEN ?
                                      ELSE admin_remarks
                                  END,
                approved_by    = CASE WHEN status = 'pending' THEN ? ELSE approved_by END
          WHERE id = ?"
    );
    $stmt->bind_param("isii", $value, $remarkNote, $adminId, $loanId);
} else {
    // Verify just sets the flag; doesn't change loan status
    $stmt = $conn->prepare("UPDATE loans SET id_verified = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $loanId);
}

if ($stmt->execute()) {
    $label   = $action === 'verify' ? 'verified' : 'rejected';
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logMsg  = "Admin {$label} ID document for loan #{$loanId}";
    $logStmt->bind_param("is", $adminId, $logMsg);
    $logStmt->execute();

    // Fetch the resulting loan status so the frontend can react (e.g. reload)
    $statusStmt = $conn->prepare("SELECT status FROM loans WHERE id = ?");
    $statusStmt->bind_param("i", $loanId);
    $statusStmt->execute();
    $statusRow = $statusStmt->get_result()->fetch_assoc();

    echo json_encode([
        'success'     => true,
        'id_verified' => $value,
        'loan_status' => $statusRow['status'] ?? null,
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}