<?php
session_start();
require_once 'includes/functions.php';

// Load data
$users = loadData('users');
$crew = loadData('crew');
$work = loadData('work');

// Find Ines in users
$inesUser = null;
foreach ($users as $user) {
    if ($user['username'] === 'Ines') {
        $inesUser = $user;
        break;
    }
}

// Find Ines in crew
$inesCrew = null;
foreach ($crew as $member) {
    if ($member['username'] === 'Ines') {
        $inesCrew = $member;
        break;
    }
}

echo "<h2>Ines Debug Information</h2>";
echo "<h3>User Data:</h3>";
echo "<pre>" . json_encode($inesUser, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Crew Data:</h3>";
echo "<pre>" . json_encode($inesCrew, JSON_PRETTY_PRINT) . "</pre>";

// Check work records with Ines crew_id
$inesCrewId = $inesCrew['id'] ?? null;
if ($inesCrewId) {
    $inesWork = array_filter($work, function($w) use ($inesCrewId) {
        return isset($w['crew_id']) && $w['crew_id'] === $inesCrewId;
    });
    
    echo "<h3>Filtered Work Records for Ines (crew_id: $inesCrewId):</h3>";
    echo "<p>Found " . count($inesWork) . " work records out of " . count($work) . " total</p>";
    echo "<pre>" . json_encode(array_values($inesWork), JSON_PRETTY_PRINT) . "</pre>";
    
    // Check if any work records have different crew_id format
    echo "<h3>All unique crew_id values in work data:</h3>";
    $crewIds = array_unique(array_column($work, 'crew_id'));
    foreach ($crewIds as $id) {
        $count = count(array_filter($work, function($w) use ($id) {
            return $w['crew_id'] === $id;
        }));
        echo "$id => $count records<br>";
    }
}
?>