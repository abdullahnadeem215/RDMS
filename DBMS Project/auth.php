<?php
/**
 * Authentication Module
 * Handles user login, logout, and session management
 */

session_start();

include 'Backend/config.php';

class AuthManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Authenticate user with username and password
     */
    public function login($username, $password) {
        $username = sanitize_input($username);
        
        $query = "SELECT user_id, username, email, full_name, role, status, password_hash 
                  FROM users WHERE username = '$username' AND status = 'Active'";
        
        $result = $this->conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (verify_password($password, $user['password_hash'])) {
                // Update last login
                $user_id = $user['user_id'];
                $this->conn->query("UPDATE users SET last_login = NOW() WHERE user_id = '$user_id'");
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                return array('success' => true, 'message' => 'Login successful');
            } else {
                return array('success' => false, 'message' => 'Invalid password');
            }
        }
        
        return array('success' => false, 'message' => 'User not found or account inactive');
    }
    
    /**
     * Check if user is logged in
     */
    public static function is_logged_in() {
        return isset($_SESSION['user_id']) && isset($_SESSION['login_time']);
    }
    
    /**
     * Check session timeout
     */
    public static function check_session_timeout() {
        if (self::is_logged_in()) {
            $current_time = time();
            $login_time = $_SESSION['login_time'];
            
            if (($current_time - $login_time) > SESSION_TIMEOUT) {
                self::logout();
                return false;
            }
            
            // Reset login time on each activity
            $_SESSION['login_time'] = $current_time;
            return true;
        }
        return false;
    }
    
    /**
     * User logout
     */
    public static function logout() {
        session_destroy();
        redirect('/login.php');
    }
    
    /**
     * Check user permission by role
     */
    public static function has_permission($required_role) {
        if (!self::is_logged_in()) {
            return false;
        }
        
        $user_role = $_SESSION['role'];
        
        // Define role hierarchy
        $role_hierarchy = array(
            'Admin' => 5,
            'Auditor' => 4,
            'Manager' => 3,
            'Field Worker' => 2,
            'Donor' => 1
        );
        
        if (isset($role_hierarchy[$user_role]) && isset($role_hierarchy[$required_role])) {
            return $role_hierarchy[$user_role] >= $role_hierarchy[$required_role];
        }
        
        return false;
    }
    
    /**
     * Register new user (Admin only)
     */
    public function register_user($data) {
        $username = sanitize_input($data['username']);
        $email = sanitize_input($data['email']);
        $full_name = sanitize_input($data['full_name']);
        $role = sanitize_input($data['role']);
        $password = sanitize_input($data['password']);
        
        // Validation
        if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
            return array('success' => false, 'message' => 'All fields are required');
        }
        
        if (!validate_email($email)) {
            return array('success' => false, 'message' => 'Invalid email format');
        }
        
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            return array('success' => false, 'message' => 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters');
        }
        
        // Check if username or email already exists
        $check_query = "SELECT user_id FROM users WHERE username = '$username' OR email = '$email'";
        $result = $this->conn->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            return array('success' => false, 'message' => 'Username or email already exists');
        }
        
        // Hash password and insert
        $password_hash = hash_password($password);
        $query = "INSERT INTO users (username, email, full_name, role, password_hash, status) 
                  VALUES ('$username', '$email', '$full_name', '$role', '$password_hash', 'Active')";
        
        if ($this->conn->query($query)) {
            return array('success' => true, 'message' => 'User registered successfully');
        } else {
            return array('success' => false, 'message' => 'Error registering user: ' . $this->conn->error);
        }
    }
    
    /**
     * Change password
     */
    public function change_password($user_id, $old_password, $new_password) {
        $user_id = sanitize_input($user_id);
        
        // Get current password hash
        $result = $this->conn->query("SELECT password_hash FROM users WHERE user_id = '$user_id'");
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (!verify_password($old_password, $user['password_hash'])) {
                return array('success' => false, 'message' => 'Current password is incorrect');
            }
            
            if (strlen($new_password) < MIN_PASSWORD_LENGTH) {
                return array('success' => false, 'message' => 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters');
            }
            
            $new_password_hash = hash_password($new_password);
            $update_query = "UPDATE users SET password_hash = '$new_password_hash' WHERE user_id = '$user_id'";
            
            if ($this->conn->query($update_query)) {
                return array('success' => true, 'message' => 'Password changed successfully');
            } else {
                return array('success' => false, 'message' => 'Error changing password');
            }
        }
        
        return array('success' => false, 'message' => 'User not found');
    }
}

// Create auth manager instance
$auth = new AuthManager($conn);

// Check session timeout on every page load
if (AuthManager::is_logged_in() && !in_array($_SERVER['PHP_SELF'], array('/login.php', '/logout.php'))) {
    if (!AuthManager::check_session_timeout()) {
        redirect('/login.php?timeout=1');
    }
}

?>
