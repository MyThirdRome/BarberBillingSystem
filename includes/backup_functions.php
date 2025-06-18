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
        
        // Send email notification with attachment
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

Le fichier de sauvegarde est joint à cet email pour votre commodité. Vous pouvez également le télécharger depuis l'interface d'administration.

Important: Conservez ce fichier en lieu sûr. Il contient toutes vos données sensibles.

Cordialement,
Système de Gestion Salon de Coiffure";
            
            sendBackupEmailWithAttachment($config['email'], $subject, $message, $backupPath, $backupName);
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
 * Send backup email with ZIP file attachment
 */
function sendBackupEmailWithAttachment($to, $subject, $message, $attachmentPath, $attachmentName) {
    $app_password_file = DATA_DIR . '/gmail_app_password.txt';
    
    if (!file_exists($app_password_file)) {
        return logEmailNotification($to, $subject, $message, 'No app password configured');
    }
    
    $app_password = trim(file_get_contents($app_password_file));
    if (empty($app_password)) {
        return logEmailNotification($to, $subject, $message, 'Empty app password');
    }
    
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $username = 'helloborislav@gmail.com';
    
    try {
        // Read and encode attachment
        $attachment_data = base64_encode(file_get_contents($attachmentPath));
        $boundary = uniqid('boundary_');
        
        // Create email with attachment
        $email_content = "From: Barbershop Management <$username>\r\n";
        $email_content .= "To: $to\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "MIME-Version: 1.0\r\n";
        $email_content .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        $email_content .= "Date: " . date('r') . "\r\n";
        $email_content .= "\r\n";
        
        // Message body
        $email_content .= "--$boundary\r\n";
        $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $email_content .= "Content-Transfer-Encoding: 7bit\r\n";
        $email_content .= "\r\n";
        $email_content .= $message . "\r\n";
        
        // Attachment
        $email_content .= "--$boundary\r\n";
        $email_content .= "Content-Type: application/zip\r\n";
        $email_content .= "Content-Transfer-Encoding: base64\r\n";
        $email_content .= "Content-Disposition: attachment; filename=\"$attachmentName\"\r\n";
        $email_content .= "\r\n";
        $email_content .= chunk_split($attachment_data) . "\r\n";
        $email_content .= "--$boundary--\r\n";
        
        // Create temporary file for cURL
        $temp_file = tempnam(sys_get_temp_dir(), 'email_');
        file_put_contents($temp_file, $email_content);
        
        // Use cURL for SMTP
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "smtp://{$smtp_host}:{$smtp_port}",
            CURLOPT_USE_SSL => CURLUSESSL_TRY,
            CURLOPT_USERNAME => $username,
            CURLOPT_PASSWORD => $app_password,
            CURLOPT_MAIL_FROM => $username,
            CURLOPT_MAIL_RCPT => [$to],
            CURLOPT_INFILE => fopen($temp_file, 'r'),
            CURLOPT_UPLOAD => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true
        ]);
        
        $result = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        // Clean up temp file
        unlink($temp_file);
        
        if ($result === false || !empty($error)) {
            throw new Exception("cURL SMTP failed: " . $error);
        }
        
        return logEmailNotification($to, $subject, $message, "Sent with attachment: $attachmentName", 'sent');
        
    } catch (Exception $e) {
        return logEmailNotification($to, $subject, $message, 'SMTP Error: ' . $e->getMessage(), 'failed');
    }
}

/**
 * Send backup email notification via Gmail SMTP
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
    
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $username = 'helloborislav@gmail.com';
    
    try {
        // Prepare email with proper headers
        $email_content = "From: Barbershop Management <$username>\r\n";
        $email_content .= "To: $to\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "MIME-Version: 1.0\r\n";
        $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $email_content .= "Date: " . date('r') . "\r\n";
        $email_content .= "\r\n";
        $email_content .= $message;
        
        // Create temporary file for cURL
        $temp_file = tempnam(sys_get_temp_dir(), 'email_');
        file_put_contents($temp_file, $email_content);
        
        // Use cURL for SMTP with proper SSL handling
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "smtp://{$smtp_host}:{$smtp_port}",
            CURLOPT_USE_SSL => CURLUSESSL_TRY,
            CURLOPT_USERNAME => $username,
            CURLOPT_PASSWORD => $app_password,
            CURLOPT_MAIL_FROM => $username,
            CURLOPT_MAIL_RCPT => [$to],
            CURLOPT_INFILE => fopen($temp_file, 'r'),
            CURLOPT_UPLOAD => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true
        ]);
        
        $result = curl_exec($curl);
        $error = curl_error($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        
        // Clean up temp file
        unlink($temp_file);
        
        if ($result === false || !empty($error)) {
            throw new Exception("cURL SMTP failed: " . $error);
        }
        
        return logEmailNotification($to, $subject, $message, null, 'sent');
        
    } catch (Exception $e) {
        return logEmailNotification($to, $subject, $message, 'SMTP Error: ' . $e->getMessage(), 'failed');
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
        
        // Remove excess files
        $filesToDelete = array_slice($files, 0, count($files) - $keepCount);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }
}

/**
 * Import backup from ZIP file
 */
function importBackupFromZip($zipPath) {
    try {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            throw new Exception("Cannot open ZIP file");
        }
        
        $imported_files = 0;
        $temp_dir = sys_get_temp_dir() . '/backup_import_' . uniqid();
        
        // Extract ZIP to temporary directory
        if (!$zip->extractTo($temp_dir)) {
            throw new Exception("Cannot extract ZIP file");
        }
        $zip->close();
        
        // List of expected data files
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
        
        // Import each file
        foreach ($dataFiles as $file) {
            $extracted_file = $temp_dir . '/data/' . $file;
            $target_file = DATA_DIR . '/' . $file;
            
            if (file_exists($extracted_file)) {
                // Validate JSON before importing
                $json_data = file_get_contents($extracted_file);
                $decoded = json_decode($json_data, true);
                
                if ($decoded !== null) {
                    // Create backup of existing file
                    if (file_exists($target_file)) {
                        copy($target_file, $target_file . '.backup.' . date('Y-m-d_H-i-s'));
                    }
                    
                    // Import new file
                    if (copy($extracted_file, $target_file)) {
                        $imported_files++;
                    }
                }
            }
        }
        
        // Clean up temporary directory
        removeDirectory($temp_dir);
        
        if ($imported_files === 0) {
            throw new Exception("No valid data files found in backup");
        }
        
        return [
            'success' => true,
            'imported_files' => $imported_files
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Recursively remove directory
 */
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
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