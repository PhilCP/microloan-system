<?php
session_start();
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

require_once 'config/db.php';

$userQuery = $conn->query("SELECT COUNT(*) AS total FROM users");
$totalUsers = $userQuery->fetch_assoc()['total'] ?? 0;

$loanQuery = $conn->query("SELECT SUM(total_amount) AS total FROM loans WHERE status='approved'");
$totalLoans = $loanQuery->fetch_assoc()['total'] ?? 0;

$approvedCount = $conn->query("SELECT COUNT(*) AS total FROM loans WHERE status='approved'")->fetch_assoc()['total'];
$totalLoansCount = $conn->query("SELECT COUNT(*) AS total FROM loans")->fetch_assoc()['total'];

$approvalRate = $totalLoansCount > 0
    ? round(($approvedCount / $totalLoansCount) * 100)
    : 0;

// Format disbursed amount
if ($totalLoans >= 1000000) {
    $loansDisplay = number_format($totalLoans / 1000000, 1) . 'M';
} elseif ($totalLoans >= 1000) {
    $loansDisplay = number_format($totalLoans / 1000, 1) . 'K';
} else {
    $loansDisplay = number_format($totalLoans);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroLoan | Financial Empowerment</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
    <style>html { scroll-behavior: smooth; }</style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

   <!-- hero section -->
    <section class="hero">
        <div class="hero-container">
           
            <h1>Financial <span>Freedom</span><br>Starts Here</h1>
            <p class="hero-subtitle">
                Instant microloans with transparent terms. Join the platform empowering thousands of businesses across Kenya.
            </p>
            <div class="cta-buttons">
                <a href="register.php" class="btn btn-gold">Get Started</a>
                <a href="login.php" class="btn btn-outline">Client Login</a>
            </div>
        </div>
    </section>

   <!-- features -->
    <section class="features" id="features">
        <div class="features-container">
            <h2 class="section-title">Why Choose <span>Us</span></h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <h3 class="feature-title">Instant Approval</h3>
                    <p class="feature-desc">Our automated assessment provides decisions in minutes. Get funded when it matters most.</p>
                </div>
                <div class="feature-card">
                    <h3 class="feature-title">Secure &amp; Private</h3>
                    <p class="feature-desc">Bank-grade encryption ensures your data stays protected. We value your privacy above all else.</p>
                </div>
                <div class="feature-card">
                    <h3 class="feature-title">Mobile First</h3>
                    <p class="feature-desc">Apply for loans and manage repayments directly from your smartphone, anytime, anywhere.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- stats coming from the db -->
    <section class="stats" id="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($totalUsers); ?><span style="color:var(--gold);font-size:2.4rem;font-weight:900;">+</span></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">KES <?php echo $loansDisplay; ?><span style="color:var(--gold);font-size:2.4rem;font-weight:900;">+</span></div>
                <div class="stat-label">Disbursed</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $approvalRate; ?><span style="color:var(--gold);font-size:2.4rem;font-weight:900;">%</span></div>
                <div class="stat-label">Approval Rate</div>
            </div>
        </div>
    </section>
<!-- faq -->
    <section class="faq-section" id="faq">
        <div class="faq-container">
            <h2 class="section-title">Common <span>Questions</span></h2>

            <div class="faq-item">
                <div class="faq-question">How long does approval take? <span class="faq-icon">+</span></div>
                <div class="faq-answer">
                    <p>Most loans are approved instantly after completing your profile. Our system works 24/7 to ensure you get funds exactly when you need them.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">What is the maximum loan amount? <span class="faq-icon">+</span></div>
                <div class="faq-answer">
                    <p>Given the nature of our platform (being for informal sectors) the maximum loan amount is KES 10,000 .</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Are there any hidden fees? <span class="faq-icon">+</span></div>
                <div class="faq-answer">
                    <p>Absolutely not. We pride ourselves on transparency. You will see the total repayment amount before you accept the loan.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- cta -->
    <section class="cta-section">
        <div class="cta-container">
            <h2>Ready to <span>join us?</span></h2>
            <p>Create an account today and join thousands of satisfied customers across the country.</p>
            <a href="register.php" class="btn btn-gold">Create Free Account</a>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

   <!-- accordion script(opend adn close faq answers) -->
    <script src="assets/js/accordion.js"></script>

</body>
</html>