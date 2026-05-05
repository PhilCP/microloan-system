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
$loanId        = intval($_POST['loan_id'] ?? 0);
$amountPaid    = floatval($_POST['amount_paid'] ?? 0);
$paymentDate   = $_POST['payment_date'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$receiptNumber = trim($_POST['receipt_number'] ?? '');
$notes         = trim($_POST['notes'] ?? '');
$recordedBy    = $user['id'];

// Basic validation
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

// Payment date cannot be in the future
if (strtotime($paymentDate) > time()) {
    echo json_encode(['success' => false, 'error' => 'Payment date cannot be in the future']);
    exit;
}

// Fetch loan details — also grab minimum_payment and duration for calculation
$loanStmt = $conn->prepare("SELECT id, borrower_id, total_amount, remaining_balance, status, duration_months FROM loans WHERE id = ?");
$loanStmt->bind_param("i", $loanId);
$loanStmt->execute();
$loanResult = $loanStmt->get_result();

if ($loanResult->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Loan not found']);
    exit;
}

$loan = $loanResult->fetch_assoc();

// Only approved loans can receive payments
if ($loan['status'] !== 'approved') {
    echo json_encode(['success' => false, 'error' => 'Can only record payments for approved loans']);
    exit;
}

// ---------------------------------------------------------------
// FIX: MINIMUM PAYMENT ENFORCEMENT (server-side)
// Minimum monthly payment = total_amount / duration_months
// Exception: if remaining balance is less than the minimum,
// allow the borrower to just pay off the remainder (final payment)
// ---------------------------------------------------------------
$minimumPayment = round($loan['total_amount'] / $loan['duration_months'], 2);
$remainingBalance = $loan['remaining_balance'];

// If the remaining balance is less than the minimum, the minimum becomes the remaining balance
$effectiveMinimum = min($minimumPayment, $remainingBalance);

if ($amountPaid < $effectiveMinimum) {
    echo json_encode([
        'success' => false,
        'error'   => 'Minimum payment is KES ' . number_format($effectiveMinimum, 2) . 
                     '. You entered KES ' . number_format($amountPaid, 2) . '.'
    ]);
    exit;
}

// Maximum: cannot pay more than what is owed
if ($amountPaid > $remainingBalance) {
    echo json_encode([
        'success' => false,
        'error'   => 'Payment amount (KES ' . number_format($amountPaid, 2) . 
                     ') exceeds remaining balance (KES ' . number_format($remainingBalance, 2) . ')'
    ]);
    exit;
}

// FIX: Verify this loan belongs to the officer recording the payment
// Prevents an officer from recording payments on another officer's loans
$ownerStmt = $conn->prepare("SELECT approved_by FROM loans WHERE id = ?");
$ownerStmt->bind_param("i", $loanId);
$ownerStmt->execute();
$ownerRow = $ownerStmt->get_result()->fetch_assoc();

if (!$ownerRow || $ownerRow['approved_by'] != $recordedBy) {
    echo json_encode(['success' => false, 'error' => 'You are not authorised to record payments for this loan']);
    exit;
}

// All checks passed — begin transaction
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
    $newBalance = $remainingBalance - $amountPaid;
    $newStatus  = ($newBalance <= 0) ? 'completed' : 'approved';

    $updateStmt = $conn->prepare("UPDATE loans SET remaining_balance = ?, status = ? WHERE id = ?");
    $updateStmt->bind_param("dsi", $newBalance, $newStatus, $loanId);

    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update loan balance');
    }

    // Log activity
    $logStmt   = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logAction = "Recorded payment of KES " . number_format($amountPaid, 2) . " for loan #{$loanId}" .
                 ($newStatus === 'completed' ? ' (Loan fully repaid)' : '');
    $logStmt->bind_param("is", $recordedBy, $logAction);
    $logStmt->execute();

    $conn->commit();

    echo json_encode([
        'success'        => true,
        'message'        => 'Payment recorded successfully',
        'repayment_id'   => $repaymentId,
        'new_balance'    => $newBalance,
        'loan_completed' => ($newStatus === 'completed')
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>