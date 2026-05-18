<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }

$loan_id = intval($_POST['loan_id'] ?? 0);
if (!$loan_id) { echo json_encode(['success' => false, 'error' => 'Invalid loan ID']); exit; }

// Verify loan belongs to this borrower and is still pending
$stmt = $conn->prepare("SELECT id FROM loans WHERE id = ? AND borrower_id = ? AND status = 'pending'");
$stmt->bind_param("ii", $loan_id, $user['id']);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Loan not found or cannot be cancelled.']);
    exit;
}

$del = $conn->prepare("DELETE FROM loans WHERE id = ? AND borrower_id = ? AND status = 'pending'");
$del->bind_param("ii", $loan_id, $user['id']);

if ($del->execute()) {
    $log = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $action = "Cancelled pending loan application #$loan_id";
    $log->bind_param("is", $user['id'], $action);
    $log->execute();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
?>