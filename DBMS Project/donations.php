<?php
/**
 * Donations and Allocations Management Page - RDMS
 */

$page_title = "Donations & Allocations";
require_once 'header.php';

$message = '';
$message_type = '';

// 1. Handle Record Donation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_donation') {
    $donor_id = intval($_POST['donor_id']);
    $donation_amount = floatval($_POST['donation_amount']);
    $donation_date = sanitize_input($_POST['donation_date']);
    $donation_type = sanitize_input($_POST['donation_type']);
    $currency = sanitize_input($_POST['currency']);
    $receipt_number = sanitize_input($_POST['receipt_number']);
    $notes = sanitize_input($_POST['notes']);

    // Check receipt unique
    $check = $conn->query("SELECT donation_id FROM donations WHERE receipt_number = '$receipt_number'");
    if ($check && $check->num_rows > 0) {
        $message = "A donation with this receipt number already exists.";
        $message_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            // Insert donation
            $sql = "INSERT INTO donations (donor_id, donation_amount, donation_date, donation_type, currency, receipt_number, notes) 
                    VALUES ($donor_id, $donation_amount, '$donation_date', '$donation_type', '$currency', '$receipt_number', '$notes')";
            $conn->query($sql);
            
            // Update donor aggregate total_donations
            $update_donor = "UPDATE donors SET total_donations = total_donations + $donation_amount WHERE donor_id = $donor_id";
            $conn->query($update_donor);
            
            $conn->commit();
            $message = "Donation recorded successfully and donor total updated!";
            $message_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error recording donation: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// 2. Handle Add Allocation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'allocate_donation') {
    $donation_id = intval($_POST['donation_id']);
    $project_id = intval($_POST['project_id']);
    $allocated_amount = floatval($_POST['allocated_amount']);
    $allocation_date = sanitize_input($_POST['allocation_date']);
    $status = sanitize_input($_POST['status']);
    $approved_by = sanitize_input($_POST['approved_by']);
    $approval_date = sanitize_input($_POST['approval_date']);

    // Check remaining donation amount
    $don_res = $conn->query(
        "SELECT donation_amount - COALESCE((SELECT SUM(allocated_amount) FROM allocations WHERE donation_id = $donation_id), 0) AS remaining 
         FROM donations WHERE donation_id = $donation_id"
    );
    $don_data = $don_res->fetch_assoc();
    $remaining_donation = $don_data['remaining'];

    // Check project budget constraints
    $proj_res = $conn->query("SELECT budget, budget_used FROM projects WHERE project_id = $project_id");
    $proj_data = $proj_res->fetch_assoc();
    $project_budget = $proj_data['budget'];
    $project_used = $proj_data['budget_used'];
    $remaining_project_budget = $project_budget - $project_used;

    if ($allocated_amount > $remaining_donation) {
        $message = "Allocated amount (PKR " . number_format($allocated_amount) . ") exceeds the remaining donation amount (PKR " . number_format($remaining_donation) . ").";
        $message_type = "error";
    } elseif ($status === 'Disbursed' && $allocated_amount > $remaining_project_budget) {
        $message = "Disbursed amount (PKR " . number_format($allocated_amount) . ") exceeds the remaining project budget capacity (PKR " . number_format($remaining_project_budget) . ").";
        $message_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            $app_date_sql = !empty($approval_date) ? "'$approval_date'" : "NULL";
            $sql = "INSERT INTO allocations (donation_id, project_id, allocated_amount, allocation_date, status, approved_by, approval_date) 
                    VALUES ($donation_id, $project_id, $allocated_amount, '$allocation_date', '$status', '$approved_by', $app_date_sql)";
            $conn->query($sql);
            
            // If disbursed, increase project's budget_used
            if ($status === 'Disbursed') {
                $up_proj = "UPDATE projects SET budget_used = budget_used + $allocated_amount WHERE project_id = $project_id";
                $conn->query($up_proj);
            }
            
            $conn->commit();
            $message = "Allocation successfully created!";
            $message_type = "success";
            // Set tab to active
            header("Location: donations.php?tab=allocations&success_msg=Allocation recorded");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error mapping allocation: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// 3. Handle Update Allocation Status
if (isset($_GET['disburse_id'])) {
    $alloc_id = intval($_GET['disburse_id']);
    
    // Fetch details
    $alloc_res = $conn->query("SELECT * FROM allocations WHERE allocation_id = $alloc_id");
    if ($alloc_res && $alloc_res->num_rows > 0) {
        $alloc_data = $alloc_res->fetch_assoc();
        $p_id = $alloc_data['project_id'];
        $amount = $alloc_data['allocated_amount'];
        $current_status = $alloc_data['status'];
        
        if ($current_status !== 'Disbursed') {
            // Check project budget constraints
            $proj_res = $conn->query("SELECT budget, budget_used FROM projects WHERE project_id = $p_id");
            $proj_data = $proj_res->fetch_assoc();
            $rem_budget = $proj_data['budget'] - $proj_data['budget_used'];
            
            if ($amount > $rem_budget) {
                $message = "Cannot disburse. Allocated amount (PKR " . number_format($amount) . ") exceeds remaining project budget (PKR " . number_format($rem_budget) . ").";
                $message_type = "error";
            } else {
                $conn->begin_transaction();
                try {
                    $conn->query("UPDATE allocations SET status = 'Disbursed', approval_date = CURDATE(), approved_by = '$_SESSION[full_name]' WHERE allocation_id = $alloc_id");
                    $conn->query("UPDATE projects SET budget_used = budget_used + $amount WHERE project_id = $p_id");
                    $conn->commit();
                    header("Location: donations.php?tab=allocations&success_msg=Disbursement completed");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error disbursing: " . $e->getMessage();
                    $message_type = "error";
                }
            }
        }
    }
}

// Check for redirect message
if (isset($_GET['success_msg'])) {
    $message = htmlspecialchars($_GET['success_msg']);
    $message_type = "success";
}

// Active Tab
$active_tab = isset($_GET['tab']) ? sanitize_input($_GET['tab']) : 'donations';

// Fetch Donors for dropdown
$donors_list = $conn->query("SELECT donor_id, donor_name, donor_type FROM donors WHERE status = 'Active' ORDER BY donor_name");
$donors = [];
while ($row = $donors_list->fetch_assoc()) {
    $donors[] = $row;
}

// Fetch Projects for dropdown
$projects_list = $conn->query("SELECT project_id, project_name, project_code, budget, budget_used FROM projects WHERE status IN ('Active', 'Planning') ORDER BY project_name");
$projects = [];
while ($row = $projects_list->fetch_assoc()) {
    $projects[] = $row;
}

// Fetch Donations List with calculations
$donations_query = "SELECT d.*, don.donor_name, don.donor_type,
                    (d.donation_amount - COALESCE((SELECT SUM(allocated_amount) FROM allocations WHERE donation_id = d.donation_id), 0)) AS remaining_unallocated
                    FROM donations d
                    JOIN donors don ON d.donor_id = don.donor_id
                    ORDER BY d.donation_date DESC";
$donations_res = $conn->query($donations_query);

// Fetch Allocations List
$allocations_query = "SELECT a.*, d.receipt_number, d.donation_amount, don.donor_name, p.project_name, p.project_code
                      FROM allocations a
                      JOIN donations d ON a.donation_id = d.donation_id
                      JOIN donors don ON d.donor_id = don.donor_id
                      JOIN projects p ON a.project_id = p.project_id
                      ORDER BY a.allocation_date DESC";
$allocations_res = $conn->query($allocations_query);
?>

<div class="container">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>💰 Fund & Allocation Management</h1>
            <p>Track incoming financial donations, record receipts, and allocate grants directly to development projects</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-secondary" onclick="openModal('addAllocationModal')">📋 Allocate Funds</button>
            <button class="btn btn-primary" onclick="openModal('addDonationModal')">➕ Record Donation</button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <span><?php echo $message_type === 'success' ? '✓' : '⚠️'; ?></span>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Tabs Container -->
    <div class="card" style="padding-bottom: 10px;">
        <div class="tabs-header">
            <button class="tab-btn <?php echo $active_tab === 'donations' ? 'active' : ''; ?>" onclick="switchTab('tab_donations', this); window.history.replaceState({}, '', '?tab=donations');">
                💵 Donations Registry
            </button>
            <button class="tab-btn <?php echo $active_tab === 'allocations' ? 'active' : ''; ?>" onclick="switchTab('tab_allocations', this); window.history.replaceState({}, '', '?tab=allocations');">
                🎯 Project Allocations
            </button>
        </div>

        <!-- TAB 1: DONATIONS REGISTRY -->
        <div id="tab_donations" class="tab-content <?php echo $active_tab === 'donations' ? 'active' : ''; ?>">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Donor Name</th>
                            <th>Donation Date</th>
                            <th>Method</th>
                            <th>Total Amount</th>
                            <th>Unallocated Balance</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($donations_res && $donations_res->num_rows > 0): ?>
                            <?php while ($row = $donations_res->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--text-secondary);"><?php echo htmlspecialchars($row['receipt_number']); ?></td>
                                    <td>
                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($row['donor_name']); ?></strong>
                                        <div style="font-size: 11px; color: var(--text-secondary); margin-top: 3px;"><?php echo $row['donor_type']; ?></div>
                                    </td>
                                    <td><?php echo format_date($row['donation_date']); ?></td>
                                    <td><span class="badge badge-info"><?php echo $row['donation_type']; ?></span></td>
                                    <td style="font-weight: 700; color: var(--success-dark);">PKR <?php echo number_format($row['donation_amount']); ?></td>
                                    <td>
                                        <?php if ($row['remaining_unallocated'] <= 0): ?>
                                            <span class="badge badge-success" style="font-size: 11px;">Fully Allocated</span>
                                        <?php else: ?>
                                            <span style="font-weight: 700; color: var(--primary);">PKR <?php echo number_format($row['remaining_unallocated']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-secondary); max-width: 250px;"><?php echo htmlspecialchars($row['notes'] ?: '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 25px; color: var(--text-light);">No donations registered.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: PROJECT ALLOCATIONS -->
        <div id="tab_allocations" class="tab-content <?php echo $active_tab === 'allocations' ? 'active' : ''; ?>">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Allocation Date</th>
                            <th>Donation Source</th>
                            <th>Donor</th>
                            <th>Target Project</th>
                            <th>Allocated Amount</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($allocations_res && $allocations_res->num_rows > 0): ?>
                            <?php while ($row = $allocations_res->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo format_date($row['allocation_date']); ?></td>
                                    <td style="font-weight: 700; color: var(--text-secondary);"><?php echo htmlspecialchars($row['receipt_number']); ?></td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['donor_name']); ?></td>
                                    <td>
                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($row['project_name']); ?></strong>
                                        <div style="font-size: 11px; color: var(--text-secondary); margin-top: 3px;"><?php echo $row['project_code']; ?></div>
                                    </td>
                                    <td style="font-weight: 700; color: var(--primary);">PKR <?php echo number_format($row['allocated_amount']); ?></td>
                                    <td>
                                        <?php 
                                        $aClass = 'badge-warning';
                                        if ($row['status'] === 'Approved') $aClass = 'badge-info';
                                        if ($row['status'] === 'Disbursed') $aClass = 'badge-success';
                                        ?>
                                        <span class="badge <?php echo $aClass; ?>"><?php echo $row['status']; ?></span>
                                        <?php if ($row['status'] === 'Disbursed'): ?>
                                            <div style="font-size: 10px; color: var(--text-secondary); margin-top: 4px;">By: <?php echo htmlspecialchars($row['approved_by']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($row['status'] !== 'Disbursed'): ?>
                                            <a href="?disburse_id=<?php echo $row['allocation_id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 11px; color: var(--success-dark); border-color: var(--success);">💸 Disburse</a>
                                        <?php else: ?>
                                            <span style="color: var(--text-light); font-size: 12px;">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 25px; color: var(--text-light);">No project allocations mapped.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= RECORD DONATION MODAL ================= -->
<div class="modal-overlay <?php echo (isset($_GET['action']) && $_GET['action'] == 'add') ? 'active' : ''; ?>" id="addDonationModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Record Incoming Donation</h3>
            <button class="btn-close-modal" onclick="closeModal('addDonationModal'); window.history.replaceState({}, '', 'donations.php');">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="record_donation">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="don_donor">Select Donor *</label>
                        <select name="donor_id" id="don_donor" class="form-control" required>
                            <?php foreach ($donors as $d): ?>
                                <option value="<?php echo $d['donor_id']; ?>">
                                    <?php echo htmlspecialchars($d['donor_name'] . " (" . $d['donor_type'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="don_amount">Donation Amount (PKR) *</label>
                        <input type="number" name="donation_amount" id="don_amount" class="form-control" min="1" value="50000" required>
                    </div>
                    <div class="form-group">
                        <label for="don_date">Donation Date *</label>
                        <input type="date" name="donation_date" id="don_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="don_type">Payment Type *</label>
                        <select name="donation_type" id="don_type" class="form-control" required>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="In-Kind">In-Kind (Goods/Materials)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="don_currency">Currency *</label>
                        <input type="text" name="currency" id="don_currency" class="form-control" value="PKR" required>
                    </div>
                    <div class="form-group">
                        <label for="don_receipt">Receipt / Reference Number *</label>
                        <input type="text" name="receipt_number" id="don_receipt" placeholder="REC-XXXXX" class="form-control" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="don_notes">Donation Notes / Remarks</label>
                        <textarea name="notes" id="don_notes" placeholder="Optionally specify transaction detail, check numbers, etc..." class="form-control"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDonationModal'); window.history.replaceState({}, '', 'donations.php');">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Donation</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= ALLOCATE FUNDS MODAL ================= -->
<div class="modal-overlay" id="addAllocationModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Allocate Donation to Project</h3>
            <button class="btn-close-modal" onclick="closeModal('addAllocationModal')">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="allocate_donation">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="alloc_don">Select Donation Receipt Source *</label>
                        <select name="donation_id" id="alloc_don" class="form-control" required>
                            <?php 
                            // Re-fetch remaining unallocated donations to prevent mistakes
                            $don_dropdown = $conn->query("SELECT d.donation_id, d.receipt_number, d.donation_amount, don.donor_name,
                                                         (d.donation_amount - COALESCE((SELECT SUM(allocated_amount) FROM allocations WHERE donation_id = d.donation_id), 0)) AS remaining
                                                         FROM donations d JOIN donors don ON d.donor_id = don.donor_id
                                                         ORDER BY d.donation_date DESC");
                            while ($don_row = $don_dropdown->fetch_assoc()) {
                                if ($don_row['remaining'] > 0) {
                                    echo "<option value='{$don_row['donation_id']}'>";
                                    echo htmlspecialchars($don_row['receipt_number'] . " [{$don_row['donor_name']}] - Balance: PKR " . number_format($don_row['remaining']));
                                    echo "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alloc_proj">Select Target Project *</label>
                        <select name="project_id" id="alloc_proj" class="form-control" required>
                            <?php foreach ($projects as $p): ?>
                                <?php 
                                $rem_budget = $p['budget'] - $p['budget_used'];
                                ?>
                                <option value="<?php echo $p['project_id']; ?>">
                                    <?php echo htmlspecialchars($p['project_name'] . " (" . $p['project_code'] . ") - Capacity: PKR " . number_format($rem_budget)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alloc_amount">Allocation Amount (PKR) *</label>
                        <input type="number" name="allocated_amount" id="alloc_amount" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="alloc_date">Allocation Date *</label>
                        <input type="date" name="allocation_date" id="alloc_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="alloc_status">Status *</label>
                        <select name="status" id="alloc_status" class="form-control" required>
                            <option value="Pending">Pending Approval</option>
                            <option value="Approved">Approved</option>
                            <option value="Disbursed">Disbursed (Released)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alloc_approved">Approved By</label>
                        <input type="text" name="approved_by" id="alloc_approved" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="alloc_approval_date">Approval Date</label>
                        <input type="date" name="approval_date" id="alloc_approval_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAllocationModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Allocate Funds</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>
