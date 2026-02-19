<div class="header">
    <div>
        <h1><?php echo $pageTitle; ?></h1>
        <p style="color: #999; margin-top: 8px;">Welcome, <?php echo escape($user['full_name']); ?></p>
    </div>
    <div style="display: flex; gap: 20px; align-items: center;">
        <span class="badge"><?php echo strtoupper($role); ?></span>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</div>