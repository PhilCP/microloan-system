<?php
/**
 * Database Configuration
 * Microloan Management System
 * GitHub: https://github.com/PhilCP/microloan-system
 */

// Database credentials for XAMPP on Mac
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');  // Default XAMPP has no password
define('DB_NAME', 'microloan_system');

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

/**
 * Helper function to execute prepared statements
 */
function executeQuery($query, $types = '', $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt;
}

/**
 * Sanitize user input
 */
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

/**
 * Log user activity
 */
function logActivity($user_id, $action) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    return $stmt->execute();
}
?>