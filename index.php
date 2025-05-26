<?php
session_start();
require_once 'includes/config.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Redirect to login page
header('Location: login.php');
exit;
?>
