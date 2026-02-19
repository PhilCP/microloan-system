<?php
session_start();
require_once '../includes/auth.php';
requireRole('borrower');
require_once '../config/db.php';

$user = getCurrentUser();

// Fetch all loans with officer remarks
$query = "SELECT l.*, u.full_name, u.email 
          FROM loans l 
          JOIN users u ON l.borrower_id = u.id 
          WHERE l.borrower_id = ? 
          ORDER BY l.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$loans = $stmt->get_result();

// Stats calculation remains the same
$stats_query = "SELECT 
    COUNT(*) as total_apps,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active_loans,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as settled_loans
    FROM loans WHERE borrower_id = ?";
$s_stmt = $conn->prepare($stats_query);
$s_stmt->bind_param("i", $user['id']);
$s_stmt->execute();
$stats = $s_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official_Loan_History_<?php echo $user['id']; ?></title>
    <style>
        :root { --primary-gold: #c59100; --dark-slate: #2c3e50; }
        body { font-family: 'Segoe UI', sans-serif; color: #333; background: #e0e0e0; padding: 40px; margin: 0; }
        
        .report-container { 
            background: #fff; 
            max-width: 1100px; 
            margin: 0 auto; 
            padding: 50px; 
            position: relative;
            border-top: 8px solid var(--primary-gold);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .header { display: flex; justify-content: space-between; margin-bottom: 40px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .logo-area h1 { margin: 0; color: var(--dark-slate); font-size: 26px; }
        .logo-area span { color: var(--primary-gold); }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .history-table th { background: var(--dark-slate); color: #fff; padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; border-right: 1px solid #444; }
        .history-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 12px; vertical-align: top; }

        .remarks-text { font-style: italic; color: #666; font-size: 11px; max-width: 200px; line-height: 1.4; }
        
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-approved { background: #d4edda; color: #155724; }
        .bg-rejected { background: #f8d7da; color: #721c24; }
        .bg-completed { background: #d1ecf1; color: #0c5460; }

        .btn-box { text-align: center; margin-bottom: 20px; }
        .btn { padding: 12px 25px; border-radius: 5px; cursor: pointer; border: none; font-weight: bold; text-decoration: none; display: inline-block; }
        
        @media print {
            .btn-box { display: none; }
            body { background: #fff; padding: 0; }
            .report-container { box-shadow: none; border: none; width: 100%; }
        }
    </style>
</head>
<body>

<div class="btn-box">
    <button class="btn" style="background: var(--primary-gold); color: white;" onclick="window.print()">🖨️ Print Full History Report</button>
    <a href="my-loans.php" class="btn" style="background:#333; color:white;">Back</a>
</div>

<div class="report-container">
    <div class="header">
        <div class="logo-area">
            <h1>MICRO<span>LOAN</span> HISTORY</h1>
            <p style="margin:5px 0; color:#666;">Account Holder: <?php echo htmlspecialchars($user['full_name']); ?></p>
        </div>
        <div style="text-align:right; font-size:12px; color:#888;">
            Reference: USR-<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?><br>
            Issue Date: <?php echo date('d M Y'); ?>
        </div>
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>ID</th>
                <th>Principal</th>
                <th>Total Due</th>
                <th>Status</th>
                <th>Officer Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php while($l = $loans->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                <td>#LN-<?php echo $l['id']; ?></td>
                <td>KES <?php echo number_format($l['amount'], 2); ?></td>
                <td>KES <?php echo number_format($l['total_amount'], 2); ?></td>
                <td>
                    <span class="badge bg-<?php echo $l['status']; ?>">
                        <?php echo $l['status']; ?>
                    </span>
                </td>
                <td class="remarks-text">
                    <?php echo !empty($l['admin_remarks']) ? htmlspecialchars($l['admin_remarks']) : '<span style="color:#ccc;">No remarks provided.</span>'; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; text-align: center;">
        <p style="font-size: 10px; color: #aaa;">This report is a system-generated summary of all loan interactions. For disputes, please contact our support department with the Loan ID as reference.</p>
    </div>
</div>

</body>
</html>