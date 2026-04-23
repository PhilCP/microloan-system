<?php
//Connection Settings (XAMPP on Mac defaults)
$host = '127.0.0.1';
$db   = 'microloan_system';
$user = 'root';
$pass = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h2> Connected to Microloan System</h2>";

    //Display Users based on your actual columns
    echo "<h3>User Registry</h3>";
    $stmt = $pdo->query("SELECT id, full_name, email, password, role FROM users");
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>
            <tr style='background: #eee;'>
                <th>Name</th><th>Email (Login)</th><th>Role</th><th>Password Hash</th>
            </tr>";

    while ($row = $stmt->fetch()) {
        echo "<tr>
                <td>{$row['full_name']}</td>
                <td>{$row['email']}</td>
                <td>{$row['role']}</td>
                <td><small><code>{$row['password']}</code></small></td>
              </tr>";
    }
    echo "</table>";

    // Hash Tester
    echo "<h3>Password Hash Test</h3>";
    $test_pass = 'admin123';
    $sample_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    
    if (password_verify($test_pass, $sample_hash)) {
        echo "<p style='color: green;'>✔ Verification Success: 'admin123' matches the database hash.</p>";
    } else {
        echo "<p style='color: red;'>✘ Verification Failed.</p>";
    }

} catch (PDOException $e) {
    echo "<h2 style='color: red;'> Connection failed</h2>";
    echo "Error: " . $e->getMessage();
}
?>