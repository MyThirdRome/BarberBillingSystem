<?php
session_start();
require_once 'includes/functions.php';

// Load data
$users = loadData('users');
$crew = loadData('crew');
$work = loadData('work');

echo "<h2>Crew Data Filtering Analysis</h2>";

// Show all users with crew_id
echo "<h3>Users with crew_id:</h3>";
foreach ($users as $user) {
    if (!empty($user['crew_id'])) {
        $workCount = count(array_filter($work, function($w) use ($user) {
            return isset($w['crew_id']) && $w['crew_id'] === $user['crew_id'];
        }));
        
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<strong>" . htmlspecialchars($user['username']) . "</strong> (" . htmlspecialchars($user['role']) . ")<br>";
        echo "Crew ID: " . htmlspecialchars($user['crew_id']) . "<br>";
        echo "Work Records: " . $workCount . "<br>";
        echo "Name: " . htmlspecialchars($user['name'] ?? 'N/A') . "<br>";
        echo "</div>";
    }
}

// Show distribution of work records by crew_id
echo "<h3>Work Records Distribution:</h3>";
$crewIds = array_unique(array_column($work, 'crew_id'));
foreach ($crewIds as $id) {
    $count = count(array_filter($work, function($w) use ($id) {
        return $w['crew_id'] === $id;
    }));
    
    // Find username for this crew_id
    $username = 'Unknown';
    foreach ($users as $user) {
        if (isset($user['crew_id']) && $user['crew_id'] === $id) {
            $username = $user['username'];
            break;
        }
    }
    
    echo "<div style='border: 1px solid #ddd; margin: 5px; padding: 5px;'>";
    echo "<strong>$username</strong>: $count records (crew_id: " . substr($id, 0, 20) . "...)<br>";
    echo "</div>";
}
?>