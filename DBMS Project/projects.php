<?php
/**
 * Projects Management Page - RDMS
 */

$page_title = "Projects";
require_once 'header.php';

$message = '';
$message_type = '';

// 1. Handle Add Project POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project') {
    $project_name = sanitize_input($_POST['project_name']);
    $project_code = sanitize_input($_POST['project_code']);
    $project_type = sanitize_input($_POST['project_type']);
    $description = sanitize_input($_POST['description']);
    $start_date = sanitize_input($_POST['start_date']);
    $expected_end_date = sanitize_input($_POST['expected_end_date']);
    $budget = floatval($_POST['budget']);
    $responsible_officer = sanitize_input($_POST['responsible_officer']);
    
    // Check code uniqueness
    $check = $conn->query("SELECT project_id FROM projects WHERE project_code = '$project_code'");
    if ($check && $check->num_rows > 0) {
        $message = "A project with this project code already exists.";
        $message_type = "error";
    } else {
        $expected_end_date_sql = !empty($expected_end_date) ? "'$expected_end_date'" : "NULL";
        $sql = "INSERT INTO projects (project_name, project_code, project_type, description, start_date, expected_end_date, budget, budget_used, status, responsible_officer) 
                VALUES ('$project_name', '$project_code', '$project_type', '$description', '$start_date', $expected_end_date_sql, $budget, 0.00, 'Planning', '$responsible_officer')";
        
        if ($conn->query($sql)) {
            $message = "Project registered successfully!";
            $message_type = "success";
        } else {
            $message = "Error registering project: " . $conn->error;
            $message_type = "error";
        }
    }
}

// 2. Handle Update Project POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_project') {
    $project_id = intval($_POST['project_id']);
    $project_name = sanitize_input($_POST['project_name']);
    $project_code = sanitize_input($_POST['project_code']);
    $project_type = sanitize_input($_POST['project_type']);
    $description = sanitize_input($_POST['description']);
    $start_date = sanitize_input($_POST['start_date']);
    $expected_end_date = sanitize_input($_POST['expected_end_date']);
    $actual_end_date = sanitize_input($_POST['actual_end_date']);
    $budget = floatval($_POST['budget']);
    $budget_used = floatval($_POST['budget_used']);
    $status = sanitize_input($_POST['status']);
    $responsible_officer = sanitize_input($_POST['responsible_officer']);

    if ($budget_used > $budget) {
        $message = "Budget used cannot exceed the total project budget.";
        $message_type = "error";
    } else {
        $expected_end_date_sql = !empty($expected_end_date) ? "'$expected_end_date'" : "NULL";
        $actual_end_date_sql = !empty($actual_end_date) ? "'$actual_end_date'" : "NULL";
        
        $sql = "UPDATE projects SET 
                project_name='$project_name', project_code='$project_code', project_type='$project_type', 
                description='$description', start_date='$start_date', expected_end_date=$expected_end_date_sql, 
                actual_end_date=$actual_end_date_sql, budget=$budget, budget_used=$budget_used, 
                status='$status', responsible_officer='$responsible_officer' 
                WHERE project_id=$project_id";
        
        if ($conn->query($sql)) {
            $message = "Project details updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating project: " . $conn->error;
            $message_type = "error";
        }
    }
}

// 3. Handle Link Location POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_location') {
    $project_id = intval($_POST['project_id']);
    $location_id = intval($_POST['location_id']);
    $target_beneficiaries = intval($_POST['target_beneficiaries']);
    $actual_beneficiaries = intval($_POST['actual_beneficiaries']);
    
    // Check if link already exists
    $check = $conn->query("SELECT project_location_id FROM project_locations WHERE project_id = $project_id AND location_id = $location_id");
    if ($check && $check->num_rows > 0) {
        $message = "This location is already mapped to this project.";
        $message_type = "error";
    } else {
        $sql = "INSERT INTO project_locations (project_id, location_id, target_beneficiaries, actual_beneficiaries) 
                VALUES ($project_id, $location_id, $target_beneficiaries, $actual_beneficiaries)";
        if ($conn->query($sql)) {
            header("Location: projects.php?view_locations=$project_id&success_msg=Location mapped successfully");
            exit();
        } else {
            $message = "Error mapping location: " . $conn->error;
            $message_type = "error";
        }
    }
}

// 4. Handle Update Actual Beneficiaries reached
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_beneficiaries') {
    $pl_id = intval($_POST['project_location_id']);
    $p_id = intval($_POST['project_id']);
    $actual = intval($_POST['actual_beneficiaries']);
    
    $sql = "UPDATE project_locations SET actual_beneficiaries = $actual WHERE project_location_id = $pl_id";
    if ($conn->query($sql)) {
        header("Location: projects.php?view_locations=$p_id&success_msg=Beneficiary reach updated");
        exit();
    } else {
        $message = "Error updating reach: " . $conn->error;
        $message_type = "error";
    }
}

// Check for redirect message
if (isset($_GET['success_msg'])) {
    $message = htmlspecialchars($_GET['success_msg']);
    $message_type = "success";
}

// Fetch Locations list for select dropdown
$locations_list = $conn->query("SELECT location_id, village_name, district FROM locations ORDER BY village_name");
$locations = [];
while ($row = $locations_list->fetch_assoc()) {
    $locations[] = $row;
}

// Fetch Projects with optional status/type filter
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$filter_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$query = "SELECT * FROM projects WHERE 1=1";
if (!empty($filter_status)) {
    $query .= " AND status = '$filter_status'";
}
if (!empty($filter_type)) {
    $query .= " AND project_type = '$filter_type'";
}
if (!empty($search)) {
    $query .= " AND (project_name LIKE '%$search%' OR project_code LIKE '%$search%' OR responsible_officer LIKE '%$search%')";
}
$query .= " ORDER BY project_id DESC";
$result = $conn->query($query);
?>

<div class="container">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>🎯 Development Projects</h1>
            <p>Track community development programs, timelines, locations, and financial budget utilization</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="openModal('addProjectModal')">➕ New Project</button>
        </div>
    </div>

    <!-- Notifications -->
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
                    placeholder="Search name, code or officer..." 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($search); ?>" 
                    style="width: 250px;"
                >
                <select name="type" class="form-control" style="width: 160px;">
                    <option value="">All Categories</option>
                    <option value="Water" <?php echo $filter_type === 'Water' ? 'selected' : ''; ?>>Water</option>
                    <option value="Health" <?php echo $filter_type === 'Health' ? 'selected' : ''; ?>>Health</option>
                    <option value="Education" <?php echo $filter_type === 'Education' ? 'selected' : ''; ?>>Education</option>
                    <option value="Infrastructure" <?php echo $filter_type === 'Infrastructure' ? 'selected' : ''; ?>>Infrastructure</option>
                    <option value="Livelihood" <?php echo $filter_type === 'Livelihood' ? 'selected' : ''; ?>>Livelihood</option>
                </select>
                <select name="status" class="form-control" style="width: 150px;">
                    <option value="">All Statuses</option>
                    <option value="Planning" <?php echo $filter_status === 'Planning' ? 'selected' : ''; ?>>Planning</option>
                    <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Completed" <?php echo $filter_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Suspended" <?php echo $filter_status === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
            <?php if (!empty($search) || !empty($filter_type) || !empty($filter_status)): ?>
                <a href="projects.php" class="btn btn-secondary" style="border: none; background: #e2e8f0; color: #475569;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Projects Table Grid -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Project Name</th>
                        <th>Type</th>
                        <th>Budget Status</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Officer</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php 
                            $utilization = 0;
                            if ($row['budget'] > 0) {
                                $utilization = round(($row['budget_used'] / $row['budget']) * 100, 1);
                            }
                            $barClass = 'success';
                            if ($utilization > 80) $barClass = 'warning';
                            if ($utilization > 98) $barClass = 'danger';
                            ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-secondary);"><?php echo htmlspecialchars($row['project_code']); ?></td>
                                <td>
                                    <strong style="font-size: 15px; color: var(--text-primary);"><?php echo htmlspecialchars($row['project_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--text-secondary); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 4px;"><?php echo htmlspecialchars($row['description']); ?></div>
                                </td>
                                <td><span class="badge badge-info"><?php echo $row['project_type']; ?></span></td>
                                <td>
                                    <div class="progress-container">
                                        <div class="progress-label">
                                            <span>PKR <?php echo number_format($row['budget_used']); ?> / <?php echo number_format($row['budget']); ?></span>
                                            <span><?php echo $utilization; ?>%</span>
                                        </div>
                                        <div class="progress-bar-bg">
                                            <div class="progress-bar-fill <?php echo $barClass; ?>" style="width: <?php echo min($utilization, 100); ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo format_date($row['start_date']); ?></td>
                                <td><?php echo $row['actual_end_date'] ? format_date($row['actual_end_date']) : ($row['expected_end_date'] ? format_date($row['expected_end_date']) : '-'); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($row['responsible_officer'] ?: '-'); ?></td>
                                <td>
                                    <?php 
                                    $sClass = 'badge-success';
                                    if ($row['status'] == 'Planning') $sClass = 'badge-info';
                                    if ($row['status'] == 'Completed') $sClass = 'badge-success';
                                    if ($row['status'] == 'Suspended') $sClass = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $sClass; ?>"><?php echo $row['status']; ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 8px;">
                                        <a href="?view_locations=<?php echo $row['project_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">🏘️ Reach</a>
                                        <a href="?edit_id=<?php echo $row['project_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">✏️ Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-light);">No projects found matching filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= NEW PROJECT MODAL ================= -->
<div class="modal-overlay <?php echo (isset($_GET['action']) && $_GET['action'] == 'add') ? 'active' : ''; ?>" id="addProjectModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Register New Project</h3>
            <button class="btn-close-modal" onclick="closeModal('addProjectModal'); window.history.replaceState({}, '', 'projects.php');">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_project">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="p_name">Project Name *</label>
                        <input type="text" name="project_name" id="p_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="p_code">Project Code *</label>
                        <input type="text" name="project_code" id="p_code" placeholder="e.g. WATER-2024" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="p_type">Project Type / Sector *</label>
                        <select name="project_type" id="p_type" class="form-control" required>
                            <option value="Water">Water</option>
                            <option value="Health">Health</option>
                            <option value="Education">Education</option>
                            <option value="Infrastructure">Infrastructure</option>
                            <option value="Livelihood">Livelihood</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="p_budget">Project Budget (PKR) *</label>
                        <input type="number" name="budget" id="p_budget" class="form-control" min="1" value="100000" required>
                    </div>
                    <div class="form-group">
                        <label for="p_start">Start Date *</label>
                        <input type="date" name="start_date" id="p_start" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="p_expected">Expected Completion Date</label>
                        <input type="date" name="expected_end_date" id="p_expected" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="p_officer">Responsible Officer *</label>
                        <input type="text" name="responsible_officer" id="p_officer" class="form-control" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="p_desc">Project Description</label>
                        <textarea name="description" id="p_desc" placeholder="Detail the objectives and targets of this project..." class="form-control"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal'); window.history.replaceState({}, '', 'projects.php');">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Project</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT PROJECT MODAL ================= -->
<?php
$edit_project = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_res = $conn->query("SELECT * FROM projects WHERE project_id = $edit_id");
    if ($edit_res && $edit_res->num_rows > 0) {
        $edit_project = $edit_res->fetch_assoc();
    }
}
?>
<div class="modal-overlay <?php echo $edit_project ? 'active' : ''; ?>" id="editProjectModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Edit Project: <?php echo htmlspecialchars($edit_project['project_code'] ?? ''); ?></h3>
            <button class="btn-close-modal" onclick="closeModal('editProjectModal'); window.location.href='projects.php';">✕</button>
        </div>
        <?php if ($edit_project): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_project">
                <input type="hidden" name="project_id" value="<?php echo $edit_project['project_id']; ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_p_name">Project Name *</label>
                            <input type="text" name="project_name" id="edit_p_name" class="form-control" value="<?php echo htmlspecialchars($edit_project['project_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_p_code">Project Code *</label>
                            <input type="text" name="project_code" id="edit_p_code" class="form-control" value="<?php echo htmlspecialchars($edit_project['project_code']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_p_type">Project Type *</label>
                            <select name="project_type" id="edit_p_type" class="form-control" required>
                                <option value="Water" <?php echo $edit_project['project_type'] == 'Water' ? 'selected' : ''; ?>>Water</option>
                                <option value="Health" <?php echo $edit_project['project_type'] == 'Health' ? 'selected' : ''; ?>>Health</option>
                                <option value="Education" <?php echo $edit_project['project_type'] == 'Education' ? 'selected' : ''; ?>>Education</option>
                                <option value="Infrastructure" <?php echo $edit_project['project_type'] == 'Infrastructure' ? 'selected' : ''; ?>>Infrastructure</option>
                                <option value="Livelihood" <?php echo $edit_project['project_type'] == 'Livelihood' ? 'selected' : ''; ?>>Livelihood</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_p_budget">Project Budget (PKR) *</label>
                            <input type="number" name="budget" id="edit_p_budget" class="form-control" value="<?php echo intval($edit_project['budget']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_p_used">Budget Spent/Used (PKR) *</label>
                            <input type="number" name="budget_used" id="edit_p_used" class="form-control" value="<?php echo intval($edit_project['budget_used']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_p_status">Status *</label>
                            <select name="status" id="edit_p_status" class="form-control" required>
                                <option value="Planning" <?php echo $edit_project['status'] == 'Planning' ? 'selected' : ''; ?>>Planning</option>
                                <option value="Active" <?php echo $edit_project['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Completed" <?php echo $edit_project['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Suspended" <?php echo $edit_project['status'] == 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_p_start">Start Date *</label>
                            <input type="date" name="start_date" id="edit_p_start" class="form-control" value="<?php echo $edit_project['start_date']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_p_expected">Expected End Date</label>
                            <input type="date" name="expected_end_date" id="edit_p_expected" class="form-control" value="<?php echo $edit_project['expected_end_date']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_p_actual">Actual End Date</label>
                            <input type="date" name="actual_end_date" id="edit_p_actual" class="form-control" value="<?php echo $edit_project['actual_end_date']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_p_officer">Responsible Officer *</label>
                            <input type="text" name="responsible_officer" id="edit_p_officer" class="form-control" value="<?php echo htmlspecialchars($edit_project['responsible_officer']); ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="edit_p_desc">Project Description</label>
                            <textarea name="description" id="edit_p_desc" class="form-control"><?php echo htmlspecialchars($edit_project['description']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal'); window.location.href='projects.php';">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Details</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================= PROJECT LOCATIONS MODAL ================= -->
<?php
$loc_project = null;
$linked_locations = [];
if (isset($_GET['view_locations'])) {
    $p_id = intval($_GET['view_locations']);
    $p_res = $conn->query("SELECT * FROM projects WHERE project_id = $p_id");
    if ($p_res && $p_res->num_rows > 0) {
        $loc_project = $p_res->fetch_assoc();
        
        $pl_res = $conn->query(
            "SELECT pl.*, l.village_name, l.district, l.province 
             FROM project_locations pl
             JOIN locations l ON pl.location_id = l.location_id
             WHERE pl.project_id = $p_id"
        );
        while ($l_row = $pl_res->fetch_assoc()) {
            $linked_locations[] = $l_row;
        }
    }
}
?>
<div class="modal-overlay <?php echo $loc_project ? 'active' : ''; ?>" id="locationsModal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">🏘️ Locations Covered - <?php echo htmlspecialchars($loc_project['project_name'] ?? ''); ?></h3>
            <button class="btn-close-modal" onclick="closeModal('locationsModal'); window.location.href='projects.php';">✕</button>
        </div>
        <div class="modal-body">
            <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
                
                <!-- Linked Locations Table -->
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Currently Covered Villages</h4>
                    <div class="table-responsive">
                        <table class="table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Village</th>
                                    <th>District (Province)</th>
                                    <th>Target Reach</th>
                                    <th>Actual Reached</th>
                                    <th style="text-align: right; width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($linked_locations) > 0): ?>
                                    <?php foreach ($linked_locations as $link): ?>
                                        <tr>
                                            <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($link['village_name']); ?></td>
                                            <td><?php echo htmlspecialchars($link['district'] . ", " . $link['province']); ?></td>
                                            <td style="font-weight: 600;"><?php echo number_format($link['target_beneficiaries']); ?> reached</td>
                                            <td style="font-weight: 700; color: var(--success-dark);"><?php echo number_format($link['actual_beneficiaries']); ?> reached</td>
                                            <td style="text-align: right;">
                                                <!-- Form to update actual beneficiaries inline -->
                                                <form method="POST" action="" style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                                    <input type="hidden" name="action" value="update_beneficiaries">
                                                    <input type="hidden" name="project_location_id" value="<?php echo $link['project_location_id']; ?>">
                                                    <input type="hidden" name="project_id" value="<?php echo $loc_project['project_id']; ?>">
                                                    <input 
                                                        type="number" 
                                                        name="actual_beneficiaries" 
                                                        class="form-control" 
                                                        style="width: 80px; padding: 4px 8px; font-size: 12px; text-align: center;" 
                                                        value="<?php echo $link['actual_beneficiaries']; ?>" 
                                                        min="0"
                                                    >
                                                    <button type="submit" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Update</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-light); padding: 15px;">No geographical reach registered yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add Location Coverage Form -->
                <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                    <h4 style="font-size: 14px; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Map Project to New Location</h4>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_location">
                        <input type="hidden" name="project_id" value="<?php echo $loc_project['project_id'] ?? 0; ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="map_loc">Select Village *</label>
                                <select name="location_id" id="map_loc" class="form-control" required>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo $loc['location_id']; ?>">
                                            <?php echo htmlspecialchars($loc['village_name'] . " (" . $loc['district'] . ")"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="map_target">Target Beneficiaries (Count) *</label>
                                <input type="number" name="target_beneficiaries" id="map_target" class="form-control" min="1" value="100" required>
                            </div>
                            <div class="form-group">
                                <label for="map_actual">Actual Beneficiaries Reached (Count) *</label>
                                <input type="number" name="actual_beneficiaries" id="map_actual" class="form-control" min="0" value="0" required>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                            <button type="submit" class="btn btn-primary">Link Location</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('locationsModal'); window.location.href='projects.php';">Close</button>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
