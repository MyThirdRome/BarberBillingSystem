<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Start session
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Accès refusé. Seuls les administrateurs peuvent utiliser cette fonctionnalité.');
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Méthode non autorisée.');
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== generateCSRFToken()) {
    http_response_code(403);
    die('Token CSRF invalide.');
}

// Get the user ID to impersonate
$targetUserId = $_POST['user_id'] ?? '';

if (empty($targetUserId)) {
    http_response_code(400);
    die('ID utilisateur requis.');
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
    http_response_code(404);
    die('Utilisateur non trouvé.');
}

// Don't allow impersonating other admins
if ($targetUser['role'] === 'admin' && $targetUser['id'] !== $_SESSION['user_id']) {
    http_response_code(403);
    die('Impossible de se connecter en tant qu\'autre administrateur.');
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