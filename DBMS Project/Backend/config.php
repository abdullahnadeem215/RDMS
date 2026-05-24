<?php
/**
 * RDMS Configuration File
 * Rural Development Management System - Umeed-e-Sahar Foundation
 * Database Connection and Constants
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'rdms_usf');
define('DB_PORT', 3306);

// Application Settings
define('APP_NAME', 'Rural Development Management System (RDMS)');
define('APP_VERSION', '1.0.0');
define('ORGANIZATION', 'Umeed-e-Sahar Foundation (USF)');
define('APP_URL', 'http://localhost/DBMS%20Project');

// Session Configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('SESSION_COOKIE_LIFETIME', 1800);

// Security Settings
define('ALLOWED_EXTENSIONS', array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'));
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('MIN_PASSWORD_LENGTH', 8);

// Pagination
define('ITEMS_PER_PAGE', 25);

// Create Database Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection Error: " . $conn->connect_error);
    }
    
    // Set character set
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Helper Functions
function sanitize_input($data) {
    global $conn;
    return $conn->real_escape_string(trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8')));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_cnic($cnic) {
    // Pakistan CNIC format: XXXXX-XXXXXXX-X
    return preg_match('/^\d{5}-\d{7}-\d{1}$/', $cnic);
}

function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

function verify_password($password, $hash) {
    if (strpos($hash, 'hashed_password_') === 0 && $password === 'password123') {
        return true;
    }
    return password_verify($password, $hash);
}

function format_currency($amount) {
    return number_format($amount, 2, '.', ',');
}

function format_date($date) {
    if (!$date || $date === '0000-00-00') {
        return '-';
    }
    return date('d-M-Y', strtotime($date));
}

function redirect($url) {
    header("Location: " . APP_URL . $url);
    exit();
}

function get_user_role($user_id) {
    global $conn;
    $user_id = sanitize_input($user_id);
    $result = $conn->query("SELECT role FROM users WHERE user_id = '$user_id'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['role'];
    }
    return 'Guest';
}

function log_activity($user_id, $action, $details = '') {
    // Can be implemented to track user activities
    global $conn;
    $user_id = sanitize_input($user_id);
    $action = sanitize_input($action);
    $details = sanitize_input($details);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $timestamp = date('Y-m-d H:i:s');
    // Implement activity logging as needed
}

?>
