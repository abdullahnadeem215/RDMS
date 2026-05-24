<?php
/**
 * Beneficiaries Management Page - RDMS
 */

$page_title = "Beneficiaries";
require_once 'header.php';

$message = '';
$message_type = '';

// 1. Handle Registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $cnic = sanitize_input($_POST['cnic']);
    $full_name = sanitize_input($_POST['full_name']);
    $father_name = sanitize_input($_POST['father_name']);
    $gender = sanitize_input($_POST['gender']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $household_size = intval($_POST['household_size']);
    $primary_income_source = sanitize_input($_POST['primary_income_source']);
    $monthly_income = floatval($_POST['monthly_income']);
    $contact_phone = sanitize_input($_POST['contact_phone']);
    $location_id = intval($_POST['location_id']);
    $registration_date = sanitize_input($_POST['registration_date']);

    // CNIC Validation check
    if (!validate_cnic($cnic)) {
        $message = "Invalid CNIC Format. Please use XXXXX-XXXXXXX-X";
        $message_type = "error";
    } else {
        // Check uniqueness of CNIC
        $check = $conn->query("SELECT beneficiary_id FROM beneficiaries WHERE cnic = '$cnic'");
        if ($check && $check->num_rows > 0) {
            $message = "A beneficiary with this CNIC is already registered.";
            $message_type = "error";
        } else {
            $dob_sql = !empty($date_of_birth) ? "'$date_of_birth'" : "NULL";
            $sql = "INSERT INTO beneficiaries (cnic, full_name, father_name, gender, date_of_birth, household_size, primary_income_source, monthly_income, contact_phone, location_id, registration_date, status) 
                    VALUES ('$cnic', '$full_name', '$father_name', '$gender', $dob_sql, $household_size, '$primary_income_source', $monthly_income, '$contact_phone', $location_id, '$registration_date', 'Active')";
            
            if ($conn->query($sql)) {
                $message = "Beneficiary registered successfully!";
                $message_type = "success";
            } else {
                $message = "Error: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}

// 2. Handle Edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $beneficiary_id = intval($_POST['beneficiary_id']);
    $cnic = sanitize_input($_POST['cnic']);
    $full_name = sanitize_input($_POST['full_name']);
    $father_name = sanitize_input($_POST['father_name']);
    $gender = sanitize_input($_POST['gender']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $household_size = intval($_POST['household_size']);
    $primary_income_source = sanitize_input($_POST['primary_income_source']);
    $monthly_income = floatval($_POST['monthly_income']);
    $contact_phone = sanitize_input($_POST['contact_phone']);
    $location_id = intval($_POST['location_id']);
    $status = sanitize_input($_POST['status']);

    if (!validate_cnic($cnic)) {
        $message = "Invalid CNIC Format. Please use XXXXX-XXXXXXX-X";
        $message_type = "error";
    } else {
        $dob_sql = !empty($date_of_birth) ? "'$date_of_birth'" : "NULL";
        $sql = "UPDATE beneficiaries SET 
                cnic='$cnic', full_name='$full_name', father_name='$father_name', gender='$gender', 
                date_of_birth=$dob_sql, household_size=$household_size, primary_income_source='$primary_income_source', 
                monthly_income=$monthly_income, contact_phone='$contact_phone', location_id=$location_id, status='$status' 
                WHERE beneficiary_id=$beneficiary_id";
        
        if ($conn->query($sql)) {
            $message = "Beneficiary details updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
}

// 3. Handle Add Need Assessment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_need') {
    $beneficiary_id = intval($_POST['beneficiary_id']);
    $need_type = sanitize_input($_POST['need_type']);
    $description = sanitize_input($_POST['description']);
    $severity = sanitize_input($_POST['severity']);
    $assessment_date = sanitize_input($_POST['assessment_date']);

    $sql = "INSERT INTO needs_assessment (beneficiary_id, need_type, description, severity, assessment_date, resolved) 
            VALUES ($beneficiary_id, '$need_type', '$description', '$severity', '$assessment_date', FALSE)";
    
    if ($conn->query($sql)) {
        $message = "Needs assessment recorded successfully!";
        $message_type = "success";
        // Force reopen the modal
        header("Location: beneficiaries.php?view_needs=$beneficiary_id&success_msg=Need added");
        exit();
    } else {
        $message = "Error adding need assessment: " . $conn->error;
        $message_type = "error";
    }
}

// 4. Handle Resolve Need Assessment
if (isset($_GET['resolve_need_id']) && isset($_GET['b_id'])) {
    $need_id = intval($_GET['resolve_need_id']);
    $b_id = intval($_GET['b_id']);
    
    $sql = "UPDATE needs_assessment SET resolved = TRUE, resolved_date = CURDATE() WHERE assessment_id = $need_id";
    if ($conn->query($sql)) {
        header("Location: beneficiaries.php?view_needs=$b_id&success_msg=Need resolved");
        exit();
    } else {
        $message = "Error resolving need";
        $message_type = "error";
    }
}

// Check for redirect message
if (isset($_GET['success_msg'])) {
    $message = htmlspecialchars($_GET['success_msg']);
    $message_type = "success";
}

// Fetch Locations for dropdown
$locations_list = $conn->query("SELECT location_id, village_name, district FROM locations ORDER BY village_name");
$locations = [];
while ($loc = $locations_list->fetch_assoc()) {
    $locations[] = $loc;
}

// Fetch Filters from Query Params
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_loc = isset($_GET['location']) ? intval($_GET['location']) : 0;
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$filter_gender = isset($_GET['gender']) ? sanitize_input($_GET['gender']) : '';

// Build Query
$query = "SELECT b.*, l.village_name, l.district 
          FROM beneficiaries b 
          JOIN locations l ON b.location_id = l.location_id 
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (b.full_name LIKE '%$search%' OR b.cnic LIKE '%$search%' OR b.contact_phone LIKE '%$search%')";
}
if ($filter_loc > 0) {
    $query .= " AND b.location_id = $filter_loc";
}
if (!empty($filter_status)) {
    $query .= " AND b.status = '$filter_status'";
}
if (!empty($filter_gender)) {
    $query .= " AND b.gender = '$filter_gender'";
}

$query .= " ORDER BY b.beneficiary_id DESC";
$result = $conn->query($query);
?>

<div class="container">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>👥 Beneficiary Profiles</h1>
            <p>Register, assess needs, and manage beneficiary demographics</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="openModal('registerModal')">➕ Register Beneficiary</button>
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
                    placeholder="Search name or CNIC..." 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($search); ?>" 
                    style="width: 240px;"
                >
                <select name="location" class="form-control" style="width: 180px;">
                    <option value="0">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo $loc['location_id']; ?>" <?php echo $filter_loc == $loc['location_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc['village_name'] . " (" . $loc['district'] . ")"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="gender" class="form-control" style="width: 130px;">
                    <option value="">All Genders</option>
                    <option value="Male" <?php echo $filter_gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $filter_gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $filter_gender === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <select name="status" class="form-control" style="width: 130px;">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $filter_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Deceased" <?php echo $filter_status === 'Deceased' ? 'selected' : ''; ?>>Deceased</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
            <?php if (!empty($search) || $filter_loc > 0 || !empty($filter_status) || !empty($filter_gender)): ?>
                <a href="beneficiaries.php" class="btn btn-secondary" style="border: none; background: #e2e8f0; color: #475569;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Beneficiary Table Grid -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>CNIC</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Household</th>
                        <th>Income Source</th>
                        <th>Monthly Income</th>
                        <th>Village</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-secondary);"><?php echo htmlspecialchars($row['cnic']); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo $row['gender']; ?></td>
                                <td><?php echo $row['household_size']; ?> members</td>
                                <td><?php echo htmlspecialchars($row['primary_income_source'] ?: '-'); ?></td>
                                <td style="font-weight: 600; color: var(--primary);">PKR <?php echo number_format($row['monthly_income']); ?></td>
                                <td><?php echo htmlspecialchars($row['village_name']); ?></td>
                                <td>
                                    <?php 
                                    $bClass = 'badge-success';
                                    if ($row['status'] == 'Inactive') $bClass = 'badge-warning';
                                    if ($row['status'] == 'Deceased') $bClass = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $bClass; ?>"><?php echo $row['status']; ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 8px;">
                                        <a href="?view_needs=<?php echo $row['beneficiary_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">🎯 Needs</a>
                                        <a href="?edit_id=<?php echo $row['beneficiary_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">✏️ Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-light);">No beneficiaries match the current filter criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= REGISTER BENEFICIARY MODAL ================= -->
<div class="modal-overlay <?php echo (isset($_GET['action']) && $_GET['action'] == 'add') ? 'active' : ''; ?>" id="registerModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Register New Beneficiary</h3>
            <button class="btn-close-modal" onclick="closeModal('registerModal'); window.history.replaceState({}, '', 'beneficiaries.php');">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="register">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="reg_cnic">CNIC (format: XXXXX-XXXXXXX-X) *</label>
                        <input type="text" name="cnic" id="reg_cnic" placeholder="42101-1234567-1" class="form-control" required pattern="^\d{5}-\d{7}-\d{1}$">
                    </div>
                    <div class="form-group">
                        <label for="reg_full_name">Full Name *</label>
                        <input type="text" name="full_name" id="reg_full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="reg_father_name">Father's/Spouse Name</label>
                        <input type="text" name="father_name" id="reg_father_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="reg_gender">Gender *</label>
                        <select name="gender" id="reg_gender" class="form-control" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reg_dob">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="reg_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="reg_household">Household Size *</label>
                        <input type="number" name="household_size" id="reg_household" class="form-control" min="1" value="5" required>
                    </div>
                    <div class="form-group">
                        <label for="reg_income_src">Primary Income Source</label>
                        <input type="text" name="primary_income_source" id="reg_income_src" placeholder="Farming, Trade, etc." class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="reg_income">Monthly Income (PKR) *</label>
                        <input type="number" name="monthly_income" id="reg_income" class="form-control" min="0" value="10000" required>
                    </div>
                    <div class="form-group">
                        <label for="reg_phone">Contact Phone</label>
                        <input type="text" name="contact_phone" id="reg_phone" placeholder="0300-1234567" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="reg_loc">Village / Location *</label>
                        <select name="location_id" id="reg_loc" class="form-control" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['location_id']; ?>">
                                    <?php echo htmlspecialchars($loc['village_name'] . " (" . $loc['district'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reg_date">Registration Date *</label>
                        <input type="date" name="registration_date" id="reg_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('registerModal'); window.history.replaceState({}, '', 'beneficiaries.php');">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Beneficiary</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT BENEFICIARY MODAL ================= -->
<?php
$edit_beneficiary = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_res = $conn->query("SELECT * FROM beneficiaries WHERE beneficiary_id = $edit_id");
    if ($edit_res && $edit_res->num_rows > 0) {
        $edit_beneficiary = $edit_res->fetch_assoc();
    }
}
?>
<div class="modal-overlay <?php echo $edit_beneficiary ? 'active' : ''; ?>" id="editModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Edit Beneficiary Details</h3>
            <button class="btn-close-modal" onclick="closeModal('editModal'); window.location.href='beneficiaries.php';">✕</button>
        </div>
        <?php if ($edit_beneficiary): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="beneficiary_id" value="<?php echo $edit_beneficiary['beneficiary_id']; ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_cnic">CNIC *</label>
                            <input type="text" name="cnic" id="edit_cnic" class="form-control" value="<?php echo htmlspecialchars($edit_beneficiary['cnic']); ?>" required pattern="^\d{5}-\d{7}-\d{1}$">
                        </div>
                        <div class="form-group">
                            <label for="edit_full_name">Full Name *</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" value="<?php echo htmlspecialchars($edit_beneficiary['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_father_name">Father's/Spouse Name</label>
                            <input type="text" name="father_name" id="edit_father_name" class="form-control" value="<?php echo htmlspecialchars($edit_beneficiary['father_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_gender">Gender *</label>
                            <select name="gender" id="edit_gender" class="form-control" required>
                                <option value="Male" <?php echo $edit_beneficiary['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $edit_beneficiary['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo $edit_beneficiary['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_dob">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="edit_dob" class="form-control" value="<?php echo $edit_beneficiary['date_of_birth']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_household">Household Size *</label>
                            <input type="number" name="household_size" id="edit_household" class="form-control" min="1" value="<?php echo $edit_beneficiary['household_size']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_income_src">Primary Income Source</label>
                            <input type="text" name="primary_income_source" id="edit_income_src" class="form-control" value="<?php echo htmlspecialchars($edit_beneficiary['primary_income_source']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_income">Monthly Income (PKR) *</label>
                            <input type="number" name="monthly_income" id="edit_income" class="form-control" min="0" value="<?php echo intval($edit_beneficiary['monthly_income']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone">Contact Phone</label>
                            <input type="text" name="contact_phone" id="edit_phone" class="form-control" value="<?php echo htmlspecialchars($edit_beneficiary['contact_phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_loc">Village / Location *</label>
                            <select name="location_id" id="edit_loc" class="form-control" required>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo $loc['location_id']; ?>" <?php echo $edit_beneficiary['location_id'] == $loc['location_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['village_name'] . " (" . $loc['district'] . ")"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Account Status *</label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="Active" <?php echo $edit_beneficiary['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $edit_beneficiary['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Deceased" <?php echo $edit_beneficiary['status'] == 'Deceased' ? 'selected' : ''; ?>>Deceased</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal'); window.location.href='beneficiaries.php';">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Details</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================= NEEDS ASSESSMENT MODAL ================= -->
<?php
$needs_beneficiary = null;
$needs_list = [];
if (isset($_GET['view_needs'])) {
    $b_id = intval($_GET['view_needs']);
    $b_res = $conn->query("SELECT * FROM beneficiaries WHERE beneficiary_id = $b_id");
    if ($b_res && $b_res->num_rows > 0) {
        $needs_beneficiary = $b_res->fetch_assoc();
        
        $n_res = $conn->query("SELECT * FROM needs_assessment WHERE beneficiary_id = $b_id ORDER BY resolved ASC, assessment_date DESC");
        while ($row = $n_res->fetch_assoc()) {
            $needs_list[] = $row;
        }
    }
}
?>
<div class="modal-overlay <?php echo $needs_beneficiary ? 'active' : ''; ?>" id="needsModal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">🎯 Needs Assessment - <?php echo htmlspecialchars($needs_beneficiary['full_name'] ?? ''); ?></h3>
            <button class="btn-close-modal" onclick="closeModal('needsModal'); window.location.href='beneficiaries.php';">✕</button>
        </div>
        <div class="modal-body">
            <!-- Split Modal Content: Add Need Form and Existing Needs List -->
            <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
                
                <!-- Existing Needs List -->
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Current Vulnerabilities / Needs</h4>
                    <div class="table-responsive">
                        <table class="table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($needs_list) > 0): ?>
                                    <?php foreach ($needs_list as $need): ?>
                                        <tr>
                                            <td><?php echo format_date($need['assessment_date']); ?></td>
                                            <td>
                                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($need['need_type']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;"><?php echo htmlspecialchars($need['description']); ?></div>
                                            </td>
                                            <td>
                                                <?php
                                                $sBadge = 'badge-success';
                                                if ($need['severity'] === 'Critical') $sBadge = 'badge-danger';
                                                if ($need['severity'] === 'High') $sBadge = 'badge-warning';
                                                if ($need['severity'] === 'Medium') $sBadge = 'badge-info';
                                                ?>
                                                <span class="badge <?php echo $sBadge; ?>"><?php echo $need['severity']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($need['resolved']): ?>
                                                    <span class="badge badge-success">Resolved (<?php echo format_date($need['resolved_date']); ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Unresolved</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <?php if (!$need['resolved']): ?>
                                                    <a href="?b_id=<?php echo $needs_beneficiary['beneficiary_id']; ?>&resolve_need_id=<?php echo $need['assessment_id']; ?>" 
                                                       class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; border-color: var(--success); color: var(--success-dark);">
                                                        ✓ Resolve
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-light); padding: 15px;">No needs assessment records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Record New Need Form -->
                <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                    <h4 style="font-size: 14px; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Record New Needs Assessment</h4>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_need">
                        <input type="hidden" name="beneficiary_id" value="<?php echo $needs_beneficiary['beneficiary_id'] ?? 0; ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="need_type">Need Category *</label>
                                <input type="text" name="need_type" id="need_type" placeholder="e.g. Healthcare access, Water, Education" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="need_severity">Severity *</label>
                                <select name="severity" id="need_severity" class="form-control" required>
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="need_date">Assessment Date *</label>
                                <input type="date" name="assessment_date" id="need_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group full-width">
                                <label for="need_desc">Description / Vulnerability Details</label>
                                <textarea name="description" id="need_desc" placeholder="Provide extra context about the assessment..." class="form-control"></textarea>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                            <button type="submit" class="btn btn-primary">Add Need Assessment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('needsModal'); window.location.href='beneficiaries.php';">Close</button>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
