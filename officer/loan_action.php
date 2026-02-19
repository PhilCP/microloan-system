<?php
session_start();
require_once '../includes/auth.php';
requireRole('officer');
require_once '../config/db.php';

header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loanId = intval($_POST['loan_id'] ?? 0);
    $action = $_POST['loan_action'] ?? '';
    $remarks = $_POST['admin_remarks'] ?? '';
    $officerId = $_SESSION['user_id'];

    if ($action === 'remark_only') {
        // Update ONLY remarks
        $stmt = $conn->prepare("UPDATE loans SET admin_remarks = ? WHERE id = ?");
        $stmt->bind_param("si", $remarks, $loanId);
    } else {
        // Update status AND remarks
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE loans SET status = ?, approved_by = ?, admin_remarks = ? WHERE id = ?");
        $stmt->bind_param("sisi", $status, $officerId, $remarks, $loanId);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}