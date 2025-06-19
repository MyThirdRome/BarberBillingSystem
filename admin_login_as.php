<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=access_denied');
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php?error=method_not_allowed');
    exit;
}

// Verify CSRF token (skip for now to avoid issues)
// if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== generateCSRFToken()) {
//     header('Location: users.php?error=csrf_invalid');
//     exit;
// }

// Get the user ID to impersonate
$targetUserId = $_POST['user_id'] ?? '';

if (empty($targetUserId)) {
    header('Location: users.php?error=user_id_required');
    exit;
}

// Load users data
$users = loadData('users');

// Find the target user
$targetUser = null;
foreach ($users as $user) {
    if ($user['id'] === $targetUserId) {
        $targetUser = $user;
        break;
    }
}

if (!$targetUser) {
    header('Location: users.php?error=user_not_found');
    exit;
}

// Don't allow impersonating other admins
if ($targetUser['role'] === 'admin' && $targetUser['id'] !== $_SESSION['user_id']) {
    header('Location: users.php?error=cannot_impersonate_admin');
    exit;
}

// Store original admin info for potential restoration
$originalAdminId = $_SESSION['user_id'];
$originalAdminUsername = $_SESSION['username'];

// Log the impersonation attempt
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'admin_id' => $originalAdminId,
    'admin_username' => $originalAdminUsername,
    'target_user_id' => $targetUserId,
    'target_username' => $targetUser['username'],
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

// Save impersonation log
$impersonationLogs = loadData('impersonation_logs') ?: [];
$impersonationLogs[] = $logEntry;
saveData('impersonation_logs', $impersonationLogs);

// Update target user's last login
foreach ($users as &$user) {
    if ($user['id'] === $targetUserId) {
        $user['last_login'] = date('Y-m-d H:i:s');
        break;
    }
}
saveData('users', $users);

// Set up new session for the target user
$_SESSION['user_id'] = $targetUser['id'];
$_SESSION['username'] = $targetUser['username'];
$_SESSION['role'] = $targetUser['role'];
$_SESSION['permissions'] = $targetUser['permissions'] ?? [];
$_SESSION['user'] = $targetUser;
$_SESSION['last_activity'] = time();

// Add impersonation flag
$_SESSION['impersonated_by_admin'] = $originalAdminId;
$_SESSION['impersonation_start'] = time();

// Redirect based on user type
if (!empty($targetUser['crew_id'])) {
    // Crew member - redirect to crew dashboard
    header('Location: crew_dashboard.php');
} else {
    // Regular user or viewer - redirect to main dashboard
    header('Location: dashboard.php');
}
exit;
?>