<?php
// Capture ANY stray output (PHP errors, warnings, HTML from includes)
// so we can always return clean JSON
ob_start();

// Suppress display of errors to browser — log them instead
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session only if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../config/db.php';

// Clear output buffer so auth.php HTML redirects don't leak into JSON
ob_clean();
header('Content-Type: application/json');

// ── Auth check ────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated. Please log in again.']);
    exit;
}

// Role check without calling requireRole() (which may output HTML/redirect)
$allowedRoles = ['borrower'];
$userRole     = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($userRole, $allowedRoles)) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$idNumber = trim($_POST['id_number'] ?? '');
$fileData = $_FILES['id_document'] ?? null;

// ── ID format validation ──────────────────────────────────────
if (empty($idNumber)) {
    echo json_encode(['success' => false, 'error' => 'Please enter your National ID number.']);
    exit;
}
if (!preg_match('/^\d{7,8}$/', $idNumber)) {
    echo json_encode(['success' => false, 'error' => 'Kenyan National ID must be 7 or 8 digits.']);
    exit;
}

// ── File check ────────────────────────────────────────────────
if (!$fileData || !isset($fileData['error'])) {
    echo json_encode(['success' => false, 'error' => 'No file data received by server.']);
    exit;
}
if ($fileData['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit. Increase upload_max_filesize in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was received.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension.',
    ];
    $msg = $uploadErrors[$fileData['error']] ?? 'PHP upload error code: ' . $fileData['error'];
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}
if ($fileData['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum is 5MB.']);
    exit;
}
if ($fileData['size'] === 0) {
    echo json_encode(['success' => false, 'error' => 'Uploaded file is empty.']);
    exit;
}

// ── MIME type ─────────────────────────────────────────────────
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
$mimeType     = mime_content_type($fileData['tmp_name']);
if (!in_array($mimeType, $allowedMimes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type (' . $mimeType . '). Please upload JPG, PNG, or PDF.']);
    exit;
}

// ── Upload directory ──────────────────────────────────────────
$uploadDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'id_documents' . DIRECTORY_SEPARATOR;

if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Cannot create upload folder. Please create /uploads/id_documents/ manually in your project root.']);
        exit;
    }
}
if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Upload folder is not writable: ' . $uploadDir]);
    exit;
}

// ── Save ──────────────────────────────────────────────────────
$ext      = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
$safeName = 'id_' . $userId . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $safeName;

if (!move_uploaded_file($fileData['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'move_uploaded_file failed. Check permissions on: ' . $uploadDir]);
    exit;
}

// ── Persist in session ────────────────────────────────────────
$_SESSION['verified_id_number'] = $idNumber;
$_SESSION['verified_id_doc']    = $safeName;
$_SESSION['verified_id_user']   = $userId;

echo json_encode([
    'success'  => true,
    'message'  => 'ID document uploaded. An officer will verify it during loan assessment.',
    'filename' => $safeName,
]);