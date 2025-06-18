<?php
require_once 'config.php';

/**
 * Load data from JSON file
 */
function loadData($type) {
    $file = DATA_DIR . '/' . $type . '.json';
    
    if (!file_exists($file)) {
        return [];
    }
    
    $data = file_get_contents($file);
    $decoded = json_decode($data, true);
    
    return $decoded ?: [];
}

/**
 * Save data to JSON file
 */
function saveData($type, $data) {
    $file = DATA_DIR . '/' . $type . '.json';
    
    // Create backup if enabled
    if (BACKUP_ENABLED && file_exists($file)) {
        createBackup($type);
    }
    
    $json_flags = JSON_UNESCAPED_UNICODE;
    if (JSON_PRETTY_PRINT) {
        $json_flags |= JSON_PRETTY_PRINT;
    }
    
    $json = json_encode($data, $json_flags);
    
    if ($json === false) {
        error_log('Failed to encode JSON for ' . $type . ': ' . json_last_error_msg());
        return false;
    }
    
    $result = file_put_contents($file, $json, LOCK_EX);
    
    if ($result === false) {
        error_log('Failed to write file: ' . $file);
        return false;
    }
    
    return true;
}

/**
 * Create backup of data file
 */
function createBackup($type) {
    $file = DATA_DIR . '/' . $type . '.json';
    $backup_dir = DATA_DIR . '/backups';
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $backup_file = $backup_dir . '/' . $type . '_' . date('Y-m-d_H-i-s') . '.json';
    copy($file, $backup_file);
    
    // Clean old backups
    cleanOldBackups($type);
}

/**
 * Clean old backup files
 */
function cleanOldBackups($type) {
    $backup_dir = DATA_DIR . '/backups';
    $pattern = $backup_dir . '/' . $type . '_*.json';
    
    $files = glob($pattern);
    if (count($files) > MAX_BACKUPS) {
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove oldest files
        $to_remove = count($files) - MAX_BACKUPS;
        for ($i = 0; $i < $to_remove; $i++) {
            unlink($files[$i]);
        }
    }
}

/**
 * Generate unique ID
 */
function generateId() {
    return uniqid('', true);
}

/**
 * Format currency amount
 */
function formatCurrency($amount, $currency = 'TND') {
    return number_format($amount, DECIMAL_PLACES, '.', ' ') . ' ' . $currency;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    return formatDate($datetime, $format);
}

/**
 * Get charge type badge color
 */
function getChargeTypeBadgeColor($type) {
    global $chargeTypes;
    return $chargeTypes[$type]['color'] ?? 'secondary';
}

/**
 * Calculate work statistics for a crew member
 */
function getCrewWorkStats($crew_id, $start_date = null, $end_date = null) {
    $work = loadData('work');
    
    $filtered_work = array_filter($work, function($w) use ($crew_id, $start_date, $end_date) {
        if ($w['crew_id'] !== $crew_id) return false;
        
        $work_date = substr($w['date'], 0, 10);
        
        if ($start_date && $work_date < $start_date) return false;
        if ($end_date && $work_date > $end_date) return false;
        
        return true;
    });
    
    return [
        'total_work' => count($filtered_work),
        'total_revenue' => array_sum(array_column($filtered_work, 'amount')),
        'average_per_work' => count($filtered_work) > 0 ? array_sum(array_column($filtered_work, 'amount')) / count($filtered_work) : 0
    ];
}

/**
 * Calculate crew pending advances
 */
function getCrewPendingAdvances($crew_id) {
    $advances = loadData('advances');
    
    $pending_advances = array_filter($advances, function($a) use ($crew_id) {
        return $a['crew_id'] === $crew_id && $a['status'] === 'pending';
    });
    
    return array_sum(array_column($pending_advances, 'amount'));
}

/**
 * Get monthly statistics
 */
function getMonthlyStats($year, $month = null) {
    $work = loadData('work');
    $charges = loadData('charges');
    
    $date_filter = $year . '-' . ($month ? sprintf('%02d', $month) : '');
    
    $filtered_work = array_filter($work, function($w) use ($date_filter) {
        return strpos($w['date'], $date_filter) === 0;
    });
    
    $filtered_charges = array_filter($charges, function($c) use ($date_filter) {
        return strpos($c['date'], $date_filter) === 0;
    });
    
    return [
        'revenue' => array_sum(array_column($filtered_work, 'amount')),
        'charges' => array_sum(array_column($filtered_charges, 'amount')),
        'work_count' => count($filtered_work),
        'charge_count' => count($filtered_charges)
    ];
}

/**
 * Search and filter function
 */
function searchData($data, $search_term, $fields = []) {
    if (empty($search_term)) return $data;
    
    return array_filter($data, function($item) use ($search_term, $fields) {
        foreach ($fields as $field) {
            if (isset($item[$field]) && stripos($item[$field], $search_term) !== false) {
                return true;
            }
        }
        return false;
    });
}

/**
 * Paginate data
 */
function paginateData($data, $page = 1, $per_page = 20) {
    $total = count($data);
    $offset = ($page - 1) * $per_page;
    
    return [
        'data' => array_slice($data, $offset, $per_page),
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}

/**
 * Sanitize filename
 */
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
}

/**
 * Export data to CSV
 */
function exportToCSV($data, $filename, $headers = []) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitizeFilename($filename) . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($headers)) {
        fputcsv($output, $headers, ';');
    }
    
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
}

/**
 * Log application events
 */
function logEvent($type, $message, $data = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'message' => $message,
        'user_id' => $_SESSION['user_id'] ?? null,
        'data' => $data
    ];
    
    $log_file = DATA_DIR . '/application.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Initialize default admin user if no users exist
 */
function initializeDefaultUser() {
    $users = loadData('users');
    
    if (empty($users)) {
        $default_admin = [
            'id' => generateId(),
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'permissions' => ['view', 'edit', 'admin'],
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'system'
        ];
        
        $users[] = $default_admin;
        saveData('users', $users);
        
        logEvent('system', 'Default admin user created');
        
        return true;
    }
    
    return false;
}

/**
 * Validate file structure and fix if needed
 */
function validateDataStructure() {
    $required_files = ['users', 'crew', 'work', 'charges', 'advances', 'payments'];
    
    foreach ($required_files as $file) {
        $file_path = DATA_DIR . '/' . $file . '.json';
        if (!file_exists($file_path)) {
            saveData($file, []);
        }
    }
    
    // Initialize default admin if needed
    initializeDefaultUser();
}

/**
 * Get system statistics
 */
function getSystemStats() {
    $stats = [];
    
    $stats['users'] = count(loadData('users'));
    $stats['crew'] = count(loadData('crew'));
    $stats['work'] = count(loadData('work'));
    $stats['charges'] = count(loadData('charges'));
    $stats['advances'] = count(loadData('advances'));
    $stats['payments'] = count(loadData('payments'));
    
    $work = loadData('work');
    $charges = loadData('charges');
    
    $stats['total_revenue'] = array_sum(array_column($work, 'amount'));
    $stats['total_charges'] = array_sum(array_column($charges, 'amount'));
    $stats['net_profit'] = $stats['total_revenue'] - $stats['total_charges'];
    
    return $stats;
}

/**
 * Generate random color for charts
 */
function generateRandomColor($alpha = 1) {
    return sprintf('rgba(%d, %d, %d, %s)', 
        rand(0, 255), 
        rand(0, 255), 
        rand(0, 255), 
        $alpha
    );
}

/**
 * Get crew member by ID
 */
function getCrewMember($crew_id) {
    $crew = loadData('crew');
    foreach ($crew as $member) {
        if ($member['id'] === $crew_id) {
            return $member;
        }
    }
    return null;
}

/**
 * Calculate age from birthdate
 */
function calculateAge($birthdate) {
    if (empty($birthdate)) return null;
    
    try {
        $birth = new DateTime($birthdate);
        $now = new DateTime();
        return $birth->diff($now)->y;
    } catch (Exception $e) {
        return null;
    }
}



// Run data structure validation on include
validateDataStructure();
?>
