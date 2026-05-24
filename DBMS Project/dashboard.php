<?php
/**
 * Dashboard - Main page after login
 */

$page_title = "Dashboard";
require_once 'header.php';

// Get dashboard statistics
$beneficiary_count = $conn->query("SELECT COUNT(*) as count FROM beneficiaries WHERE status='Active'");
$beneficiary_data = $beneficiary_count->fetch_assoc();
$total_beneficiaries = $beneficiary_data['count'];

$donor_count = $conn->query("SELECT COUNT(*) as count FROM donors WHERE status='Active'");
$donor_data = $donor_count->fetch_assoc();
$total_donors = $donor_data['count'];

$project_count = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status IN ('Active', 'Planning')");
$project_data = $project_count->fetch_assoc();
$active_projects = $project_data['count'];

$donation_sum = $conn->query("SELECT SUM(donation_amount) as total FROM donations WHERE YEAR(donation_date)=YEAR(NOW())");
$donation_data = $donation_sum->fetch_assoc();
$total_donations = $donation_data['total'] ? $donation_data['total'] : 0;

$budget_sum = $conn->query("SELECT SUM(budget_used) as total FROM projects");
$budget_data = $budget_sum->fetch_assoc();
$total_spent = $budget_data['total'] ? $budget_data['total'] : 0;

$village_count = $conn->query("SELECT COUNT(*) as count FROM locations");
$village_data = $village_count->fetch_assoc();
$total_villages = $village_data['count'];

$volunteer_count = $conn->query("SELECT COUNT(*) as count FROM volunteers WHERE availability_status='Available'");
$volunteer_data = $volunteer_count->fetch_assoc();
$available_volunteers = $volunteer_data['count'];

// Recent distributions
$recent_distributions = $conn->query(
    "SELECT ad.distribution_id, ad.distribution_date, ad.aid_type, b.full_name, l.village_name 
     FROM aid_distribution ad
     JOIN beneficiaries b ON ad.beneficiary_id = b.beneficiary_id
     JOIN locations l ON ad.location_id = l.location_id
     ORDER BY ad.distribution_date DESC LIMIT 5"
);

// Recent donations
$recent_donations = $conn->query(
    "SELECT d.donation_id, d.donor_id, d.donation_amount, d.donation_date, don.donor_name 
     FROM donations d
     JOIN donors don ON d.donor_id = don.donor_id
     ORDER BY d.donation_date DESC LIMIT 5"
);
?>

<!-- Main Content -->
<div class="container animate-fade">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>! 👋</h1>
            <p>Here's an overview of the Umeed-e-Sahar Foundation's development activities</p>
        </div>
    </div>
    
    <!-- Quick Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-wrapper">👥</div>
            <div class="stat-content">
                <div class="stat-label">Total Beneficiaries</div>
                <div class="stat-value"><?php echo number_format($total_beneficiaries); ?></div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon-wrapper">🤝</div>
            <div class="stat-content">
                <div class="stat-label">Active Donors</div>
                <div class="stat-value"><?php echo number_format($total_donors); ?></div>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon-wrapper">📋</div>
            <div class="stat-content">
                <div class="stat-label">Active Projects</div>
                <div class="stat-value"><?php echo number_format($active_projects); ?></div>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon-wrapper">💰</div>
            <div class="stat-content">
                <div class="stat-label">Donations (<?php echo date('Y'); ?>)</div>
                <div class="stat-value">PKR <?php echo number_format(intval($total_donations)); ?></div>
            </div>
        </div>
        <div class="stat-card pink">
            <div class="stat-icon-wrapper">💳</div>
            <div class="stat-content">
                <div class="stat-label">Amount Spent</div>
                <div class="stat-value">PKR <?php echo number_format(intval($total_spent)); ?></div>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon-wrapper">🏘️</div>
            <div class="stat-content">
                <div class="stat-label">Villages Covered</div>
                <div class="stat-value"><?php echo number_format($total_villages); ?></div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon-wrapper">🙋</div>
            <div class="stat-content">
                <div class="stat-label">Available Volunteers</div>
                <div class="stat-value"><?php echo number_format($available_volunteers); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <h2 style="font-size: 20px; margin-bottom: 20px; color: var(--text-primary);">⚡ Quick Actions</h2>
    <div class="quick-actions">
        <a href="beneficiaries.php?action=add" class="action-btn">
            <span class="action-icon">➕</span>
            <span>Add Beneficiary</span>
        </a>
        <a href="donations.php?action=add" class="action-btn">
            <span class="action-icon">💵</span>
            <span>Record Donation</span>
        </a>
        <a href="aid-distribution.php?action=add" class="action-btn">
            <span class="action-icon">📦</span>
            <span>Distribute Aid</span>
        </a>
        <a href="projects.php?action=add" class="action-btn">
            <span class="action-icon">🎯</span>
            <span>Create Project</span>
        </a>
    </div>
    
    <!-- Recent Activities Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px;">
        <!-- Recent Aid Distributions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📦 Recent Aid Distributions</h3>
                <a href="aid-distribution.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Aid Type</th>
                            <th>Beneficiary</th>
                            <th>Village</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($recent_distributions && $recent_distributions->num_rows > 0) {
                            while ($row = $recent_distributions->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td style='font-weight: 600; color: var(--text-secondary);'>" . format_date($row['distribution_date']) . "</td>";
                                echo "<td><span class='badge badge-info'>" . htmlspecialchars($row['aid_type']) . "</span></td>";
                                echo "<td style='font-weight: 600;'>" . htmlspecialchars($row['full_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['village_name']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align: center; color: var(--text-light);'>No recent distributions recorded</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Donations -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">💰 Recent Donations</h3>
                <a href="donations.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Donor</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($recent_donations && $recent_donations->num_rows > 0) {
                            while ($row = $recent_donations->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td style='font-weight: 600; color: var(--text-secondary);'>" . format_date($row['donation_date']) . "</td>";
                                echo "<td style='font-weight: 600;'>" . htmlspecialchars($row['donor_name']) . "</td>";
                                echo "<td style='font-weight: 700; color: var(--primary);'>PKR " . number_format($row['donation_amount']) . "</td>";
                                echo "<td><span class='badge badge-success'>Received</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align: center; color: var(--text-light);'>No recent donations recorded</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
