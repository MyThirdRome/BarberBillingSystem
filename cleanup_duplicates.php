<?php
require_once 'includes/functions.php';

// Load charges data
$charges = loadData('charges');

// Find and remove duplicate charges based on advance_id, amount, date, and crew_id
$seen = [];
$duplicates = [];
$cleaned = [];

foreach ($charges as $index => $charge) {
    // Create a unique key for comparison
    $key = '';
    if (isset($charge['advance_id'])) {
        $key = $charge['advance_id'] . '_' . $charge['amount'] . '_' . $charge['date'] . '_' . $charge['crew_id'];
    } else {
        // For non-advance charges, use different criteria
        $key = $charge['type'] . '_' . $charge['amount'] . '_' . $charge['date'] . '_' . ($charge['crew_id'] ?? '');
    }
    
    if (isset($seen[$key])) {
        // This is a duplicate
        $duplicates[] = $charge;
        echo "Duplicate found: " . $charge['type'] . " - " . $charge['amount'] . " TND on " . $charge['date'] . "\n";
    } else {
        // This is the first occurrence
        $seen[$key] = true;
        $cleaned[] = $charge;
    }
}

// Save cleaned data
if (count($duplicates) > 0) {
    saveData('charges', $cleaned);
    echo "\nRemoved " . count($duplicates) . " duplicate charges.\n";
    echo "Cleaned data saved successfully.\n";
} else {
    echo "No duplicates found.\n";
}
?>