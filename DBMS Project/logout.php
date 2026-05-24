<?php
/**
 * Logout - Handle user session termination
 */

session_start();
include 'Backend/config.php';
include 'auth.php';

// Destroy session
AuthManager::logout();

header('Location: index.php');
exit();

?>
