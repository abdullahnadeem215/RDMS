<?php
/**
 * Aid Distribution Management Page - RDMS
 */

$page_title = "Aid Distribution";
require_once 'header.php';

$message = '';
$message_type = '';

// 1. Handle Register POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $beneficiary_id = intval($_POST['beneficiary_id']);
    $project_id = intval($_POST['project_id']);
    $location_id = intval($_POST['location_id']);
    $distribution_date = sanitize_input($_POST['distribution_date']);
    $aid_type = sanitize_input($_POST['aid_type']);
    $quantity = floatval($_POST['quantity']);
    $unit_of_measure = sanitize_input($_POST['unit_of_measure']);
    $monetary_valuation = floatval($_POST['monetary_valuation']);
    $distributed_by = sanitize_input($_POST['distributed_by']);
    $status = sanitize_input($_POST['status']);
    $notes = sanitize_input($_POST['notes']);

    // Check budget capacity of project
    $p_res = $conn->query("SELECT budget, budget_used FROM projects WHERE project_id = $project_id");
    $p_data = $p_res->fetch_assoc();
    $capacity = $p_data['budget'] - $p_data['budget_used'];

    // We only validate budget check if status is "Distributed" or "Shipped" and has monetary valuation
    if ($monetary_valuation > $capacity && ($status === 'Distributed' || $status === 'Shipped')) {
        $message = "Cannot register distribution. Cost (PKR " . number_format($monetary_valuation) . ") exceeds remaining project budget (PKR " . number_format($capacity) . ").";
        $message_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO aid_distribution (beneficiary_id, project_id, location_id, distribution_date, aid_type, quantity, unit_of_measure, monetary_valuation, distributed_by, status, notes) 
                    VALUES ($beneficiary_id, $project_id, $location_id, '$distribution_date', '$aid_type', $quantity, '$unit_of_measure', $monetary_valuation, '$distributed_by', '$status', '$notes')";
            $conn->query($sql);
            
            // If distributed/shipped, increase project's budget_used by the valuation amount
            if ($monetary_valuation > 0 && ($status === 'Distributed' || $status === 'Shipped')) {
                $conn->query("UPDATE projects SET budget_used = budget_used + $monetary_valuation WHERE project_id = $project_id");
            }
            
            // Increment the actual beneficiary reached count in project_locations mapping
            $conn->query("UPDATE project_locations SET actual_beneficiaries = actual_beneficiaries + 1 
                          WHERE project_id = $project_id AND location_id = $location_id");

            $conn->commit();
            $message = "Aid distribution shipment registered successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// 2. Handle Update Status POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $dist_id = intval($_POST['distribution_id']);
    $status = sanitize_input($_POST['status']);
    
    // Fetch details
    $dist_res = $conn->query("SELECT * FROM aid_distribution WHERE distribution_id = $dist_id");
    if ($dist_res && $dist_res->num_rows > 0) {
        $dist_data = $dist_res->fetch_assoc();
        $p_id = $dist_data['project_id'];
        $val = $dist_data['monetary_valuation'];
        $old_status = $dist_data['status'];
        
        $conn->begin_transaction();
        try {
            $sql = "UPDATE aid_distribution SET status = '$status' WHERE distribution_id = $dist_id";
            $conn->query($sql);
            
            // If status changed to Distributed/Shipped and wasn't previously, update budget_used
            if ($val > 0 && ($status === 'Distributed' || $status === 'Shipped') && ($old_status !== 'Distributed' && $old_status !== 'Shipped')) {
                $conn->query("UPDATE projects SET budget_used = budget_used + $val WHERE project_id = $p_id");
            }
            // If changed from Distributed/Shipped to Returned/Pending, refund budget_used
            if ($val > 0 && ($old_status === 'Distributed' || $old_status === 'Shipped') && ($status !== 'Distributed' && $status !== 'Shipped')) {
                $conn->query("UPDATE projects SET budget_used = budget_used - $val WHERE project_id = $p_id");
            }
            
            $conn->commit();
            header("Location: aid-distribution.php?success_msg=Shipment status updated to " . $status);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Check for redirect message
if (isset($_GET['success_msg'])) {
    $message = htmlspecialchars($_GET['success_msg']);
    $message_type = "success";
}

// Fetch lists for select elements
$beneficiaries_res = $conn->query("SELECT beneficiary_id, full_name, cnic FROM beneficiaries WHERE status = 'Active' ORDER BY full_name");
$beneficiaries = [];
while ($row = $beneficiaries_res->fetch_assoc()) {
    $beneficiaries[] = $row;
}

$projects_res = $conn->query("SELECT project_id, project_name, project_code FROM projects WHERE status = 'Active' ORDER BY project_name");
$projects = [];
while ($row = $projects_res->fetch_assoc()) {
    $projects[] = $row;
}

$locations_res = $conn->query("SELECT location_id, village_name, district FROM locations ORDER BY village_name");
$locations = [];
while ($row = $locations_res->fetch_assoc()) {
    $locations[] = $row;
}

$volunteers_res = $conn->query("SELECT volunteer_name, availability_status FROM volunteers ORDER BY volunteer_name");
$volunteers = [];
while ($row = $volunteers_res->fetch_assoc()) {
    $volunteers[] = $row;
}

// Filters from Query Params
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_aid = isset($_GET['aid_type']) ? sanitize_input($_GET['aid_type']) : '';
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$filter_loc = isset($_GET['location']) ? intval($_GET['location']) : 0;

$query = "SELECT ad.*, b.full_name, b.cnic, p.project_name, p.project_code, l.village_name, l.district 
          FROM aid_distribution ad
          JOIN beneficiaries b ON ad.beneficiary_id = b.beneficiary_id
          JOIN projects p ON ad.project_id = p.project_id
          JOIN locations l ON ad.location_id = l.location_id
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (b.full_name LIKE '%$search%' OR p.project_code LIKE '%$search%' OR ad.distributed_by LIKE '%$search%')";
}
if (!empty($filter_aid)) {
    $query .= " AND ad.aid_type = '$filter_aid'";
}
if (!empty($filter_status)) {
    $query .= " AND ad.status = '$filter_status'";
}
if ($filter_loc > 0) {
    $query .= " AND ad.location_id = $filter_loc";
}

$query .= " ORDER BY ad.distribution_date DESC, ad.distribution_id DESC";
$result = $conn->query($query);

// Get distinct aid types for filters
$aid_types_res = $conn->query("SELECT DISTINCT aid_type FROM aid_distribution ORDER BY aid_type");
$aid_types = [];
while ($row = $aid_types_res->fetch_assoc()) {
    $aid_types[] = $row['aid_type'];
}
?>

<div class="container">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>📦 Aid Distribution Log</h1>
            <p>Coordinate supply chain handovers, dispatch emergency aid packages, and log direct distribution activities</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="openModal('addDistributionModal')">➕ Log Aid Handover</button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <span><?php echo $message_type === 'success' ? '✓' : '⚠️'; ?></span>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Filter Control Bar -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-inputs" style="width: 100%; display: flex; justify-content: space-between;">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search beneficiary, project code, volunteer..." 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($search); ?>" 
                    style="width: 265px;"
                >
                <select name="aid_type" class="form-control" style="width: 150px;">
                    <option value="">All Aid Types</option>
                    <?php foreach ($aid_types as $at): ?>
                        <option value="<?php echo htmlspecialchars($at); ?>" <?php echo $filter_aid === $at ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($at); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="location" class="form-control" style="width: 160px;">
                    <option value="0">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo $loc['location_id']; ?>" <?php echo $filter_loc == $loc['location_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc['village_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control" style="width: 140px;">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Shipped" <?php echo $filter_status === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="Distributed" <?php echo $filter_status === 'Distributed' ? 'selected' : ''; ?>>Distributed</option>
                    <option value="Returned" <?php echo $filter_status === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
            <?php if (!empty($search) || !empty($filter_aid) || $filter_loc > 0 || !empty($filter_status)): ?>
                <a href="aid-distribution.php" class="btn btn-secondary" style="border: none; background: #e2e8f0; color: #475569;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Distribution Table Grid -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Beneficiary (CNIC)</th>
                        <th>Project Link</th>
                        <th>Village Location</th>
                        <th>Aid Details</th>
                        <th>Cost Valuation</th>
                        <th>Handed Over By</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--text-secondary);"><?php echo format_date($row['distribution_date']); ?></td>
                                <td>
                                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                    <div style="font-size: 11px; color: var(--text-secondary); margin-top: 3px; font-weight: 700;"><?php echo htmlspecialchars($row['cnic']); ?></div>
                                </td>
                                <td>
                                    <strong style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['project_code']); ?></strong>
                                    <div style="font-size: 11px; color: var(--text-light); margin-top: 3px; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($row['project_name']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($row['village_name']); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($row['aid_type']); ?></span>
                                    <div style="font-size: 12px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">Qty: <?php echo $row['quantity'] . " " . htmlspecialchars($row['unit_of_measure']); ?></div>
                                </td>
                                <td style="font-weight: 700; color: var(--primary);">PKR <?php echo number_format($row['monetary_valuation']); ?></td>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($row['distributed_by'] ?: '-'); ?></td>
                                <td>
                                    <?php 
                                    $sClass = 'badge-warning';
                                    if ($row['status'] == 'Shipped') $sClass = 'badge-info';
                                    if ($row['status'] == 'Distributed') $sClass = 'badge-success';
                                    if ($row['status'] == 'Returned') $sClass = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $sClass; ?>"><?php echo $row['status']; ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($row['status'] !== 'Distributed' && $row['status'] !== 'Returned'): ?>
                                        <form method="POST" action="" style="display: flex; gap: 4px; justify-content: flex-end;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="distribution_id" value="<?php echo $row['distribution_id']; ?>">
                                            <select name="status" class="form-control" style="width: 110px; padding: 4px; font-size: 12px;">
                                                <option value="Shipped" <?php echo $row['status'] == 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="Distributed">Distributed</option>
                                                <option value="Returned">Returned</option>
                                            </select>
                                            <button type="submit" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 12px;">Completed Log</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-light);">No aid distributions registered matching filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= LOG DISTRIBUTION MODAL ================= -->
<div class="modal-overlay <?php echo (isset($_GET['action']) && $_GET['action'] == 'add') ? 'active' : ''; ?>" id="addDistributionModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Log Aid Distribution Handover</h3>
            <button class="btn-close-modal" onclick="closeModal('addDistributionModal'); window.history.replaceState({}, '', 'aid-distribution.php');">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="register">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="dist_b">Select Beneficiary *</label>
                        <select name="beneficiary_id" id="dist_b" class="form-control" required>
                            <?php foreach ($beneficiaries as $b): ?>
                                <option value="<?php echo $b['beneficiary_id']; ?>">
                                    <?php echo htmlspecialchars($b['full_name'] . " (" . $b['cnic'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dist_proj">Select Project Source *</label>
                        <select name="project_id" id="dist_proj" class="form-control" required>
                            <?php foreach ($projects as $pj): ?>
                                <option value="<?php echo $pj['project_id']; ?>">
                                    <?php echo htmlspecialchars($pj['project_name'] . " (" . $pj['project_code'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dist_loc">Select Location *</label>
                        <select name="location_id" id="dist_loc" class="form-control" required>
                            <?php foreach ($locations as $lc): ?>
                                <option value="<?php echo $lc['location_id']; ?>">
                                    <?php echo htmlspecialchars($lc['village_name'] . " (" . $lc['district'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dist_aid">Aid Material / Type *</label>
                        <input type="text" name="aid_type" id="dist_aid" placeholder="e.g. Food Pack, Medical Kit, Cash Transfer" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="dist_qty">Quantity *</label>
                        <input type="number" name="quantity" id="dist_qty" class="form-control" min="0.1" step="0.1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="dist_uom">Unit of Measure *</label>
                        <select name="unit_of_measure" id="dist_uom" class="form-control" required>
                            <option value="Kits">Kits / Boxes</option>
                            <option value="Kg">Kilograms (Kg)</option>
                            <option value="Liters">Liters</option>
                            <option value="Pieces">Pieces</option>
                            <option value="PKR">PKR (Direct Cash Aid)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dist_val">Monetary Valuation (PKR) *</label>
                        <input type="number" name="monetary_valuation" id="dist_val" class="form-control" min="0" value="5000" required>
                    </div>
                    <div class="form-group">
                        <label for="dist_vol">Distributed / Coordinated By *</label>
                        <select name="distributed_by" id="dist_vol" class="form-control" required>
                            <option value="Foundation Staff">Foundation Staff (Direct Handover)</option>
                            <?php foreach ($volunteers as $vl): ?>
                                <option value="<?php echo htmlspecialchars($vl['volunteer_name']); ?>">
                                    <?php echo htmlspecialchars($vl['volunteer_name'] . " [Volunteer - " . $vl['availability_status'] . "]"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dist_date">Distribution Date *</label>
                        <input type="date" name="distribution_date" id="dist_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="dist_status">Status *</label>
                        <select name="status" id="dist_status" class="form-control" required>
                            <option value="Pending">Pending</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Distributed" selected>Distributed</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="dist_notes">Notes / Observations</label>
                        <textarea name="notes" id="dist_notes" placeholder="Optionally specify beneficiary feedback, item batch, etc..." class="form-control"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDistributionModal'); window.history.replaceState({}, '', 'aid-distribution.php');">Cancel</button>
                <button type="submit" class="btn btn-primary">Log Shipment</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>
