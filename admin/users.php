<?php
/
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";

//  1. Tactical Access Control (Lock/Unlock)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $targetId = intval($_GET['id']);
    $action = $_GET['action'];
    $newStatus = ($action === 'activate') ? 1 : 0;

    // Safety Protocol: Prevent Admin from locking their own account
    if ($targetId !== $user['id']) { 
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStatus, $targetId);
        
        if ($stmt->execute()) {
            $statusText = ($newStatus === 1 ? "RESTORED" : "SUSPENDED");
            $logAction = "ADMIN [{$user['id']}] $statusText access for UID #$targetId";
            
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $logStmt->bind_param("is", $user['id'], $logAction);
            $logStmt->execute();
        }
    }
    header("Location: users.php?msg=state_updated");
    exit;
}

// 2. Registry Filtration Logic
$search = $_GET['search'] ?? '';
$filterRole = $_GET['role'] ?? '';

// Using Prepared Statement for search safety
$query = "SELECT id, full_name, email, role, is_active, created_at FROM users WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $query .= " AND (full_name LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}
if ($filterRole) {
    $query .= " AND role = ?";
    $params[] = $filterRole;
    $types .= "s";
}
$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$usersResult = $stmt->get_result();

$pageTitle = "Personnel Registry";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        body { background: #000; color: #fff; }
        
        /* Header & Search */
        .management-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .search-bar { 
            display: flex; gap: 10px; background: #0a0a0a; padding: 20px; 
            border-radius: 12px; border: 1px solid #1a1a1a; margin-bottom: 30px;
        }
        .input-dark { 
            background: #000; border: 1px solid #222; color: #fff; 
            padding: 12px; border-radius: 6px; font-size: 14px;
        }
        .input-dark:focus { border-color: #f0a500; outline: none; }
        
        /* Personnel Table */
        .user-table { width: 100%; border-collapse: collapse; background: #0a0a0a; border-radius: 12px; overflow: hidden; border: 1px solid #1a1a1a; }
        .user-table th { background: #111; color: #444; padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .user-table td { padding: 18px 15px; border-bottom: 1px solid #111; font-size: 14px; vertical-align: middle; }
        .user-table tr:hover { background: #0f0f0f; }
        
        /* Designations */
        .role-pill { font-size: 10px; padding: 4px 10px; border-radius: 4px; font-weight: 900; text-transform: uppercase; border: 1px solid currentColor; }
        .role-admin { color: #f0a500; background: rgba(240,165,0,0.05); }
        .role-officer { color: #3b82f6; background: rgba(59,130,246,0.05); }
        .role-borrower { color: #666; background: rgba(255,255,255,0.02); }
        
        /* Status Tracking */
        .status-indicator { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 800; }
        .dot { height: 6px; width: 6px; border-radius: 50%; }
        .dot-active { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
        .dot-locked { background: #ef4444; }

        /* Ops Buttons */
        .btn-op { 
            text-decoration: none; font-size: 11px; padding: 8px 14px; border-radius: 6px; 
            font-weight: 800; transition: 0.3s; border: 1px solid #222; color: #fff; display: inline-block;
        }
        .btn-op:hover { border-color: #f0a500; color: #f0a500; }
        .btn-deploy { background: #f0a500; color: #000 !important; border: none; text-transform: uppercase; }
        .btn-deploy:hover { background: #fff; transform: translateY(-2px); }
        
        .btn-story { background: rgba(240, 165, 0, 0.1); color: #f0a500 !important; border-color: #f0a500; }
        .btn-story:hover { background: #f0a500; color: #000 !important; }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; padding: 30px;">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="management-header">
            <div>
                <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">Personnel Registry</h2>
                <p style="color: #444; font-size: 13px;">Modify operational permissions and verify personnel identities.</p>
            </div>
            <a href="add_user.php" class="btn-op btn-deploy">Deploy New User</a>
        </div>

        <form method="GET" class="search-bar">
            <input type="text" name="search" class="input-dark" placeholder="Search Identity (Name/Email)..." value="<?php echo htmlspecialchars($search); ?>" style="flex-grow: 1;">
            <select name="role" class="input-dark">
                <option value="">All Designations</option>
                <option value="admin" <?php echo $filterRole == 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="officer" <?php echo $filterRole == 'officer' ? 'selected' : ''; ?>>Officer</option>
                <option value="borrower" <?php echo $filterRole == 'borrower' ? 'selected' : ''; ?>>Borrower</option>
            </select>
            <button type="submit" class="btn-op">Apply Filters</button>
            <?php if($search || $filterRole): ?>
                <a href="users.php" class="btn-op" style="border-color: #ef4444; color: #ef4444;">Clear</a>
            <?php endif; ?>
        </form>

        <table class="user-table">
            <thead>
                <tr>
                    <th>Identity Reference</th>
                    <th>Designation</th>
                    <th>Access State</th>
                    <th>Registry Date</th>
                    <th style="text-align: right;">Authorization Ops</th>
                </tr>
            </thead>
            <tbody>
                <?php if($usersResult->num_rows > 0): ?>
                    <?php while($u = $usersResult->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 800; color: #fff;"><?php echo htmlspecialchars($u['full_name']); ?></div>
                            <div style="color: #444; font-size: 11px; font-family: monospace;"><?php echo htmlspecialchars($u['email']); ?></div>
                        </td>
                        <td>
                            <span class="role-pill role-<?php echo $u['role']; ?>">
                                <?php echo $u['role']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="status-indicator">
                                <?php if($u['is_active']): ?>
                                    <span class="dot dot-active"></span> <span style="color:#22c55e">GRANTED</span>
                                <?php else: ?>
                                    <span class="dot dot-locked"></span> <span style="color:#ef4444">REVOKED</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="color: #444; font-size: 12px; font-family: monospace;">
                            <?php echo date('Y.m.d', strtotime($u['created_at'])); ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 5px; justify-content: flex-end;">
                                <?php if($u['role'] == 'borrower'): ?>
                                    <a href="borrower_statement.php?id=<?php echo $u['id']; ?>" class="btn-op btn-story" title="Financial Story">STORY</a>
                                <?php endif; ?>
                                
                                <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn-op">EDIT</a>

                                <?php if($u['id'] != $user['id']): ?>
                                    <?php if($u['is_active']): ?>
                                        <a href="?action=deactivate&id=<?php echo $u['id']; ?>" class="btn-op" style="color: #ef4444;" onclick="return confirm('Revoke access for this user?')">REVOKE</a>
                                    <?php else: ?>
                                        <a href="?action=activate&id=<?php echo $u['id']; ?>" class="btn-op" style="color: #22c55e;">RESTORE</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="btn-op" style="opacity: 0.3; cursor: not-allowed;">LOCKED</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:80px; color:#222; text-transform: uppercase; font-weight: 900;">Registry is empty or filter returned no results.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <script>
        // Smooth Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('dashboardMain').style.marginLeft = 
                document.getElementById('sidebar').classList.contains('active') ? '240px' : '0';
        });
    </script>
</body>
</html>