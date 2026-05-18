<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Please login to access this page";
        header("Location: /microloan-system/login.php");
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireRole($role) {
    requireLogin();

    // Re-check is_active on EVERY protected page load.
    // A session persists even after an admin revokes access in the DB,
    // so we must query the live value here — not trust the session alone.
    global $conn;
    $stmt = $conn->prepare("SELECT role, is_active FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if (!$u || !$u['is_active']) {
        // Destroy the session so the user is fully logged out
        session_unset();
        session_destroy();
        header("Location: /microloan-system/login.php?reason=suspended");
        exit();
    }

    if ($u['role'] !== $role) {
        $_SESSION['error'] = "Access denied";
        header("Location: /microloan-system/index.php");
        exit();
    }
}

function loginUser($email, $password) {
    global $conn;

    if (empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Please fill in all fields'];
    }

    // Also fetch is_active so we can block suspended users at login
    $stmt = $conn->prepare("SELECT id, full_name, email, password, role, is_active FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    // Block login for suspended accounts
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Your account has been suspended. Please contact an administrator.'];
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];

    logActivity($user['id'], "User logged in");

    return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
}

function registerUser($data) {
    global $conn;

    $required = ['full_name', 'email', 'phone', 'password', 'confirm_password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => 'Please fill in all fields'];
        }
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }

    if (!preg_match('/^\+?254[0-9]{9}$/', $data['phone'])) {
        return ['success' => false, 'message' => 'Invalid phone. Use: +254XXXXXXXXX'];
    }

    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }

    if ($data['password'] !== $data['confirm_password']) {
        return ['success' => false, 'message' => 'Passwords do not match'];
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'borrower')");
    $stmt->bind_param("ssss", $data['full_name'], $data['email'], $data['phone'], $hashed_password);

    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        logActivity($user_id, "New user registered");
        return ['success' => true, 'message' => 'Registration successful! Please login.'];
    }

    return ['success' => false, 'message' => 'Registration failed'];
}

function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], "User logged out");
    }
    session_unset();
    session_destroy();
    header("Location: /microloan-system/login.php");
    exit();
}

function getDashboardURL($role) {
    $urls = [
        'admin'    => '/microloan-system/admin/dashboard.php',
        'officer'  => '/microloan-system/officer/dashboard.php',
        'borrower' => '/microloan-system/borrower/dashboard.php'
    ];
    return $urls[$role] ?? '/microloan-system/index.php';
}

function redirectToDashboard() {
    if (isset($_SESSION['role'])) {
        header("Location: " . getDashboardURL($_SESSION['role']));
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;

    global $conn;
    $stmt = $conn->prepare("SELECT id, full_name, email, phone, role, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

?>