<?php
/**
 * Global Header - RDMS
 * Standard page template header with authentication guards and layout
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'Backend/config.php';
require_once 'auth.php';

// Auth Guard
if (!AuthManager::is_logged_in()) {
    redirect('/login.php');
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " | RDMS" : "Rural Development Management System"; ?></title>
    <link rel="stylesheet" href="Frontend/style.css">
</head>
<body>
    <!-- Navbar Component -->
    <div class="navbar">
        <div class="navbar-brand">
            <span>🌾</span> RDMS
        </div>
        <div class="navbar-menu">
            <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="beneficiaries.php" class="<?php echo $current_page === 'beneficiaries.php' ? 'active' : ''; ?>">Beneficiaries</a>
            <a href="projects.php" class="<?php echo $current_page === 'projects.php' ? 'active' : ''; ?>">Projects</a>
            <a href="donors.php" class="<?php echo $current_page === 'donors.php' ? 'active' : ''; ?>">Donors</a>
            <a href="donations.php" class="<?php echo $current_page === 'donations.php' ? 'active' : ''; ?>">Donations</a>
            <a href="aid-distribution.php" class="<?php echo $current_page === 'aid-distribution.php' ? 'active' : ''; ?>">Aid Distribution</a>
            <a href="volunteers.php" class="<?php echo $current_page === 'volunteers.php' ? 'active' : ''; ?>">Volunteers</a>
            <a href="reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">Reports</a>
        </div>
        <div class="navbar-user">
            <div class="user-info">
                <strong><?php echo htmlspecialchars($user_name); ?></strong>
                <p><?php echo htmlspecialchars($user_role); ?></p>
            </div>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
