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
        
        // Send email via Gmail SMTP
        return sendGmailSMTPEmail($recipientEmail, $subject, $message);
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send email via Gmail SMTP using proper TLS authentication
 */
function sendGmailSMTPEmail($to, $subject, $message) {
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $username = 'helloborislav@gmail.com';
    $password = 'kingsm22';
    $from_name = 'Barbershop Management System';
    
    try {
        // Create SSL context
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Connect to Gmail SMTP server
        $socket = stream_socket_client(
            "tcp://{$smtp_host}:{$smtp_port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
        }
        
        // Get initial response
        $response = fgets($socket, 512);
        
        // Send EHLO
        fwrite($socket, "EHLO localhost\r\n");
        $response = fgets($socket, 512);
        
        // Start TLS
        fwrite($socket, "STARTTLS\r\n");
        $response = fgets($socket, 512);
        
        // Enable TLS encryption
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("Failed to enable TLS encryption");
        }
        
        // Send EHLO again after TLS
        fwrite($socket, "EHLO localhost\r\n");
        $response = fgets($socket, 512);
        
        // Authenticate
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 512);
        
        fwrite($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 512);
        
        fwrite($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 512);
        
        // Check if authentication was successful
        if (strpos($response, '235') === false) {
            throw new Exception("SMTP authentication failed: $response");
        }
        
        // Send MAIL FROM
        fwrite($socket, "MAIL FROM: <$username>\r\n");
        $response = fgets($socket, 512);
        
        // Send RCPT TO
        fwrite($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 512);
        
        // Send DATA command
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 512);
        
        // Build email content
        $email_content = "From: $from_name <$username>\r\n";
        $email_content .= "To: $to\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "MIME-Version: 1.0\r\n";
        $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $email_content .= "Date: " . date('r') . "\r\n";
        $email_content .= "\r\n";
        $email_content .= $message . "\r\n";
        $email_content .= ".\r\n";
        
        // Send email content
        fwrite($socket, $email_content);
        $response = fgets($socket, 512);
        
        // Send QUIT
        fwrite($socket, "QUIT\r\n");
        $response = fgets($socket, 512);
        
        fclose($socket);
        
        // Log successful email attempt
        $log_data = [
            'to' => $to,
            'subject' => $subject,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'sent',
            'method' => 'Gmail SMTP'
        ];
        
        $log_file = DATA_DIR . '/email_notifications.json';
        $logs = [];
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true) ?: [];
        }
        $logs[] = $log_data;
        file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
        
        return true;
        
    } catch (Exception $e) {
        // Log failed email attempt
        $log_data = [
            'to' => $to,
            'subject' => $subject,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'failed',
            'error' => $e->getMessage(),
            'method' => 'Gmail SMTP'
        ];
        
        $log_file = DATA_DIR . '/email_notifications.json';
        $logs = [];
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true) ?: [];
        }
        $logs[] = $log_data;
        file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
        
        error_log("Gmail SMTP Error: " . $e->getMessage());
        return false;
    }
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