<?php
/**
 * Reports and Analytics Page - RDMS
 */

$page_title = "Reports & Analytics";
require_once 'header.php';

// Active Tab
$active_tab = isset($_GET['tab']) ? sanitize_input($_GET['tab']) : 'impact';

// REPORT 1: Geographical Impact Report
$impact_query = "SELECT l.location_id, l.village_name, l.tehsil, l.district, l.province,
                 (SELECT COUNT(*) FROM beneficiaries b WHERE b.location_id = l.location_id AND b.status = 'Active') AS active_beneficiaries,
                 (SELECT COALESCE(SUM(actual_beneficiaries), 0) FROM project_locations pl WHERE pl.location_id = l.location_id) AS total_reached,
                 (SELECT COALESCE(SUM(monetary_valuation), 0) FROM aid_distribution ad WHERE ad.location_id = l.location_id AND ad.status = 'Distributed') AS aid_distributed_value
                 FROM locations l
                 ORDER BY total_reached DESC, l.village_name ASC";
$impact_res = $conn->query($impact_query);

// REPORT 2: Needs Assessment Mapping (Unresolved vulnerabilities)
$needs_query = "SELECT l.village_name, l.district,
                COUNT(na.assessment_id) AS total_unresolved,
                SUM(CASE WHEN na.severity = 'Critical' THEN 1 ELSE 0 END) AS critical_severity,
                SUM(CASE WHEN na.severity = 'High' THEN 1 ELSE 0 END) AS high_severity,
                SUM(CASE WHEN na.severity = 'Medium' THEN 1 ELSE 0 END) AS medium_severity,
                SUM(CASE WHEN na.severity = 'Low' THEN 1 ELSE 0 END) AS low_severity
                FROM needs_assessment na
                JOIN beneficiaries b ON na.beneficiary_id = b.beneficiary_id
                JOIN locations l ON b.location_id = l.location_id
                WHERE na.resolved = FALSE
                GROUP BY l.location_id
                ORDER BY critical_severity DESC, total_unresolved DESC";
$needs_res = $conn->query($needs_query);

// REPORT 3: Project Budget Tracking
$budget_query = "SELECT p.*,
                 (p.budget - p.budget_used) AS budget_remaining,
                 (SELECT COUNT(DISTINCT volunteer_id) FROM volunteer_assignments va WHERE va.project_id = p.project_id AND va.status IN ('Assigned', 'In Progress')) AS active_volunteers
                 FROM projects p
                 ORDER BY p.budget DESC";
$budget_res = $conn->query($budget_query);

// REPORT 4: Historical Distribution Audit Log (Full Details)
$history_query = "SELECT ad.*, b.full_name AS beneficiary_name, b.cnic AS beneficiary_cnic, p.project_name, p.project_code, l.village_name
                  FROM aid_distribution ad
                  JOIN beneficiaries b ON ad.beneficiary_id = b.beneficiary_id
                  JOIN projects p ON ad.project_id = p.project_id
                  JOIN locations l ON ad.location_id = l.location_id
                  ORDER BY ad.distribution_date DESC, ad.distribution_id DESC";
$history_res = $conn->query($history_query);
?>

<div class="container">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>📊 Foundation Reports & Analytics</h1>
            <p>Export historical logs, inspect regional impact indicators, examine project financial performance, and mapping community needs</p>
        </div>
        <div>
            <!-- Print Report Button -->
            <button class="btn btn-primary" onclick="window.print();">🖨️ Print / Save PDF</button>
        </div>
    </div>

    <!-- Analytics Dashboard Cards -->
    <div class="card" style="padding-bottom: 10px;">
        <div class="tabs-header">
            <button class="tab-btn <?php echo $active_tab === 'impact' ? 'active' : ''; ?>" onclick="switchTab('tab_impact', this); window.history.replaceState({}, '', '?tab=impact');">
                🏘️ Geographical Reach & Impact
            </button>
            <button class="tab-btn <?php echo $active_tab === 'needs' ? 'active' : ''; ?>" onclick="switchTab('tab_needs', this); window.history.replaceState({}, '', '?tab=needs');">
                🎯 Unresolved Needs Vulnerabilities
            </button>
            <button class="tab-btn <?php echo $active_tab === 'budget' ? 'active' : ''; ?>" onclick="switchTab('tab_budget', this); window.history.replaceState({}, '', '?tab=budget');">
                💳 Budget Tracking & Projects
            </button>
            <button class="tab-btn <?php echo $active_tab === 'history' ? 'active' : ''; ?>" onclick="switchTab('tab_history', this); window.history.replaceState({}, '', '?tab=history');">
                📜 Distribution Audit Logs
            </button>
        </div>

        <!-- TAB 1: GEOGRAPHICAL IMPACT REPORT -->
        <div id="tab_impact" class="tab-content <?php echo $active_tab === 'impact' ? 'active' : ''; ?>">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Village Name</th>
                            <th>Administrative Jurisdiction</th>
                            <th>Active Beneficiaries</th>
                            <th>Target reached (Planned)</th>
                            <th>Aid Distributed Value</th>
                            <th>Status Indicator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($impact_res && $impact_res->num_rows > 0): ?>
                            <?php while ($row = $impact_res->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($row['village_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tehsil'] . ", " . $row['district'] . " (" . $row['province'] . ")"); ?></td>
                                    <td style="font-weight: 600;"><?php echo number_format($row['active_beneficiaries']); ?> families</td>
                                    <td style="font-weight: 700; color: var(--success-dark);"><?php echo number_format($row['total_reached']); ?> individuals reached</td>
                                    <td style="font-weight: 700; color: var(--primary);">PKR <?php echo number_format($row['aid_distributed_value']); ?></td>
                                    <td>
                                        <?php if ($row['total_reached'] > 300): ?>
                                            <span class="badge badge-success">High Impact</span>
                                        <?php elseif ($row['total_reached'] > 100): ?>
                                            <span class="badge badge-info">Medium Impact</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Expanding Reach</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-light); padding: 25px;">No geographical reach registered yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: UNRESOLVED NEEDS MAPPING -->
        <div id="tab_needs" class="tab-content <?php echo $active_tab === 'needs' ? 'active' : ''; ?>">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Village Location</th>
                            <th>District</th>
                            <th style="color: var(--danger); font-weight: 700;">Critical Needs</th>
                            <th style="color: var(--warning); font-weight: 700;">High Needs</th>
                            <th style="color: var(--info); font-weight: 700;">Medium Needs</th>
                            <th style="color: var(--success-dark); font-weight: 700;">Low Needs</th>
                            <th>Total Unresolved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($needs_res && $needs_res->num_rows > 0): ?>
                            <?php while ($row = $needs_res->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($row['village_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['district']); ?></td>
                                    <td style="font-weight: 700; color: var(--danger);"><?php echo number_format($row['critical_severity']); ?></td>
                                    <td style="font-weight: 700; color: var(--warning);"><?php echo number_format($row['high_severity']); ?></td>
                                    <td style="font-weight: 600; color: var(--info);"><?php echo number_format($row['medium_severity']); ?></td>
                                    <td style="font-weight: 600; color: var(--success-dark);"><?php echo number_format($row['low_severity']); ?></td>
                                    <td style="font-weight: 800; font-size: 15px; color: var(--text-primary);"><?php echo number_format($row['total_unresolved']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-light); padding: 25px;">No unresolved needs assessment records exist.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: PROJECT FINANCIAL BUDGET TRACKING -->
        <div id="tab_budget" class="tab-content <?php echo $active_tab === 'budget' ? 'active' : ''; ?>">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Project Code</th>
                            <th>Project Name</th>
                            <th>Total Budget</th>
                            <th>Budget Spent / Disbursed</th>
                            <th>Budget Remaining</th>
                            <th>Active Volunteers</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($budget_res && $budget_res->num_rows > 0): ?>
                            <?php while ($row = $budget_res->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--text-secondary);"><?php echo htmlspecialchars($row['project_code']); ?></td>
                                    <td style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($row['project_name']); ?></td>
                                    <td style="font-weight: 700;">PKR <?php echo number_format($row['budget']); ?></td>
                                    <td style="font-weight: 700; color: var(--primary);">PKR <?php echo number_format($row['budget_used']); ?></td>
                                    <td style="font-weight: 700; color: var(--success-dark);">PKR <?php echo number_format($row['budget_remaining']); ?></td>
                                    <td style="font-weight: 600;"><?php echo number_format($row['active_volunteers']); ?> active</td>
                                    <td>
                                        <?php 
                                        $sClass = 'badge-success';
                                        if ($row['status'] == 'Planning') $sClass = 'badge-info';
                                        if ($row['status'] == 'Completed') $sClass = 'badge-success';
                                        if ($row['status'] == 'Suspended') $sClass = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $sClass; ?>"><?php echo $row['status']; ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-light); padding: 25px;">No projects registered.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: DISTRIBUTION AUDIT LOG -->
        <div id="tab_history" class="tab-content <?php echo $active_tab === 'history' ? 'active' : ''; ?>">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shipment Date</th>
                            <th>Recipient Beneficiary (CNIC)</th>
                            <th>Location / Village</th>
                            <th>Aid Description</th>
                            <th>Associated Project</th>
                            <th>Cost Valuation</th>
                            <th>Delivered By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($history_res && $history_res->num_rows > 0): ?>
                            <?php while ($row = $history_res->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--text-secondary);"><?php echo format_date($row['distribution_date']); ?></td>
                                    <td>
                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($row['beneficiary_name']); ?></strong>
                                        <div style="font-size: 11px; color: var(--text-secondary); margin-top: 3px; font-weight: 700;"><?php echo htmlspecialchars($row['beneficiary_cnic']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['village_name']); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($row['aid_type']); ?></span>
                                        <div style="font-size: 11px; font-weight: 600; color: var(--text-secondary); margin-top: 3px;">Qty: <?php echo $row['quantity'] . " " . htmlspecialchars($row['unit_of_measure']); ?></div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['project_code']); ?></strong>
                                    </td>
                                    <td style="font-weight: 700; color: var(--primary);">PKR <?php echo number_format($row['monetary_valuation']); ?></td>
                                    <td><?php echo htmlspecialchars($row['distributed_by'] ?: '-'); ?></td>
                                    <td>
                                        <?php 
                                        $sClass = 'badge-warning';
                                        if ($row['status'] == 'Shipped') $sClass = 'badge-info';
                                        if ($row['status'] == 'Distributed') $sClass = 'badge-success';
                                        if ($row['status'] == 'Returned') $sClass = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $sClass; ?>"><?php echo $row['status']; ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-light); padding: 25px;">No historical distribution transactions registered.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
