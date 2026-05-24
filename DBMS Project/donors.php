<?php
/**
 * Donors Management Page - RDMS
 */

$page_title = "Donors";
require_once 'header.php';

$message = '';
$message_type = '';

// 1. Handle Registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $donor_name = sanitize_input($_POST['donor_name']);
    $donor_type = sanitize_input($_POST['donor_type']);
    $contact_person = sanitize_input($_POST['contact_person']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $country = sanitize_input($_POST['country']);
    $city = sanitize_input($_POST['city']);
    $address = sanitize_input($_POST['address']);
    $registration_date = sanitize_input($_POST['registration_date']);
    
    // Validate unique email
    if (!empty($email) && !validate_email($email)) {
        $message = "Invalid email format.";
        $message_type = "error";
    } else {
        $email_check_sql = !empty($email) ? "SELECT donor_id FROM donors WHERE email = '$email'" : "";
        $can_insert = true;
        if (!empty($email_check_sql)) {
            $check = $conn->query($email_check_sql);
            if ($check && $check->num_rows > 0) {
                $message = "A donor with this email address already exists.";
                $message_type = "error";
                $can_insert = false;
            }
        }
        
        if ($can_insert) {
            $email_val = !empty($email) ? "'$email'" : "NULL";
            $sql = "INSERT INTO donors (donor_name, donor_type, contact_person, email, phone, country, city, address, registration_date, total_donations, status) 
                    VALUES ('$donor_name', '$donor_type', '$contact_person', $email_val, '$phone', '$country', '$city', '$address', '$registration_date', 0.00, 'Active')";
            
            if ($conn->query($sql)) {
                $message = "Donor registered successfully!";
                $message_type = "success";
            } else {
                $message = "Error: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}

// 2. Handle Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $donor_id = intval($_POST['donor_id']);
    $donor_name = sanitize_input($_POST['donor_name']);
    $donor_type = sanitize_input($_POST['donor_type']);
    $contact_person = sanitize_input($_POST['contact_person']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $country = sanitize_input($_POST['country']);
    $city = sanitize_input($_POST['city']);
    $address = sanitize_input($_POST['address']);
    $status = sanitize_input($_POST['status']);

    if (!empty($email) && !validate_email($email)) {
        $message = "Invalid email format.";
        $message_type = "error";
    } else {
        $email_val = !empty($email) ? "'$email'" : "NULL";
        $sql = "UPDATE donors SET 
                donor_name='$donor_name', donor_type='$donor_type', contact_person='$contact_person', 
                email=$email_val, phone='$phone', country='$country', city='$city', address='$address', status='$status' 
                WHERE donor_id=$donor_id";
        
        if ($conn->query($sql)) {
            $message = "Donor details updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
}

// Filters from Query Params
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$query = "SELECT * FROM donors WHERE 1=1";
if (!empty($search)) {
    $query .= " AND (donor_name LIKE '%$search%' OR contact_person LIKE '%$search%' OR email LIKE '%$search%')";
}
if (!empty($filter_type)) {
    $query .= " AND donor_type = '$filter_type'";
}
if (!empty($filter_status)) {
    $query .= " AND status = '$filter_status'";
}
$query .= " ORDER BY donor_id DESC";
$result = $conn->query($query);
?>

<div class="container">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>🤝 Donor Directory</h1>
            <p>Manage individual, institutional, NGO, corporate and government donors supporting operations</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="openModal('addDonorModal')">➕ Register Donor</button>
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
                    placeholder="Search name, contact, email..." 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($search); ?>" 
                    style="width: 250px;"
                >
                <select name="type" class="form-control" style="width: 160px;">
                    <option value="">All Categories</option>
                    <option value="Individual" <?php echo $filter_type === 'Individual' ? 'selected' : ''; ?>>Individual</option>
                    <option value="NGO" <?php echo $filter_type === 'NGO' ? 'selected' : ''; ?>>NGO</option>
                    <option value="Corporate" <?php echo $filter_type === 'Corporate' ? 'selected' : ''; ?>>Corporate</option>
                    <option value="Government" <?php echo $filter_type === 'Government' ? 'selected' : ''; ?>>Government</option>
                </select>
                <select name="status" class="form-control" style="width: 150px;">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $filter_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Suspended" <?php echo $filter_status === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
            <?php if (!empty($search) || !empty($filter_type) || !empty($filter_status)): ?>
                <a href="donors.php" class="btn btn-secondary" style="border: none; background: #e2e8f0; color: #475569;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Donors Table Grid -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Donor Name</th>
                        <th>Type</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Total Contributions</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 15px; color: var(--text-primary);"><?php echo htmlspecialchars($row['donor_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Registered: <?php echo format_date($row['registration_date']); ?></div>
                                </td>
                                <td><span class="badge badge-info"><?php echo $row['donor_type']; ?></span></td>
                                <td><?php echo htmlspecialchars($row['contact_person'] ?: '-'); ?></td>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['city'] . ", " . $row['country']); ?></td>
                                <td style="font-weight: 700; color: var(--success-dark);">PKR <?php echo number_format($row['total_donations']); ?></td>
                                <td>
                                    <?php 
                                    $sClass = 'badge-success';
                                    if ($row['status'] == 'Inactive') $sClass = 'badge-warning';
                                    if ($row['status'] == 'Suspended') $sClass = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $sClass; ?>"><?php echo $row['status']; ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="?edit_id=<?php echo $row['donor_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">✏️ Edit</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-light);">No donors found matching filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= REGISTER DONOR MODAL ================= -->
<div class="modal-overlay" id="addDonorModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Register New Donor</h3>
            <button class="btn-close-modal" onclick="closeModal('addDonorModal')">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="register">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="d_name">Donor / Company Name *</label>
                        <input type="text" name="donor_name" id="d_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="d_type">Donor Category *</label>
                        <select name="donor_type" id="d_type" class="form-control" required>
                            <option value="Individual">Individual</option>
                            <option value="NGO">NGO</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Government">Government</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="d_contact">Contact Person</label>
                        <input type="text" name="contact_person" id="d_contact" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="d_email">Email Address</label>
                        <input type="email" name="email" id="d_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="d_phone">Phone Contact *</label>
                        <input type="text" name="phone" id="d_phone" placeholder="e.g. +923001234567" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="d_country">Country *</label>
                        <input type="text" name="country" id="d_country" value="Pakistan" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="d_city">City *</label>
                        <input type="text" name="city" id="d_city" placeholder="Karachi" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="d_date">Registration Date *</label>
                        <input type="date" name="registration_date" id="d_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="d_address">Postal Address</label>
                        <textarea name="address" id="d_address" class="form-control"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDonorModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Donor</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT DONOR MODAL ================= -->
<?php
$edit_donor = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_res = $conn->query("SELECT * FROM donors WHERE donor_id = $edit_id");
    if ($edit_res && $edit_res->num_rows > 0) {
        $edit_donor = $edit_res->fetch_assoc();
    }
}
?>
<div class="modal-overlay <?php echo $edit_donor ? 'active' : ''; ?>" id="editDonorModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="font-size: 18px;">Edit Donor: <?php echo htmlspecialchars($edit_donor['donor_name'] ?? ''); ?></h3>
            <button class="btn-close-modal" onclick="closeModal('editDonorModal'); window.location.href='donors.php';">✕</button>
        </div>
        <?php if ($edit_donor): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="donor_id" value="<?php echo $edit_donor['donor_id']; ?>">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_d_name">Donor Name *</label>
                            <input type="text" name="donor_name" id="edit_d_name" class="form-control" value="<?php echo htmlspecialchars($edit_donor['donor_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_d_type">Donor Category *</label>
                            <select name="donor_type" id="edit_d_type" class="form-control" required>
                                <option value="Individual" <?php echo $edit_donor['donor_type'] == 'Individual' ? 'selected' : ''; ?>>Individual</option>
                                <option value="NGO" <?php echo $edit_donor['donor_type'] == 'NGO' ? 'selected' : ''; ?>>NGO</option>
                                <option value="Corporate" <?php echo $edit_donor['donor_type'] == 'Corporate' ? 'selected' : ''; ?>>Corporate</option>
                                <option value="Government" <?php echo $edit_donor['donor_type'] == 'Government' ? 'selected' : ''; ?>>Government</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_d_contact">Contact Person</label>
                            <input type="text" name="contact_person" id="edit_d_contact" class="form-control" value="<?php echo htmlspecialchars($edit_donor['contact_person']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_d_email">Email Address</label>
                            <input type="email" name="email" id="edit_d_email" class="form-control" value="<?php echo htmlspecialchars($edit_donor['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_d_phone">Phone Contact *</label>
                            <input type="text" name="phone" id="edit_d_phone" class="form-control" value="<?php echo htmlspecialchars($edit_donor['phone']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_d_country">Country *</label>
                            <input type="text" name="country" id="edit_d_country" class="form-control" value="<?php echo htmlspecialchars($edit_donor['country']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_d_city">City *</label>
                            <input type="text" name="city" id="edit_d_city" class="form-control" value="<?php echo htmlspecialchars($edit_donor['city']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_d_status">Donor Status *</label>
                            <select name="status" id="edit_d_status" class="form-control" required>
                                <option value="Active" <?php echo $edit_donor['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $edit_donor['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Suspended" <?php echo $edit_donor['status'] == 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="edit_d_address">Postal Address</label>
                            <textarea name="address" id="edit_d_address" class="form-control"><?php echo htmlspecialchars($edit_donor['address']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editDonorModal'); window.location.href='donors.php';">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Details</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
