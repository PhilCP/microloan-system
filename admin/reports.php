<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/auth.php';
requireRole('admin'); 
require_once '../config/db.php';

$user = getCurrentUser();
$role = "admin";

// 1. Tactical Search & Ledger Filtering
$search = $_GET['search'] ?? '';

// Build the ledger query with dynamic filtering
$repaymentsQuery = "SELECT r.*, u.full_name, u.email, l.remaining_balance 
                    FROM repayments r
                    JOIN loans l ON r.loan_id = l.id
                    JOIN users u ON l.borrower_id = u.id";

if (!empty($search)) {
    // Sanitizing for a basic LIKE search
    $s = "%$search%";
    $repaymentsQuery .= " WHERE u.full_name LIKE ? OR u.email LIKE ?";
    $stmt = $conn->prepare($repaymentsQuery . " ORDER BY r.payment_date DESC LIMIT 50");
    $stmt->bind_param("ss", $s, $s);
    $stmt->execute();
    $repaymentsResult = $stmt->get_result();
} else {
    $repaymentsResult = $conn->query($repaymentsQuery . " ORDER BY r.payment_date DESC LIMIT 50");
}

// --- 2. Macro-Financial Metrics ---
$statsQuery = "SELECT 
    (SELECT SUM(total_amount) FROM loans WHERE status IN ('approved', 'completed')) as total_disbursed,
    (SELECT SUM(amount_paid) FROM repayments) as total_recovered,
    (SELECT COUNT(*) FROM loans WHERE status='approved') as active_loans,
    (SELECT COUNT(*) FROM users WHERE role='borrower') as total_borrowers";

$stats = $conn->query($statsQuery)->fetch_assoc();

$totalDisbursed = $stats['total_disbursed'] ?? 0;
$totalRecovered = $stats['total_recovered'] ?? 0;
// Risk Assessment: Capital currently "in the field"
$outstanding = $totalDisbursed - $totalRecovered;

$pageTitle = "Global Intelligence Report";
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

        /* Macro Metrics Grid */
        .admin-stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-bottom: 35px; 
        }
        .stat-card { 
            background: #0a0a0a; 
            border: 1px solid #1a1a1a; 
            padding: 25px; 
            border-radius: 12px; 
            border-top: 4px solid #f0a500; 
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: #333; }
        .stat-label { color: #444; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; margin-bottom: 12px; }
        .stat-number { font-size: 26px; font-weight: 900; color: #fff; font-family: 'JetBrains Mono', monospace; }

        /* Report Section */
        .report-section { background: #0a0a0a; border: 1px solid #1a1a1a; border-radius: 12px; padding: 30px; }
        
        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            padding-bottom: 20px;
            border-bottom: 1px solid #111;
        }

        /* Filter UI */
        .filter-group { display: flex; gap: 10px; align-items: center; }
        .search-input { 
            background: #000; 
            border: 1px solid #222; 
            color: #fff; 
            padding: 10px 15px; 
            border-radius: 6px; 
            font-size: 13px;
            width: 250px;
        }
        .search-input:focus { border-color: #f0a500; outline: none; }

        .btn-action { 
            background: #f0a500; 
            color: #000; 
            padding: 10px 20px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-size: 12px; 
            font-weight: 900; 
            border: none;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn-action:hover { background: #fff; }

        /* Ledger Table */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: #333; font-size: 11px; text-transform: uppercase; padding: 15px; border-bottom: 1px solid #111; }
        td { padding: 18px 15px; border-bottom: 1px solid #0f0f0f; font-size: 14px; }
        
        .method-badge { 
            font-size: 9px; 
            padding: 3px 8px; 
            border-radius: 4px; 
            background: #111; 
            color: #666; 
            border: 1px solid #222; 
            font-weight: 800;
        }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <main class="dashboard-main" id="dashboardMain" style="margin-left: 240px; padding: 30px;">
        <?php include '../includes/dashboard_header.php'; ?>

        <div class="welcome" style="margin-bottom: 40px;">
            <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">Global Intelligence</h2>
            <p style="color: #444;">Macro-oversight of total capital deployment and institutional recovery metrics.</p>
        </div>

        <div class="admin-stats-grid">
            <div class="stat-card">
                <div class="stat-label">Gross Disbursement</div>
                <div class="stat-number">KES <?php echo number_format($totalDisbursed, 0); ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #22c55e;">
                <div class="stat-label">Capital Recovered</div>
                <div class="stat-number" style="color: #22c55e;">KES <?php echo number_format($totalRecovered, 0); ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #ef4444;">
                <div class="stat-label">Systemic Risk (Outstanding)</div>
                <div class="stat-number" style="color: #ef4444;">KES <?php echo number_format($outstanding, 0); ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #3b82f6;">
                <div class="stat-label">Operational Deployments</div>
                <div class="stat-number" style="color: #3b82f6;"><?php echo $stats['active_loans']; ?></div>
            </div>
        </div>

        <div class="report-section">
            <div class="section-header">
                <h3 style="font-weight: 900; text-transform: uppercase; font-size: 14px; letter-spacing: 1px; color: #f0a500;">Recent Transaction Ledger</h3>
                
                <div class="filter-group">
                    <form method="GET" style="display: flex; gap: 8px;">
                        <input type="text" name="search" class="search-input" placeholder="Search Identity (Name/Email)..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-action" style="background: #111; color: #fff; border: 1px solid #222;">Filter</button>
                    </form>
                    <a href="full_history_report.php" class="btn-action" target="_blank">Export Master Ledger</a>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Borrower Identity</th>
                        <th>Ref Code</th>
                        <th>Channel</th>
                        <th>Credit (Amount)</th>
                        <th>Post-Payment Bal.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($repaymentsResult->num_rows > 0): ?>
                        <?php while($row = $repaymentsResult->fetch_assoc()): ?>
                        <tr>
                            <td style="color: #444; font-size: 12px; font-family: monospace;"><?php echo date('Y.m.d', strtotime($row['payment_date'])); ?></td>
                            <td>
                                <strong style="color: #ddd;"><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                <div style="font-size: 11px; color: #333;"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td style="font-family: monospace; color: #444;">#LN-<?php echo str_pad($row['loan_id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><span class="method-badge"><?php echo strtoupper($row['payment_method']); ?></span></td>
                            <td style="color: #22c55e; font-weight: 900;">KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                            <td style="color: #f0a500; font-family: monospace;">KES <?php echo number_format($row['remaining_balance'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 50px; color: #222; font-weight: 900;">NO TRANSACTIONS FOUND IN THIS SECTOR.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        document.getElementById('sidebarToggle').addEventListener('click',()=>{
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('dashboardMain');
            sidebar.classList.toggle('active');
            main.style.marginLeft = sidebar.classList.contains('active') ? '240px' : '0';
        });
    </script>
</body>
</html>