<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Clear any existing output buffers
ob_clean();

// Redirect to login page with logout message
$_SESSION['logout_message'] = "You have been successfully logged out.";
header("Location: login.php");
exit();
?>