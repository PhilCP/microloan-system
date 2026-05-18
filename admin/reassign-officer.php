<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

header('Content-Type: application/json');

$loanId    = intval($_POST['loan_id']   ?? 0);
$officerId = intval($_POST['officer_id'] ?? 0);

if (!$loanId || !$officerId) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Confirm the target is actually an active officer
$check = $conn->prepare(
    "SELECT id FROM users WHERE id = ? AND role = 'officer' AND is_active = 1"
);
$check->bind_param("i", $officerId);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'error' => 'Officer not found or inactive']);
    exit;
}

// Confirm the loan exists
$loanCheck = $conn->prepare("SELECT id FROM loans WHERE id = ?");
$loanCheck->bind_param("i", $loanId);
$loanCheck->execute();
if (!$loanCheck->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'error' => 'Loan not found']);
    exit;
}

$user = getCurrentUser();

$stmt = $conn->prepare("UPDATE loans SET assigned_officer_id = ? WHERE id = ?");
$stmt->bind_param("ii", $officerId, $loanId);

if ($stmt->execute()) {
    $logAction = "ADMIN [{$user['id']}] reassigned loan #$loanId to officer #$officerId";
    $logStmt   = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logStmt->bind_param("is", $user['id'], $logAction);
    $logStmt->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}