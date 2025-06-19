<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Start session
session_start();

// Check if user is currently impersonating
if (!isset($_SESSION['impersonated_by_admin'])) {
    header('Location: dashboard.php');
    exit;
}

// Get original admin ID
$originalAdminId = $_SESSION['impersonated_by_admin'];

// Load users data to restore admin session
$users = loadData('users');
$adminUser = null;

foreach ($users as $user) {
    if ($user['id'] === $originalAdminId && $user['role'] === 'admin') {
        $adminUser = $user;
        break;
    }
}

if (!$adminUser) {
    // If admin user not found, logout completely
    session_destroy();
    header('Location: login.php?error=admin_not_found');
    exit;
}

// Log the return action
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'admin_id' => $originalAdminId,
    'returned_from_user_id' => $_SESSION['user_id'],
    'returned_from_username' => $_SESSION['username'],
    'impersonation_duration' => time() - ($_SESSION['impersonation_start'] ?? time()),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

$impersonationLogs = loadData('impersonation_logs') ?: [];
$impersonationLogs[] = $logEntry;
saveData('impersonation_logs', $impersonationLogs);

// Restore admin session
$_SESSION['user_id'] = $adminUser['id'];
$_SESSION['username'] = $adminUser['username'];
$_SESSION['role'] = $adminUser['role'];
$_SESSION['permissions'] = $adminUser['permissions'] ?? [];
$_SESSION['user'] = $adminUser;
$_SESSION['last_activity'] = time();

// Remove impersonation flags
unset($_SESSION['impersonated_by_admin']);
unset($_SESSION['impersonation_start']);

// Update admin's last login
foreach ($users as &$user) {
    if ($user['id'] === $adminUser['id']) {
        $user['last_login'] = date('Y-m-d H:i:s');
        break;
    }
}
saveData('users', $users);

// Redirect to users page
header('Location: users.php?returned=1');
exit;
?>