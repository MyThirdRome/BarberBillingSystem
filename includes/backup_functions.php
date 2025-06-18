<?php

require_once 'config.php';

/**
 * Log email notification attempts
 */
function logEmailNotification($to, $subject, $message, $error = null, $status = 'logged') {
    $log_data = [
        'to' => $to,
        'subject' => $subject,
        'message' => substr($message, 0, 200) . '...',
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => $status,
        'method' => $status === 'sent' ? 'Gmail SMTP' : 'Local logging',
        'error' => $error
    ];
    
    $log_file = DATA_DIR . '/email_notifications.json';
    $logs = [];
    if (file_exists($log_file)) {
        $logs = json_decode(file_get_contents($log_file), true) ?: [];
    }
    
    // Keep only last 50 log entries
    if (count($logs) > 50) {
        $logs = array_slice($logs, -50);
    }
    
    $logs[] = $log_data;
    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
    
    return $status === 'sent';
}

/**
 * Create a backup of the barbershop data
 */
function createBackupSystem($manual = false) {
    try {
        $backupDir = 'backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "barbershop_backup_{$timestamp}.zip";
        $backupPath = $backupDir . '/' . $backupName;
        
        // Create ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE) !== TRUE) {
            throw new Exception("Cannot create backup archive");
        }
        
        // Add all JSON data files
        $dataFiles = [
            'crew.json',
            'work.json',
            'charges.json',
            'advances.json',
            'payments.json',
            'users.json',
            'price_list.json',
            'backup_config.json'
        ];
        
        foreach ($dataFiles as $file) {
            $filePath = DATA_DIR . '/' . $file;
            if (file_exists($filePath)) {
                $zip->addFile($filePath, 'data/' . $file);
            }
        }
        
        $zip->close();
        
        // Clean up old backups
        cleanupOldBackupFiles($backupDir, 5);
        
        // Send email notification
        $config = json_decode(file_get_contents(DATA_DIR . '/backup_config.json'), true);
        if ($config && !empty($config['email'])) {
            $subject = "Sauvegarde Salon de Coiffure - " . date('d/m/Y H:i');
            $message = "Bonjour,

Une nouvelle sauvegarde de votre système de gestion de salon de coiffure a été créée avec succès.

Détails de la sauvegarde:
- Nom du fichier: {$backupName}
- Date: " . date('d/m/Y à H:i:s') . "
- Type: " . ($manual ? 'Manuelle' : 'Automatique') . "
- Taille: " . formatFileSize(filesize($backupPath)) . "

Cette sauvegarde contient toutes vos données importantes:
- Équipe et membres
- Prestations de travail
- Charges et dépenses
- Avances et paiements
- Configuration du système

Cordialement,
Système de Gestion Salon de Coiffure";
            
            sendBackupEmail($config['email'], $subject, $message);
        }
        
        return [
            'success' => true,
            'file' => $backupName,
            'path' => $backupPath
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send backup email notification
 */
function sendBackupEmail($to, $subject, $message) {
    $app_password_file = DATA_DIR . '/gmail_app_password.txt';
    
    if (!file_exists($app_password_file)) {
        return logEmailNotification($to, $subject, $message, 'No app password configured');
    }
    
    $app_password = trim(file_get_contents($app_password_file));
    if (empty($app_password)) {
        return logEmailNotification($to, $subject, $message, 'Empty app password');
    }
    
    // For now, just log the email until SMTP is properly configured
    return logEmailNotification($to, $subject, $message, 'App password configured, ready for SMTP', 'ready');
}

/**
 * Clean up old backup files
 */
function cleanupOldBackupFiles($backupDir, $keepCount = 5) {
    $files = glob($backupDir . '/barbershop_backup_*.zip');
    if (count($files) > $keepCount) {
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove excess files
        $filesToDelete = array_slice($files, 0, count($files) - $keepCount);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

?>