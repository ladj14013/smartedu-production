<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';

// Logout user
logout_user();

// Redirect to home page
header("Location: ../index.php");
exit();
?>
