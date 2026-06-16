<?php
session_start();
require_once '../includes/auth.php';
requireRole('admin');
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";

// Access Control (to lock/unlock)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $targetId = intval($_GET['id']);
    $action   = $_GET['action'];
    $newStatus = ($action === 'activate') ? 1 : 0;

    if ($targetId !== $user['id']) {

        // If revoking a borrower, block if they have an outstanding balance
        if ($newStatus === 0) {
            $debtCheck = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM loans
                 WHERE borrower_id = ? AND status = 'approved' AND remaining_balance > 0"
            );
            $debtCheck->bind_param("i", $targetId);
            $debtCheck->execute();
            $debtRow = $debtCheck->get_result()->fetch_assoc();

            if ($debtRow['cnt'] > 0) {
                header("Location: users.php?msg=has_debt");
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStatus, $targetId);

        if ($stmt->execute()) {
            $statusText = ($newStatus === 1 ? "RESTORED" : "SUSPENDED");
            $logAction  = "ADMIN [{$user['id']}] $statusText access for UID #$targetId";

            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $logStmt->bind_param("is", $user['id'], $logAction);
            $logStmt->execute();
        }
    }
    header("Location: users.php?msg=state_updated");
    exit;
}

// Filter Logic
$search     = $_GET['search'] ?? '';
$filterRole = $_GET['role']   ?? '';

$query  = "SELECT id, full_name, email, role, is_active, created_at FROM users WHERE 1=1";
$params = [];
$types  = "";

if ($search) {
    $query       .= " AND (full_name LIKE ? OR email LIKE ?)";
    $searchParam  = "%$search%";
    $params[]     = $searchParam;
    $params[]     = $searchParam;
    $types       .= "ss";
}
if ($filterRole) {
    $query    .= " AND role = ?";
    $params[]  = $filterRole;
    $types    .= "s";
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
    <link rel="stylesheet" href="../assets/css/users-management.css">
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; padding: 30px;">
        <?php include '../includes/dashboard_header.php'; ?>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'has_debt'): ?>
                <div style="background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.3);
                            color:#ef4444; padding:12px 18px; border-radius:8px; margin-bottom:18px;
                            font-size:13px; font-weight:700;">
                     Cannot revoke — this borrower has an outstanding loan balance. Settle the debt first.
                </div>
            <?php elseif ($_GET['msg'] === 'state_updated'): ?>
                <div style="background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.25);
                            color:#22c55e; padding:12px 18px; border-radius:8px; margin-bottom:18px;
                            font-size:13px; font-weight:700;">
                    User access state updated.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="management-header">
            <a href="add_user.php" class="btn-op btn-deploy">Deploy New User</a>
        </div>

        <form method="GET" class="search-bar">
            <input type="text" name="search" class="input-dark"
                   placeholder="Search Identity (Name/Email)..."
                   value="<?php echo htmlspecialchars($search); ?>"
                   style="flex-grow: 1;">
            <select name="role" class="input-dark">
                <option value="">All Designations</option>
                <option value="admin"    <?php echo $filterRole == 'admin'    ? 'selected' : ''; ?>>Admin</option>
                <option value="officer"  <?php echo $filterRole == 'officer'  ? 'selected' : ''; ?>>Officer</option>
                <option value="borrower" <?php echo $filterRole == 'borrower' ? 'selected' : ''; ?>>Borrower</option>
            </select>
            <button type="submit" class="btn-op">Apply Filters</button>
            <?php if ($search || $filterRole): ?>
                <a href="users.php" class="btn-op" style="border-color:#ef4444; color:#ef4444;">Clear</a>
            <?php endif; ?>
        </form>

        <table class="user-table">
            <thead>
                <tr>
                    <th>Identity Reference</th>
                    <th>Designation</th>
                    <th>Access State</th>
                    <th>Registry Date</th>
                    <th style="text-align:right;">Authorization Ops</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($usersResult->num_rows > 0): ?>
                    <?php while ($u = $usersResult->fetch_assoc()): ?>

                        <?php
                        // Pre-check outstanding debt for borrowers so we know
                        // whether to render the REVOKE button or a locked state
                        $hasDebt = false;
                        if ($u['role'] === 'borrower' && $u['is_active']) {
                            $debtStmt = $conn->prepare(
                                "SELECT COUNT(*) AS cnt FROM loans
                                 WHERE borrower_id = ? AND status = 'approved' AND remaining_balance > 0"
                            );
                            $debtStmt->bind_param("i", $u['id']);
                            $debtStmt->execute();
                            $hasDebt = (bool) $debtStmt->get_result()->fetch_assoc()['cnt'];
                        }
                        ?>

                        <tr>
                            <td>
                                <div style="font-weight:800; color:#fff;"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                <div style="color:#444; font-size:11px; font-family:monospace;"><?php echo htmlspecialchars($u['email']); ?></div>
                            </td>
                            <td>
                                <span class="role-pill role-<?php echo $u['role']; ?>">
                                    <?php echo $u['role']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="status-indicator">
                                    <?php if ($u['is_active']): ?>
                                        <span style="color:#22c55e">GRANTED</span>
                                    <?php else: ?>
                                        <span style="color:#ef4444">REVOKED</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="color:#444; font-size:12px; font-family:monospace;">
                                <?php echo date('Y.m.d', strtotime($u['created_at'])); ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex; gap:5px; justify-content:flex-end;">

                                    <?php if ($u['role'] == 'borrower'): ?>
                                        <a href="borrower_statement.php?id=<?php echo $u['id']; ?>"
                                           class="btn-op btn-story" title="Financial Story">STORY</a>
                                    <?php endif; ?>

                                    <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn-op">EDIT</a>

                                    <?php if ($u['id'] != $user['id']): ?>
                                        <?php if ($u['is_active']): ?>
                                            <?php if ($hasDebt): ?>
                                                <!-- Borrower with active debt — cannot revoke -->
                                                <span class="btn-op"
                                                      style="opacity:0.35; cursor:not-allowed; color:#f59e0b;"
                                                      title="Borrower has outstanding debt — cannot revoke">
                                                    DEBT 💰
                                                </span>
                                            <?php else: ?>
                                                <a href="?action=deactivate&id=<?php echo $u['id']; ?>"
                                                   class="btn-op" style="color:#ef4444;"
                                                   onclick="return confirm('Revoke access for this user?')">
                                                    REVOKE
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="?action=activate&id=<?php echo $u['id']; ?>"
                                               class="btn-op" style="color:#22c55e;">RESTORE</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="btn-op" style="opacity:0.3; cursor:not-allowed;">LOCKED</span>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:80px; color:#222;
                                               text-transform:uppercase; font-weight:900;">
                            Registry is empty.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <script>
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('dashboardMain').style.marginLeft =
                document.getElementById('sidebar').classList.contains('active') ? '240px' : '0';
        });
    </script>
</body>
</html>