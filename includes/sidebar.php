<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'borrower';

// Map pages to their nav abbreviation for active detection
$nav_items = [
    'dashboard'  => ['file' => 'dashboard.php',    'abbr' => 'DB', 'label' => 'Dashboard',  'href' => 'dashboard.php'],
    'users'      => ['file' => 'users.php',         'abbr' => 'US', 'label' => 'Users',       'href' => '/microloan-system/admin/users.php'],
    'logs'       => ['file' => 'activity_logs.php', 'abbr' => 'AU', 'label' => 'Audit Logs',  'href' => 'activity_logs.php'],
    'reports'    => ['file' => 'reports.php',       'abbr' => 'RP', 'label' => 'Reports',     'href' => 'reports.php'],
];
?>
<link rel="stylesheet" href="/microloan-system/assets/css/sidebar.css">

<div class="sidebar-toggle" id="sidebarToggle">MENU</div>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-top">
        <div class="logo">
            <div class="logo-box">M</div>
            <div class="logo-text">
                <strong>MICROLOAN</strong>
                <small><?= strtoupper($user_role); ?> ACCESS</small>
            </div>
        </div>
        <button class="collapse-btn" id="collapseBtn" aria-label="Collapse sidebar">&#9664;</button>
        <button class="mobile-close" id="mobileClose" aria-label="Close menu">&#10005;</button>
    </div>

    <nav class="nav-wrapper">

        <ul class="menu">
            <li class="<?= $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <span class="abbr-box">DB</span>
                    <span class="text">Dashboard</span>
                </a>
                <span class="nav-tooltip">Dashboard</span>
            </li>

            <?php if ($user_role === 'admin'): ?>
            <li class="<?= in_array($current_page, ['users.php','add_user.php','edit_user.php']) ? 'active' : ''; ?>">
                <a href="/microloan-system/admin/users.php">
                    <span class="abbr-box">US</span>
                    <span class="text">Users</span>
                </a>
                <span class="nav-tooltip">Users</span>
            </li>
            <?php endif; ?>

            <?php if ($user_role === 'admin' || $user_role === 'officer'): ?>
            <li class="<?= $current_page === 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php">
                    <span class="abbr-box">RP</span>
                    <span class="text">Reports</span>
                </a>
                <span class="nav-tooltip">Reports</span>
            </li>
            <?php endif; ?>
        </ul>

        <?php if ($user_role === 'admin'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-label">Admin</div>
        <ul class="menu">
            <li class="<?= $current_page === 'activity_logs.php' ? 'active' : ''; ?>">
                <a href="activity_logs.php">
                    <span class="abbr-box">AU</span>
                    <span class="text">Audit Logs</span>
                </a>
                <span class="nav-tooltip">Audit Logs</span>
            </li>
        </ul>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-link">
            <span class="abbr-box">LO</span>
            <span class="text">Logout</span>
        </a>
        <span class="nav-tooltip">Logout</span>
    </div>

</aside>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar     = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('collapseBtn');
    const toggleBtn   = document.getElementById('sidebarToggle');
    const closeBtn    = document.getElementById('mobileClose');

    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            collapseBtn.innerHTML = sidebar.classList.contains('collapsed') ? '&#9654;' : '&#9664;';

            const main = document.querySelector('.dashboard-main');
            if (main) {
                main.style.marginLeft = sidebar.classList.contains('collapsed') ? '52px' : '220px';
            }
        });
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.add('active');
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            sidebar.classList.remove('active');
        });
    }
});
</script>