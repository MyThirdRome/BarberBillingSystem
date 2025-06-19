<?php
require_once 'includes/functions.php';

// Load users
$users = loadData('users');

// Update admin password back to Admin123, keep others as kingsm
foreach ($users as &$user) {
    if ($user['username'] === 'admin') {
        $user['password'] = password_hash('Admin123', PASSWORD_DEFAULT);
        echo "Updated admin password to 'Admin123'\n";
    } else {
        $user['password'] = password_hash('kingsm', PASSWORD_DEFAULT);
        echo "Updated password for " . $user['username'] . " to 'kingsm'\n";
    }
    $user['password_changed_at'] = date('Y-m-d H:i:s');
}

// Save updated users
saveData('users', $users);
echo "\nPassword update complete:\n";
echo "- admin: Admin123\n";
echo "- all others: kingsm\n";
?>