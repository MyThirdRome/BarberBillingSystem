<?php
// Application configuration
define('APP_NAME', 'Gestion Salon de Coiffure');
define('APP_VERSION', '1.0.0');

// Paths
define('DATA_DIR', __DIR__ . '/../data');
define('ASSETS_DIR', __DIR__ . '/../assets');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// File storage settings
if (!defined('JSON_PRETTY_PRINT')) {
    define('JSON_PRETTY_PRINT', true);
}
define('BACKUP_ENABLED', true);
define('MAX_BACKUPS', 10);

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Ensure data directory is writable
if (!is_writable(DATA_DIR)) {
    die('Data directory is not writable: ' . DATA_DIR);
}

// Default timezone
date_default_timezone_set('Europe/Paris');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PHP configuration for better security
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
}

// Application constants
define('DEFAULT_LANGUAGE', 'fr');
define('DEFAULT_CURRENCY', 'TND');
define('DECIMAL_PLACES', 3);

// Navigation menu structure
$navigation = [
    'dashboard' => [
        'title' => 'Tableau de Bord',
        'icon' => 'fas fa-tachometer-alt',
        'url' => 'dashboard.php',
        'permission' => 'view'
    ],
    'crew' => [
        'title' => 'Équipe',
        'icon' => 'fas fa-users',
        'url' => 'crew.php',
        'permission' => 'edit'
    ],
    'work' => [
        'title' => 'Travaux',
        'icon' => 'fas fa-cut',
        'url' => 'work.php',
        'permission' => 'edit'
    ],
    'charges' => [
        'title' => 'Charges',
        'icon' => 'fas fa-file-invoice-dollar',
        'url' => 'charges.php',
        'permission' => 'edit'
    ],

    'statistics' => [
        'title' => 'Statistiques',
        'icon' => 'fas fa-chart-bar',
        'url' => 'statistics.php',
        'permission' => 'view'
    ],
    'users' => [
        'title' => 'Utilisateurs',
        'icon' => 'fas fa-user-cog',
        'url' => 'users.php',
        'permission' => 'admin'
    ]
];

// Charge type configurations
$chargeTypes = [
    'salary' => [
        'label' => 'Salaire',
        'color' => 'primary',
        'requires_crew' => true,
        'requires_description' => false
    ],
    'rent' => [
        'label' => 'Loyer',
        'color' => 'warning',
        'requires_crew' => false,
        'requires_description' => false
    ],
    'electricity' => [
        'label' => 'Électricité',
        'color' => 'info',
        'requires_crew' => false,
        'requires_description' => false
    ],
    'water' => [
        'label' => 'Eau',
        'color' => 'success',
        'requires_crew' => false,
        'requires_description' => false
    ],
    'divers' => [
        'label' => 'Divers',
        'color' => 'secondary',
        'requires_crew' => false,
        'requires_description' => true
    ]
];

// Work type presets (can be extended)
$workTypes = [
    'Coupe Homme',
    'Coupe Femme',
    'Coupe Enfant',
    'Barbe',
    'Moustache',
    'Coloration',
    'Mèches',
    'Permanente',
    'Brushing',
    'Shampoing',
    'Soin',
    'Épilation'
];

// Payment status configurations
$paymentStatuses = [
    'pending' => [
        'label' => 'En attente',
        'color' => 'warning'
    ],
    'paid' => [
        'label' => 'Payé',
        'color' => 'success'
    ],
    'cancelled' => [
        'label' => 'Annulé',
        'color' => 'danger'
    ]
];

// User roles and their default permissions
$userRoles = [
    'admin' => [
        'label' => 'Administrateur',
        'permissions' => ['view', 'edit', 'admin'],
        'can_create_users' => true,
        'can_delete_users' => true,
        'can_modify_permissions' => true
    ],
    'user' => [
        'label' => 'Utilisateur',
        'permissions' => ['view'],
        'can_create_users' => false,
        'can_delete_users' => false,
        'can_modify_permissions' => false
    ]
];

// System settings that can be modified
$systemSettings = [
    'salon_name' => 'Salon de Coiffure',
    'address' => '',
    'phone' => '',
    'email' => '',
    'currency_symbol' => 'TND',
    'tax_rate' => 20.0,
    'default_bonus_percentage' => 5.0,
    'backup_frequency' => 'daily'
];
?>
