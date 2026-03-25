<?php


require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Test - Microloan System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #1a202c;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
        }
        .success {
            background: #f0fdf4;
            border-color: #22c55e;
            color: #166534;
        }
        .error {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }
        .info {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1e40af;
        }
        .status-title {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-admin { background: #fef3c7; color: #92400e; }
        .badge-officer { background: #dbeafe; color: #1e40af; }
        .badge-borrower { background: #e0e7ff; color: #3730a3; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .credentials {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .next-steps {
            background: #fefce8;
            border-left: 5px solid #eab308;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        .next-steps h3 {
            color: #854d0e;
            margin-bottom: 15px;
        }
        .next-steps ol {
            margin-left: 20px;
            color: #713f12;
        }
        .next-steps li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏦 Microloan Management System</h1>
        <p class="subtitle">Database Connection Test - XAMPP on macOS</p>

        <?php if ($conn->ping()): ?>
            <!-- Connection Success -->
            <div class="status-box success">
                <div class="status-title">✅ Database Connected Successfully</div>
                <p><strong>Database:</strong> <?php echo DB_NAME; ?></p>
                <p><strong>Host:</strong> <?php echo DB_SERVER; ?></p>
                <p><strong>Character Set:</strong> <?php echo $conn->character_set_name(); ?></p>
            </div>

            <!-- Check Tables -->
            <?php
            $tables = ['users', 'loans', 'repayments', 'activity_logs'];
            $all_exist = true;
            ?>
            <div class="status-box info">
                <div class="status-title">📊 Database Tables</div>
                <?php foreach ($tables as $table): ?>
                    <?php
                    $result = $conn->query("SHOW TABLES LIKE '$table'");
                    if ($result && $result->num_rows > 0) {
                        $count = $conn->query("SELECT COUNT(*) as count FROM $table")->fetch_assoc()['count'];
                        echo "✓ <strong>$table</strong> - $count record(s)<br>";
                    } else {
                        echo "✗ <strong>$table</strong> - Not found<br>";
                        $all_exist = false;
                    }
                    ?>
                <?php endforeach; ?>
            </div>

            <?php if ($all_exist): ?>
                <!-- Sample Users -->
                <div class="status-box success">
                    <div class="status-title">👥 Sample Users</div>
                    <?php
                    $users = $conn->query("SELECT full_name, email, role FROM users ORDER BY id LIMIT 5");
                    if ($users && $users->num_rows > 0):
                    ?>
                        <table>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                            </tr>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Sample Loans -->
                <div class="status-box info">
                    <div class="status-title">💰 Sample Loans</div>
                    <?php
                    $loans = $conn->query("SELECT l.amount, l.status, u.full_name FROM loans l JOIN users u ON l.borrower_id = u.id LIMIT 5");
                    if ($loans && $loans->num_rows > 0):
                    ?>
                        <table>
                            <tr>
                                <th>Borrower</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                            <?php while ($loan = $loans->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                    <td>KES <?php echo number_format($loan['amount'], 2); ?></td>
                                    <td><span class="badge badge-<?php echo $loan['status']; ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Login Credentials -->
                <div class="status-box success">
                    <div class="status-title">🔑 Default Login Credentials</div>
                    <p>Use these to test the system:</p>
                    <div class="credentials">
<strong>ADMIN ACCOUNT:</strong>
Email: admin@microloan.com
Password: admin123

<strong>LOAN OFFICER:</strong>
Email: officer@microloan.com
Password: officer123

<strong>BORROWER:</strong>
Email: jane@example.com
Password: borrower123
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Connection Failed -->
            <div class="status-box error">
                <div class="status-title">❌ Database Connection Failed</div>
                <p><strong>Error:</strong> <?php echo $conn->connect_error; ?></p>
                <p style="margin-top: 15px;"><strong>Troubleshooting:</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Make sure XAMPP is running</li>
                    <li>Check that Apache and MySQL are started (green indicators)</li>
                    <li>Verify database credentials in config/db.php</li>
                    <li>Ensure database 'microloan_system' exists</li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Next Steps -->
        <div class="next-steps">
            <h3>🚀 Next Steps</h3>
            <ol>
                <li>Clone your GitHub repo to XAMPP's htdocs folder</li>
                <li>Place <code>db.php</code> in the <code>config/</code> folder</li>
                <li>Create the project folder structure</li>
                <li>Start building the authentication system (Week 2)</li>
                <li>Push your changes to GitHub regularly</li>
            </ol>
        </div>
    </div>
</body>
</html>