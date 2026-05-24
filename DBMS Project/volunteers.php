<?php
/**
 * Volunteers Management Page - RDMS
 */

$page_title = "Volunteers Registry";
require_once 'header.php';

$message = '';
$message_type = '';

// 1. Handle Register POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $volunteer_name = sanitize_input($_POST['volunteer_name']);
    $cnic = sanitize_input($_POST['cnic']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $location_id = intval($_POST['location_id']);
    $primary_skill = sanitize_input($_POST['primary_skill']);
    $secondary_skills = sanitize_input($_POST['secondary_skills']);
    $registration_date = sanitize_input($_POST['registration_date']);
    
    // Check CNIC uniqueness if provided
    $can_insert = true;
    if (!empty($cnic)) {
        if (!validate_cnic($cnic)) {
            $message = "Invalid CNIC Format. Please use XXXXX-XXXXXXX-X";
            $message_type = "error";
            $can_insert = false;
        } else {
            $check = $conn->query("SELECT volunteer_id FROM volunteers WHERE cnic = '$cnic'");
            if ($check && $check->num_rows > 0) {
                $message = "A volunteer with this CNIC already exists.";
                $message_type = "error";
                $can_insert = false;
            }
        }
    }
    
    if ($can_insert) {
        $cnic_val = !empty($cnic) ? "'$cnic'" : "NULL";
        $email_val = !empty($email) ? "'$email'" : "NULL";
        $dob_val = !empty($date_of_birth) ? "'$date_of_birth'" : "NULL";
        
        $sql = "INSERT INTO volunteers (volunteer_name, cnic, email, phone, date_of_birth, location_id, primary_skill, secondary_skills, availability_status, registration_date, hours_contributed) 
                VALUES ('$volunteer_name', $cnic_val, $email_val, '$phone', $dob_val, $location_id, '$primary_skill', '$secondary_skills', 'Available', '$registration_date', 0)";
        
        if ($conn->query($sql)) {
            $message = "Volunteer registered successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
}

// 2. Handle Update Details POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $volunteer_id = intval($_POST['volunteer_id']);
    $volunteer_name = sanitize_input($_POST['volunteer_name']);
    $cnic = sanitize_input($_POST['cnic']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $location_id = intval($_POST['location_id']);
    $primary_skill = sanitize_input($_POST['primary_skill']);
    $secondary_skills = sanitize_input($_POST['secondary_skills']);
    $availability_status = sanitize_input($_POST['availability_status']);

    $can_update = true;
    if (!empty($cnic) && !validate_cnic($cnic)) {
        $message = "Invalid CNIC Format. Please use XXXXX-XXXXXXX-X";
        $message_type = "error";
        $can_update = false;
    }

    if ($can_update) {
        $cnic_val = !empty($cnic) ? "'$cnic'" : "NULL";
        $email_val = !empty($email) ? "'$email'" : "NULL";
        $dob_val = !empty($date_of_birth) ? "'$date_of_birth'" : "NULL";
        
        $sql = "UPDATE volunteers SET 
                volunteer_name='$volunteer_name', cnic=$cnic_val, email=$email_val, phone='$phone', 
                date_of_birth=$dob_val, location_id=$location_id, primary_skill='$primary_skill', 
                secondary_skills='$secondary_skills', availability_status='$availability_status' 
                WHERE volunteer_id=$volunteer_id";
        
        if ($conn->query($sql)) {
            $message = "Volunteer details updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
}

// 3. Handle Add Assignment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_assignment') {
    $volunteer_id = intval($_POST['volunteer_id']);
    $project_id = intval($_POST['project_id']);
    $assignment_date = sanitize_input($_POST['assignment_date']);
    $role = sanitize_input($_POST['role']);
    $status = sanitize_input($_POST['status']);
    $hours_worked = intval($_POST['hours_worked']);

    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO volunteer_assignments (volunteer_id, project_id, assignment_date, role, hours_worked, status) 
                VALUES ($volunteer_id, $project_id, '$assignment_date', '$role', $hours_worked, '$status')";
        $conn->query($sql);
        
        // Update volunteer hours
        if ($hours_worked > 0) {
            $conn->query("UPDATE volunteers SET hours_contributed = hours_contributed + $hours_worked WHERE volunteer_id = $volunteer_id");
        }
        
        // Update volunteer status if active
        if ($status === 'In Progress' || $status === 'Assigned') {
            $conn->query("UPDATE volunteers SET availability_status = 'On Assignment' WHERE volunteer_id = $volunteer_id");
        }

        $conn->commit();
        header("Location: volunteers.php?view_assignments=$volunteer_id&success_msg=Volunteer assigned successfully");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error assigning project: " . $e->getMessage();
        $message_type = "error";
    }
}

// 4. Handle Update Assignment hours / status POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_assignment') {
    $assign_id = intval($_POST['assignment_id']);
    $vol_id = intval($_POST['volunteer_id']);
    $status = sanitize_input($_POST['status']);
    $hours_added = intval($_POST['hours_added']);
    $is_complete = ($status === 'Completed' || $status === 'Cancelled');
    $comp_date_sql = $status === 'Completed' ? ", completion_date = CURDATE()" : "";

    $conn->begin_transaction();
    try {
        $sql = "UPDATE volunteer_assignments SET status = '$status', hours_worked = hours_worked + $hours_added $comp_date_sql WHERE assignment_id = $assign_id";
        $conn->query($sql);
        
        // Add hours to volunteer profile
        if ($hours_added > 0) {
            $conn->query("UPDATE volunteers SET hours_contributed = hours_contributed + $hours_added WHERE volunteer_id = $vol_id");
        }
        
        // If completed, set volunteer back to available if they have no other active assignments
        if ($is_complete) {
            $check_active = $conn->query("SELECT assignment_id FROM volunteer_assignments WHERE volunteer_id = $vol_id AND status IN ('Assigned', 'In Progress')");
            if (!$check_active || $check_active->num_rows == 0) {
                $conn->query("UPDATE volunteers SET availability_status = 'Available' WHERE volunteer_id = $vol_id");
            }
        }
        
        $conn->commit();
        header("Location: volunteers.php?view_assignments=$vol_id&success_msg=Assignment details updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
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

// Fetch Projects for assignment dropdown
$projects_list = $conn->query("SELECT project_id, project_name, project_code FROM projects WHERE status = 'Active' ORDER BY project_name");
$projects = [];
while ($proj = $projects_list->fetch_assoc()) {
    $projects[] = $proj;
}

// Filters from Query Params
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_skill = isset($_GET['skill']) ? sanitize_input($_GET['skill']) : '';
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$query = "SELECT v.*, l.village_name, l.district 
          FROM volunteers v 
          LEFT JOIN locations l ON v.location_id = l.location_id
          WHERE 1=1";
if (!empty($search)) {
    $query .= " AND (v.volunteer_name LIKE '%$search%' OR v.cnic LIKE '%$search%' OR v.phone LIKE '%$search%')";
}
if (!empty($filter_skill)) {
    $query .= " AND v.primary_skill = '$filter_skill'";
}
if (!empty($filter_status)) {
    $query .= " AND v.availability_status = '$filter_status'";
}
$query .= " ORDER BY v.volunteer_id DESC";
$result = $conn->query($query);

// Fetch Unique skills for filtering
$skills_list = $conn->query("SELECT DISTINCT primary_skill FROM volunteers WHERE primary_skill IS NOT NULL AND primary_skill != '' ORDER BY primary_skill");
$skills = [];
while ($s = $skills_list->fetch_assoc()) {
    $skills[] = $s['primary_skill'];
}
?>

<div class="container">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>🙋 Volunteers Registry</h1>
            <p>Engage, schedule, and log working contribution hours for foundation volunteers</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="openModal('addVolunteerModal')">➕ Register Volunteer</button>
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
                    placeholder="Search name, phone or CNIC..." 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($search); ?>" 
                    style="width: 250px;"
                >
                <select name="skill" class="form-control" style="width: 180px;">
                    <option value="">All Primary Skills</option>
                    <?php foreach ($skills as $sk): ?>
                        <option value="<?php echo htmlspecialchars($sk); ?>" <?php echo $filter_skill === $sk ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sk); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control" style="width: 160px;">
                    <option value="">All Statuses</option>
                    <option value="Available" <?php echo $filter_status === 'Available' ? 'selected' : ''; ?>>Available</option>
                    <option value="On Assignment" <?php echo $filter_status === 'On Assignment' ? 'selected' : ''; ?>>On Assignment</option>
                    <option value="On Leave" <?php echo $filter_status === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                    <option value="Inactive" <?php echo $filter_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
            <?php if (!empty($search) || !empty($filter_skill) || !empty($filter_status)): ?>
                <a href="volunteers.php" class="btn btn-secondary" style="border: none; background: #e2e8f0; color: #475569;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Volunteers Table Grid -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Volunteer Name</th>
                        <th>CNIC</th>
                        <th>Skill (Primary)</th>
                        <th>Contact</th>
                        <th>Base Location</th>
                        <th>Contributed Hours</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 15px; color: var(--text-primary);"><?php echo htmlspecialchars($row['volunteer_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Registered: <?php echo format_date($row['registration_date']); ?></div>
                                </td>
                                <td style="font-weight: 600; color: var(--text-secondary);"><?php echo htmlspecialchars($row['cnic'] ?: '-'); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($row['primary_skill']); ?></span>
                                    <?php if ($row['secondary_skills']): ?>
                                        <div style="font-size: 11px; color: var(--text-secondary); margin-top: 3px; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($row['secondary_skills']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($row['phone']); ?></span>
                                    <div style="font-size: 11px; color: var(--text-light); margin-top: 3px;"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($row['village_name'] ?: '-'); ?></td>
                                <td style="font-weight: 700; color: var(--primary);"><?php echo number_format($row['hours_contributed']); ?> hrs</td>
                                <td>
                                    <?php 
                                    $vClass = 'badge-success';
                                    if ($row['availability_status'] == 'On Assignment') $vClass = 'badge-info';
                                    if ($row['availability_status'] == 'On Leave') $vClass = 'badge-warning';
                                    if ($row['availability_status'] == 'Inactive') $vClass = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $vClass; ?>"><?php echo $row['availability_status']; ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 8px;">
                                        <a href="?view_assignments=<?php echo $row['volunteer_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">🎯 Projects</a>
                                        <a href="?edit_id=<?php echo $row['volunteer_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">✏️ Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-light);">No volunteers found matching filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= REGISTER VOLUNTEER MODAL ================= -->
<div class="modal-overlay" id="addVolunteerModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Register New Volunteer</h3>
            <button class="btn-close-modal" onclick="closeModal('addVolunteerModal')">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="register">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="v_name">Volunteer Full Name *</label>
                        <input type="text" name="volunteer_name" id="v_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="v_cnic">CNIC (format: XXXXX-XXXXXXX-X)</label>
                        <input type="text" name="cnic" id="v_cnic" placeholder="42101-1234567-1" class="form-control" pattern="^\d{5}-\d{7}-\d{1}$">
                    </div>
                    <div class="form-group">
                        <label for="v_email">Email Address</label>
                        <input type="email" name="email" id="v_email" placeholder="example@email.com" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="v_phone">Phone Contact *</label>
                        <input type="text" name="phone" id="v_phone" placeholder="0300-1234567" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="v_dob">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="v_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="v_loc">Base Location *</label>
                        <select name="location_id" id="v_loc" class="form-control" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['location_id']; ?>">
                                    <?php echo htmlspecialchars($loc['village_name'] . " (" . $loc['district'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="v_skill">Primary Skill *</label>
                        <input type="text" name="primary_skill" id="v_skill" placeholder="e.g. Nursing, Civil Engineering, Education" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="v_reg_date">Registration Date *</label>
                        <input type="date" name="registration_date" id="v_reg_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="v_sec_skills">Secondary Skills (Comma Separated)</label>
                        <input type="text" name="secondary_skills" id="v_sec_skills" placeholder="e.g. Plumbing, IT, First Aid" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addVolunteerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Volunteer</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT VOLUNTEER MODAL ================= -->
<?php
$edit_volunteer = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_res = $conn->query("SELECT * FROM volunteers WHERE volunteer_id = $edit_id");
    if ($edit_res && $edit_res->num_rows > 0) {
        $edit_volunteer = $edit_res->fetch_assoc();
    }
}
?>
<div class="modal-overlay <?php echo $edit_volunteer ? 'active' : ''; ?>" id="editVolunteerModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Edit Volunteer: <?php echo htmlspecialchars($edit_volunteer['volunteer_name'] ?? ''); ?></h3>
            <button class="btn-close-modal" onclick="closeModal('editVolunteerModal'); window.location.href='volunteers.php';">✕</button>
        </div>
        <?php if ($edit_volunteer): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="volunteer_id" value="<?php echo $edit_volunteer['volunteer_id']; ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_v_name">Volunteer Name *</label>
                            <input type="text" name="volunteer_name" id="edit_v_name" class="form-control" value="<?php echo htmlspecialchars($edit_volunteer['volunteer_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_v_cnic">CNIC</label>
                            <input type="text" name="cnic" id="edit_v_cnic" class="form-control" value="<?php echo htmlspecialchars($edit_volunteer['cnic']); ?>" pattern="^\d{5}-\d{7}-\d{1}$">
                        </div>
                        <div class="form-group">
                            <label for="edit_v_email">Email Address</label>
                            <input type="email" name="email" id="edit_v_email" class="form-control" value="<?php echo htmlspecialchars($edit_volunteer['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_v_phone">Phone Contact *</label>
                            <input type="text" name="phone" id="edit_v_phone" class="form-control" value="<?php echo htmlspecialchars($edit_volunteer['phone']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_v_dob">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="edit_v_dob" class="form-control" value="<?php echo $edit_volunteer['date_of_birth']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_v_loc">Base Location *</label>
                            <select name="location_id" id="edit_v_loc" class="form-control" required>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo $loc['location_id']; ?>" <?php echo $edit_volunteer['location_id'] == $loc['location_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['village_name'] . " (" . $loc['district'] . ")"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_v_skill">Primary Skill *</label>
                            <input type="text" name="primary_skill" id="edit_v_skill" class="form-control" value="<?php echo htmlspecialchars($edit_volunteer['primary_skill']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_v_status">Availability Status *</label>
                            <select name="availability_status" id="edit_v_status" class="form-control" required>
                                <option value="Available" <?php echo $edit_volunteer['availability_status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                                <option value="On Assignment" <?php echo $edit_volunteer['availability_status'] === 'On Assignment' ? 'selected' : ''; ?>>On Assignment</option>
                                <option value="On Leave" <?php echo $edit_volunteer['availability_status'] === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                                <option value="Inactive" <?php echo $edit_volunteer['availability_status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="edit_v_sec_skills">Secondary Skills</label>
                            <input type="text" name="secondary_skills" id="edit_v_sec_skills" class="form-control" value="<?php echo htmlspecialchars($edit_volunteer['secondary_skills']); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editVolunteerModal'); window.location.href='volunteers.php';">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Details</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================= PROJECT ASSIGNMENTS MODAL ================= -->
<?php
$assign_vol = null;
$assignments_list = [];
if (isset($_GET['view_assignments'])) {
    $v_id = intval($_GET['view_assignments']);
    $v_res = $conn->query("SELECT * FROM volunteers WHERE volunteer_id = $v_id");
    if ($v_res && $v_res->num_rows > 0) {
        $assign_vol = $v_res->fetch_assoc();
        
        $va_res = $conn->query(
            "SELECT va.*, p.project_name, p.project_code 
             FROM volunteer_assignments va
             JOIN projects p ON va.project_id = p.project_id
             WHERE va.volunteer_id = $v_id
             ORDER BY va.status ASC, va.assignment_date DESC"
        );
        while ($va_row = $va_res->fetch_assoc()) {
            $assignments_list[] = $va_row;
        }
    }
}
?>
<div class="modal-overlay <?php echo $assign_vol ? 'active' : ''; ?>" id="assignmentsModal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">🎯 Project Assignments - <?php echo htmlspecialchars($assign_vol['volunteer_name'] ?? ''); ?></h3>
            <button class="btn-close-modal" onclick="closeModal('assignmentsModal'); window.location.href='volunteers.php';">✕</button>
        </div>
        <div class="modal-body">
            <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
                
                <!-- Existing Assignments -->
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Currently Assigned Projects</h4>
                    <div class="table-responsive">
                        <table class="table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Assign Date</th>
                                    <th>Role</th>
                                    <th>Hours Logged</th>
                                    <th>Status</th>
                                    <th style="text-align: right; width: 230px;">Log hours & Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($assignments_list) > 0): ?>
                                    <?php foreach ($assignments_list as $asg): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($asg['project_name']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 3px;"><?php echo $asg['project_code']; ?></div>
                                            </td>
                                            <td><?php echo format_date($asg['assignment_date']); ?><?php echo $asg['completion_date'] ? "<br><span style='font-size:11px;color:var(--text-light);'>to ".format_date($asg['completion_date'])."</span>" : ""; ?></td>
                                            <td style="font-weight: 600;"><?php echo htmlspecialchars($asg['role'] ?: '-'); ?></td>
                                            <td style="font-weight: 700; color: var(--primary);"><?php echo number_format($asg['hours_worked']); ?> hrs</td>
                                            <td>
                                                <?php 
                                                $asBadge = 'badge-warning';
                                                if ($asg['status'] === 'In Progress') $asBadge = 'badge-info';
                                                if ($asg['status'] === 'Completed') $asBadge = 'badge-success';
                                                if ($asg['status'] === 'Cancelled') $asBadge = 'badge-danger';
                                                ?>
                                                <span class="badge <?php echo $asBadge; ?>"><?php echo $asg['status']; ?></span>
                                            </td>
                                            <td style="text-align: right;">
                                                <?php if ($asg['status'] !== 'Completed' && $asg['status'] !== 'Cancelled'): ?>
                                                    <form method="POST" action="" style="display: flex; gap: 4px; justify-content: flex-end;">
                                                        <input type="hidden" name="action" value="update_assignment">
                                                        <input type="hidden" name="assignment_id" value="<?php echo $asg['assignment_id']; ?>">
                                                        <input type="hidden" name="volunteer_id" value="<?php echo $assign_vol['volunteer_id']; ?>">
                                                        <input 
                                                            type="number" 
                                                            name="hours_added" 
                                                            placeholder="+ Hrs" 
                                                            class="form-control" 
                                                            style="width: 60px; padding: 4px; font-size: 11px;" 
                                                            min="0" 
                                                            value="0"
                                                        >
                                                        <select name="status" class="form-control" style="width: 95px; padding: 4px; font-size: 11px;">
                                                            <option value="In Progress" <?php echo $asg['status'] == 'In Progress' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="Completed">Completed</option>
                                                            <option value="Cancelled">Cancel</option>
                                                        </select>
                                                        <button type="submit" class="btn btn-secondary" style="padding: 4px 6px; font-size: 11px;">Save</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color: var(--text-light); font-size: 12px;">Closed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--text-light); padding: 15px;">No projects assigned yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add Assignment Form -->
                <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                    <h4 style="font-size: 14px; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Assign to New Project</h4>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_assignment">
                        <input type="hidden" name="volunteer_id" value="<?php echo $assign_vol['volunteer_id'] ?? 0; ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="asg_proj">Select Project *</label>
                                <select name="project_id" id="asg_proj" class="form-control" required>
                                    <?php foreach ($projects as $pj): ?>
                                        <option value="<?php echo $pj['project_id']; ?>">
                                            <?php echo htmlspecialchars($pj['project_name'] . " (" . $pj['project_code'] . ")"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="asg_role">Volunteer Role / Designation *</label>
                                <input type="text" name="role" id="asg_role" placeholder="e.g. Field Coordinator, Medical Assistant" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="asg_date">Assignment Start Date *</label>
                                <input type="date" name="assignment_date" id="asg_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="asg_status">Initial Status *</label>
                                <select name="status" id="asg_status" class="form-control" required>
                                    <option value="Assigned">Assigned</option>
                                    <option value="In Progress" selected>In Progress</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="asg_hours">Initial Hours Worked</label>
                                <input type="number" name="hours_worked" id="asg_hours" class="form-control" min="0" value="0">
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                            <button type="submit" class="btn btn-primary">Assign Volunteer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('assignmentsModal'); window.location.href='volunteers.php';">Close</button>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
