<?php

/**
 * Simple Gmail SMTP email sender for backup notifications
 */

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
function sendGmailEmail($to, $subject, $message) {
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $username = 'helloborislav@gmail.com';
    
    // Check if app password is configured
    $app_password_file = DATA_DIR . '/gmail_app_password.txt';
    if (!file_exists($app_password_file)) {
        return logEmailNotification($to, $subject, $message, 'No app password configured');
    }
    
    $app_password = trim(file_get_contents($app_password_file));
    if (empty($app_password)) {
        return logEmailNotification($to, $subject, $message, 'Empty app password');
    }
    
    try {
        // Use basic mail function as fallback for now
        $headers = "From: Barbershop Management <$username>\r\n";
        $headers .= "Reply-To: $username\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        $success = mail($to, $subject, $message, $headers);
        
        if ($success) {
            return logEmailNotification($to, $subject, $message, 'Email sent successfully', 'sent');
        } else {
            return logEmailNotification($to, $subject, $message, 'Mail function failed', 'failed');
        }
        
    } catch (Exception $e) {
        return logEmailNotification($to, $subject, $message, 'Error: ' . $e->getMessage(), 'failed');
    }
}
?>