<?php
require_once 'config.php';
require_once 'functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Update user's last activity in database
$users = loadData('users');
foreach ($users as &$user) {
    if ($user['id'] === $_SESSION['user_id']) {
        $user['last_activity'] = date('Y-m-d H:i:s');
        if (!isset($user['login_count'])) {
            $user['login_count'] = 0;
        }
        break;
    }
}
saveData('users', $users);

// Helper function to check permissions
function checkPermission($required_permission) {
    // Admin has all permissions
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Check if user has the required permission
    $user_permissions = $_SESSION['permissions'] ?? [];
    
    if (!in_array($required_permission, $user_permissions)) {
        header('HTTP/1.0 403 Forbidden');
        die('
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Accès Refusé</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
            <div class="text-center">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <i class="fas fa-ban fa-4x text-danger mb-4"></i>
                        <h1 class="h3 text-danger">Accès Refusé</h1>
                        <p class="text-muted mb-4">Vous n\'avez pas les permissions nécessaires pour accéder à cette page.</p>
                        <a href="dashboard.php" class="btn btn-primary">Retour au Tableau de Bord</a>
                    </div>
                </div>
            </div>
            <script src="https://kit.fontawesome.com/your-kit-id.js"></script>
        </body>
        </html>
        ');
    }
}

// Helper function to check if user is admin
function isAdmin() {
    return $_SESSION['role'] === 'admin';
}

// Helper function to check if user has specific permission
function hasPermission($permission) {
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    $user_permissions = $_SESSION['permissions'] ?? [];
    return in_array($permission, $user_permissions);
}

// Helper function to get current user data
function getCurrentUser() {
    $users = loadData('users');
    foreach ($users as $user) {
        if ($user['id'] === $_SESSION['user_id']) {
            return $user;
        }
    }
    return null;
}

// Security: Regenerate session ID periodically
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function generateCSRFToken() {
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

// XSS Protection helper
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Rate limiting for failed login attempts
function checkRateLimit($username) {
    $attempts_file = DATA_DIR . '/login_attempts.json';
    $attempts = [];
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true) ?: [];
    }
    
    $now = time();
    $user_attempts = $attempts[$username] ?? [];
    
    // Clean old attempts (older than lockout time)
    $user_attempts = array_filter($user_attempts, function($attempt_time) use ($now) {
        return ($now - $attempt_time) < LOCKOUT_TIME;
    });
    
    // Check if user is locked out
    if (count($user_attempts) >= MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    
    return true;
}

function recordFailedLogin($username) {
    $attempts_file = DATA_DIR . '/login_attempts.json';
    $attempts = [];
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true) ?: [];
    }
    
    if (!isset($attempts[$username])) {
        $attempts[$username] = [];
    }
    
    $attempts[$username][] = time();
    
    file_put_contents($attempts_file, json_encode($attempts));
}

function clearFailedLogins($username) {
    $attempts_file = DATA_DIR . '/login_attempts.json';
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true) ?: [];
        unset($attempts[$username]);
        file_put_contents($attempts_file, json_encode($attempts));
    }
}

// Input validation helpers
function validateInput($input, $type = 'string', $options = []) {
    switch ($type) {
        case 'string':
            $input = trim($input);
            $max_length = $options['max_length'] ?? 255;
            return strlen($input) <= $max_length ? $input : false;
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
            
        case 'phone':
            return preg_match('/^[\+]?[0-9\s\-\(\)\.]{10,20}$/', $input) ? $input : false;
            
        case 'amount':
            $amount = floatval($input);
            $min = $options['min'] ?? 0;
            $max = $options['max'] ?? 999999;
            return ($amount >= $min && $amount <= $max) ? $amount : false;
            
        case 'date':
            $date = DateTime::createFromFormat('Y-m-d', $input);
            return $date && $date->format('Y-m-d') === $input ? $input : false;
            
        case 'datetime':
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $input);
            return $date && $date->format('Y-m-d H:i:s') === $input ? $input : false;
            
        default:
            return $input;
    }
}

// Log security events
function logSecurityEvent($event, $details = []) {
    $log_file = DATA_DIR . '/security.log';
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $_SESSION['user_id'] ?? 'anonymous',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}
?>
