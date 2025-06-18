<?php
/**
 * Automatic backup trigger - to be called via cron job every hour
 * Or can be included in other pages to check for backup needs
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/backup_functions.php';

// Check if running via command line or web
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    // If accessed via web, require admin authentication
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        exit('Access denied');
    }
}

// Check and perform automatic backup if needed
$result = checkAutomaticBackup();

if ($isCLI) {
    // Command line output
    if ($result && $result['success']) {
        echo "Backup created successfully: " . $result['file'] . "\n";
        echo "Size: " . formatFileSize($result['size']) . "\n";
    } elseif ($result === false) {
        echo "No backup needed at this time.\n";
    } else {
        echo "Backup failed: " . $result['error'] . "\n";
    }
} else {
    // Web output (JSON)
    header('Content-Type: application/json');
    if ($result && $result['success']) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Backup created successfully',
            'file' => $result['file'],
            'size' => formatFileSize($result['size'])
        ]);
    } elseif ($result === false) {
        echo json_encode([
            'status' => 'info',
            'message' => 'No backup needed at this time'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Backup failed: ' . $result['error']
        ]);
    }
}
?>