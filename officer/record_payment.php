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

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

// Get form data
$loanId = intval($_POST['loan_id'] ?? 0);
$amountPaid = floatval($_POST['amount_paid'] ?? 0);
$paymentDate = $_POST['payment_date'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$receiptNumber = trim($_POST['receipt_number'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$recordedBy = $user['id'];

// Validate inputs
if ($loanId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid loan ID']);
    exit;
}

if ($amountPaid <= 0) {
    echo json_encode(['success' => false, 'error' => 'Payment amount must be greater than 0']);
    exit;
}

if (empty($paymentDate)) {
    echo json_encode(['success' => false, 'error' => 'Payment date is required']);
    exit;
}

if (empty($paymentMethod)) {
    echo json_encode(['success' => false, 'error' => 'Payment method is required']);
    exit;
}

// Validate payment date (not in future)
$paymentDateTime = strtotime($paymentDate);
if ($paymentDateTime > time()) {
    echo json_encode(['success' => false, 'error' => 'Payment date cannot be in the future']);
    exit;
}

// Check if loan exists and get details
$loanStmt = $conn->prepare("SELECT id, borrower_id, total_amount, remaining_balance, status FROM loans WHERE id = ?");
$loanStmt->bind_param("i", $loanId);
$loanStmt->execute();
$loanResult = $loanStmt->get_result();

if ($loanResult->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Loan not found']);
    exit;
}

$loan = $loanResult->fetch_assoc();

// Check if loan is approved
if ($loan['status'] !== 'approved') {
    echo json_encode(['success' => false, 'error' => 'Can only record payments for approved loans']);
    exit;
}

// Check if payment amount exceeds remaining balance
if ($amountPaid > $loan['remaining_balance']) {
    echo json_encode([
        'success' => false, 
        'error' => 'Payment amount (KES ' . number_format($amountPaid, 2) . ') exceeds remaining balance (KES ' . number_format($loan['remaining_balance'], 2) . ')'
    ]);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Insert repayment record
    $insertStmt = $conn->prepare("INSERT INTO repayments (loan_id, amount_paid, payment_date, payment_method, receipt_number, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insertStmt->bind_param("idsssis", $loanId, $amountPaid, $paymentDate, $paymentMethod, $receiptNumber, $recordedBy, $notes);
    
    if (!$insertStmt->execute()) {
        throw new Exception('Failed to record payment');
    }
    
    $repaymentId = $conn->insert_id;
    
    // Update loan remaining balance
    $newBalance = $loan['remaining_balance'] - $amountPaid;
    $newStatus = ($newBalance <= 0) ? 'completed' : 'approved';
    
    $updateStmt = $conn->prepare("UPDATE loans SET remaining_balance = ?, status = ? WHERE id = ?");
    $updateStmt->bind_param("dsi", $newBalance, $newStatus, $loanId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update loan balance');
    }
    
    // Log activity
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logAction = "Recorded payment of KES " . number_format($amountPaid, 2) . " for loan #{$loanId}" . ($newStatus === 'completed' ? ' (Loan fully repaid)' : '');
    $logStmt->bind_param("is", $recordedBy, $logAction);
    $logStmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded successfully',
        'repayment_id' => $repaymentId,
        'new_balance' => $newBalance,
        'loan_completed' => ($newStatus === 'completed')
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>