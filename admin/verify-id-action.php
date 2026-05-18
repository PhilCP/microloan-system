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

$stmt = $conn->prepare("UPDATE loans SET id_verified = ? WHERE id = ?");
$stmt->bind_param("ii", $value, $loanId);

if ($stmt->execute()) {
    $label   = $action === 'verify' ? 'verified' : 'rejected';
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logMsg  = "Admin {$label} ID document for loan #{$loanId}";
    $logStmt->bind_param("is", $adminId, $logMsg);
    $logStmt->execute();

    echo json_encode(['success' => true, 'id_verified' => $value]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}