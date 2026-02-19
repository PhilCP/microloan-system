<?php
session_start();
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

require_once 'config/db.php';

// To Fetch Real-Time Stats
$userQuery = $conn->query("SELECT COUNT(*) AS total FROM users");
$totalUsers = $userQuery->fetch_assoc()['total'] ?? 0;

$loanQuery = $conn->query("SELECT SUM(total_amount) AS total FROM loans WHERE status='approved'");
$totalLoans = $loanQuery->fetch_assoc()['total'] ?? 0;

$approvedCount = $conn->query("SELECT COUNT(*) AS total FROM loans WHERE status='approved'")->fetch_assoc()['total'];
$totalLoansCount = $conn->query("SELECT COUNT(*) AS total FROM loans")->fetch_assoc()['total'];

$approvalRate = $totalLoansCount > 0 
    ? round(($approvedCount / $totalLoansCount) * 100) 
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microloan System | Financial Empowerment</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
    
    <style>
        html { scroll-behavior: smooth; }
        /* Hero specific background */
        .hero {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), #000), 
                              url('assets/images/micro.jpg');
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <section class="hero">
        <div class="hero-container">
            <h1>Financial Freedom<br><span class="gradient-text">Starts Here</span></h1>
            <p class="hero-subtitle">
                Instant microloans with transparent terms. Join the platform empowering thousands of businesses across Kenya.
            </p>
            <div class="cta-buttons">
                <a href="register.php" class="btn btn-white">Get Started</a>
                <a href="login.php" class="btn btn-outline">Client Login</a>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="features-container">
            <h2 class="section-title">Why Choose Us</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <span class="feature-icon">⚡</span>
                    <h3 class="feature-title">Instant Approval</h3>
                    <p class="feature-desc">Our automated assessment provides decisions in minutes, not days. Get funded when it matters most.</p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon">🛡️</span>
                    <h3 class="feature-title">Secure & Private</h3>
                    <p class="feature-desc">Bank-grade encryption ensures your data stays protected. We value your privacy above all else.</p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon">📱</span>
                    <h3 class="feature-title">Mobile First</h3>
                    <p class="feature-desc">Apply for loans and manage repayments directly from your smartphone, anytime, anywhere.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="stats" id="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($totalUsers); ?>+</div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    KES <?php echo ($totalLoans >= 1000000) ? number_format($totalLoans/1000000, 1).'M' : number_format($totalLoans); ?>
                </div>
                <div class="stat-label">Disbursed</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $approvalRate; ?>%</div>
                <div class="stat-label">Approval Rate</div>
            </div>
        </div>
    </section>

    <section class="faq-section" id="faq">
        <div class="faq-container">
            <h2 class="section-title">Common Questions</h2>
            
            <div class="faq-item">
                <div class="faq-question">How long does approval take? <span class="faq-icon">+</span></div>
                <div class="faq-answer">Most loans are approved instantly after completing your profile. Our system works 24/7 to ensure you get funds exactly when you need them.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">What is the maximum loan amount? <span class="faq-icon">+</span></div>
                <div class="faq-answer">First-time borrowers can access up to KES 5,000. As you repay on time, your limit grows automatically up to KES 50,000.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Are there any hidden fees? <span class="faq-icon">+</span></div>
                <div class="faq-answer">Absolutely not. We pride ourselves on transparency. You will see the total repayment amount before you accept the loan.</div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="cta-container">
            <h2>Ready to grow your business?</h2>
            <p>Create an account today and get your first loan at 0% interest for the first month.</p>
            <a href="register.php" class="btn btn-black">Create Free Account</a>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/accordion.js"></script>
</body>
</html>