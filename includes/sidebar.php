<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'borrower';
?>

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
        
        <div class="collapse-btn" id="collapseBtn">&lt;</div>
        
        <div class="mobile-close" id="mobileClose">X</div>
    </div>

    <nav class="nav-wrapper">
        <ul class="menu">
            <li class="<?= $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <span class="icon">📊</span>
                    <span class="text">Dashboard</span>
                </a>
            </li>

            <?php if ($user_role === 'admin'): ?>
            <li class="<?= in_array($current_page, ['users.php','add_user.php','edit_user.php']) ? 'active' : ''; ?>">
                <a href="users.php">
                    <span class="icon">👥</span>
                    <span class="text">Users</span>
                </a>
            </li>
            <li class="<?= $current_page == 'activity_logs.php' ? 'active' : ''; ?>">
                <a href="activity_logs.php">
                    <span class="icon">📜</span>
                    <span class="text">Audit Logs</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($user_role === 'admin' || $user_role === 'officer'): ?>
            <li class="<?= $current_page == 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php">
                    <span class="icon">📈</span>
                    <span class="text">Reports</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- <li class="<?= $current_page == 'pending-loans.php' ? 'active' : ''; ?>">
                <a href="pending-loans.php">
                    <span class="icon">💰</span>
                    <span class="text"><?= ($user_role == 'borrower') ? 'My Loans' : 'Loan Queue'; ?></span>
                </a>
            </li> -->
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-link">
            <span class="icon">🔒</span>
            <span class="text">Logout</span>
        </a>
    </div>
</aside>

<style>
    /* ===== SIDEBAR BASE ===== */
    .sidebar {
        width: 240px;
        height: 100vh;
        background: #000;
        border-right: 1px solid #1a1a1a;
        position: fixed;
        left: 0; top: 0;
        display: flex;
        flex-direction: column;
        transition: width 0.3s ease, transform 0.3s ease;
        z-index: 3000;
    }

    .sidebar.collapsed { width: 70px; }

    /* Branding Section */
    .sidebar-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px 15px;
        position: relative;
    }

    .logo { display: flex; align-items: center; gap: 10px; overflow: hidden; }
    .logo-box {
        background: #f0a500;
        color: #000;
        min-width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border-radius: 4px;
    }

    .logo-text { white-space: nowrap; }
    .logo-text strong { display: block; font-size: 13px; }
    .logo-text small { font-size: 9px; color: #555; }

    /* Control Buttons */
    .collapse-btn, .mobile-close {
        cursor: pointer;
        color: #f0a500;
        font-weight: bold;
        font-family: monospace;
        font-size: 18px;
        padding: 5px;
    }

    .mobile-close { display: none; } /* Hidden on desktop */

    /* Navigation */
    .nav-wrapper { flex-grow: 1; overflow-x: hidden; }
    .menu { list-style: none; padding: 0; margin: 0; }
    .menu li a {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        text-decoration: none;
        color: #777;
        white-space: nowrap;
    }

    .menu li.active a { color: #f0a500; background: #0a0a0a; border-left: 3px solid #f0a500; }
    .menu li a:hover { color: #fff; background: #0a0a0a; }
    .icon { min-width: 30px; font-size: 18px; }

    /* Footer */
    .sidebar-footer { padding: 20px; border-top: 1px solid #111; }
    .logout-link { color: #ef4444 !important; }

    /* Collapsed State Hide Text */
    .sidebar.collapsed .text, .sidebar.collapsed .logo-text { display: none; }

    /* ===== RESPONSIVE ===== */
    .sidebar-toggle {
        display: none;
        position: fixed;
        top: 15px; left: 15px;
        background: #f0a500;
        color: #000;
        padding: 8px 12px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 12px;
        cursor: pointer;
        z-index: 2500;
    }

    @media (max-width: 992px) {
        .sidebar { transform: translateX(-100%); width: 250px !important; }
        .sidebar.active { transform: translateX(0); }
        .sidebar-toggle { display: block; }
        .collapse-btn { display: none; }
        .mobile-close { display: block; }
        .sidebar.active .text, .sidebar.active .logo-text { display: block; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('collapseBtn');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('mobileClose');

    // Desktop Collapse: Change width and toggle arrow direction
    if(collapseBtn) {
        collapseBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            collapseBtn.innerHTML = sidebar.classList.contains('collapsed') ? '&gt;' : '&lt;';
            
            // Adjust main content margin
            const main = document.querySelector('.dashboard-main');
            if(main) main.style.marginLeft = sidebar.classList.contains('collapsed') ? '70px' : '240px';
        });
    }

    // Mobile Open
    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.add('active');
        });
    }

    // Mobile Close
    if(closeBtn) {
        closeBtn.addEventListener('click', () => {
            sidebar.classList.remove('active');
        });
    }
});
</script>