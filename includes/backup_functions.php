<?php
/**
 * Backup system functions for Barbershop Management System
 */

/**
 * Create backup of all data files
 */
function createBackupSystem($isManual = false) {
    try {
        $backupDir = DATA_DIR . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = 'barbershop_backup_' . $timestamp . '.zip';
        $backupPath = $backupDir . '/' . $backupName;
        
        // Create ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Cannot create ZIP file');
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
                $zip->addFile($filePath, $file);
            }
        }
        
        // Add backup info file
        $backupInfo = [
            'created_at' => date('Y-m-d H:i:s'),
            'type' => $isManual ? 'manual' : 'automatic',
            'created_by' => $_SESSION['username'] ?? 'system',
            'files_count' => count($dataFiles),
            'version' => '1.0'
        ];
        
        $zip->addFromString('backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));
        $zip->close();
        
        // Load backup config
        $backupConfig = json_decode(file_get_contents(DATA_DIR . '/backup_config.json'), true);
        
        // Send email notification (simplified version)
        if ($backupConfig['enabled'] || $isManual) {
            sendBackupNotification($backupName, $backupConfig['email'], filesize($backupPath));
        }
        
        // Update backup config
        $backupConfig['last_backup'] = date('Y-m-d H:i:s');
        $backupConfig['backup_count'] = ($backupConfig['backup_count'] ?? 0) + 1;
        $backupConfig['last_backup_type'] = $isManual ? 'manual' : 'automatic';
        file_put_contents(DATA_DIR . '/backup_config.json', json_encode($backupConfig, JSON_PRETTY_PRINT));
        
        // Clean up old backup files (keep only last 5)
        cleanupOldBackupFiles($backupDir);
        
        return [
            'success' => true,
            'file' => $backupName,
            'path' => $backupPath,
            'size' => filesize($backupPath)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send backup notification via Gmail SMTP
 */
function sendBackupNotification($backupName, $recipientEmail, $fileSize) {
    try {
        $subject = 'Sauvegarde Salon de Coiffure - ' . date('d/m/Y H:i');
        
        $message = "Bonjour,

Une sauvegarde de votre système de gestion de salon de coiffure a été créée avec succès.

Détails de la sauvegarde:
- Date et heure: " . date('d/m/Y à H:i') . "
- Nom du fichier: {$backupName}
- Taille: " . formatFileSize($fileSize) . "

Cette sauvegarde contient toutes vos données importantes:
• Données des équipes
• Prestations et services
• Charges et dépenses  
• Avances et paiements
• Utilisateurs et paramètres
• Liste de prix

Le fichier de sauvegarde est stocké sur le serveur et peut être téléchargé via l'interface d'administration.

Cordialement,
Système de Gestion Salon de Coiffure";
        
        // Include email helper and send email
        require_once 'email_helper.php';
        return sendGmailEmail($recipientEmail, $subject, $message);
        
    } catch (Exception $e) {
        return false;
    }
}

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
 * Send email via Gmail SMTP with app password
 */
function sendGmailSMTPEmail($to, $subject, $message) {
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $username = 'helloborislav@gmail.com';
    
    // Check if app password is configured
    $app_password_file = DATA_DIR . '/gmail_app_password.txt';
    if (!file_exists($app_password_file)) {
        // Fall back to logging if no app password is set
        return logEmailNotification($to, $subject, $message, 'No app password configured');
    }
    
    $app_password = trim(file_get_contents($app_password_file));
    if (empty($app_password)) {
        return logEmailNotification($to, $subject, $message, 'Empty app password');
    }
    
    try {
        // Create socket connection
        $socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        if (!$socket) {
            throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
        }
        
        // Read initial response
        fgets($socket, 512);
        
        // Send EHLO
        fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        fgets($socket, 512);
        
        // Start TLS
        fwrite($socket, "STARTTLS\r\n");
        fgets($socket, 512);
        
        // Enable crypto
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        // Send EHLO again
        fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        fgets($socket, 512);
        
        // Login
        fwrite($socket, "AUTH LOGIN\r\n");
        fgets($socket, 512);
        
        fwrite($socket, base64_encode($username) . "\r\n");
        fgets($socket, 512);
        
        fwrite($socket, base64_encode($app_password) . "\r\n");
        $auth_response = fgets($socket, 512);
        
        if (strpos($auth_response, '235') === false) {
            throw new Exception("Authentication failed: " . $auth_response);
        }
        
        // Send email
        fwrite($socket, "MAIL FROM: <$username>\r\n");
        fgets($socket, 512);
        
        fwrite($socket, "RCPT TO: <$to>\r\n");
        fgets($socket, 512);
        
        fwrite($socket, "DATA\r\n");
        fgets($socket, 512);
        
        // Email content
        $email_content = "From: Barbershop Management <$username>\r\n";
        $email_content .= "To: $to\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "MIME-Version: 1.0\r\n";
        $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $email_content .= "Date: " . date('r') . "\r\n";
        $email_content .= "\r\n";
        $email_content .= $message . "\r\n";
        $email_content .= ".\r\n";
        
        fwrite($socket, $email_content);
        fgets($socket, 512);
        
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        return logEmailNotification($to, $subject, $message, 'Successfully sent via Gmail SMTP', 'sent');
        
    } catch (Exception $e) {
        return logEmailNotification($to, $subject, $message, 'SMTP Error: ' . $e->getMessage(), 'failed');
    }
}

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
 * Clean up old backup files
 */
function cleanupOldBackupFiles($backupDir, $keepCount = 5) {
    $files = glob($backupDir . '/barbershop_backup_*.zip');
    if (count($files) > $keepCount) {
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Delete oldest files
        $filesToDelete = array_slice($files, 0, count($files) - $keepCount);
        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

/**
 * Format file size to human readable format
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Check if automatic backup is due
 */
function checkAutomaticBackup() {
    $configFile = DATA_DIR . '/backup_config.json';
    if (!file_exists($configFile)) {
        return false;
    }
    
    $backupConfig = json_decode(file_get_contents($configFile), true);
    if (!$backupConfig['enabled']) {
        return false;
    }
    
    $lastBackup = $backupConfig['last_backup'] ?? null;
    
    // If no backup exists or last backup was more than 1 hour ago
    if (!$lastBackup || strtotime($lastBackup) < strtotime('-1 hour')) {
        return createBackupSystem(false);
    }
    
    return false;
}
?>