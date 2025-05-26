<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkPermission('edit');

// Load all data
$advances = loadData('advances');
$payments = loadData('payments');
$charges = loadData('charges');
$crew = loadData('crew');

$syncedCount = 0;

echo "<h3>Synchronisation des charges...</h3>";

// Sync advances to charges
foreach ($advances as $advance) {
    // Check if this advance already has a charge entry
    $existingCharge = array_filter($charges, function($charge) use ($advance) {
        return isset($charge['advance_id']) && $charge['advance_id'] === $advance['id'];
    });
    
    if (empty($existingCharge)) {
        // Find crew member name
        $crewMember = array_filter($crew, function($c) use ($advance) {
            return $c['id'] === $advance['crew_id'];
        });
        $crewMember = reset($crewMember);
        
        if ($crewMember) {
            $newCharge = [
                'id' => generateId(),
                'type' => 'Avance - ' . htmlspecialchars($crewMember['name']),
                'amount' => $advance['amount'],
                'date' => $advance['date'],
                'description' => 'Avance: ' . ($advance['reason'] ?? 'Pas de raison spécifiée'),
                'category' => 'Salaires et Avances',
                'created_at' => date('Y-m-d H:i:s'),
                'crew_id' => $advance['crew_id'],
                'advance_id' => $advance['id']
            ];
            $charges[] = $newCharge;
            $syncedCount++;
            echo "✓ Avance synchronisée: " . htmlspecialchars($crewMember['name']) . " - " . number_format($advance['amount'], 3) . " TND<br>";
        }
    }
}

// Sync payments to charges
foreach ($payments as $payment) {
    // Check if this payment already has a charge entry
    $existingCharge = array_filter($charges, function($charge) use ($payment) {
        return isset($charge['payment_id']) && $charge['payment_id'] === $payment['id'];
    });
    
    if (empty($existingCharge)) {
        // Find crew member name
        $crewMember = array_filter($crew, function($c) use ($payment) {
            return $c['id'] === $payment['crew_id'];
        });
        $crewMember = reset($crewMember);
        
        if ($crewMember) {
            // Use period_start for the charge date (salary period month)
            $chargeDate = $payment['period_start'] ?? $payment['payment_date'] ?? date('Y-m-d');
            
            // Create detailed description
            $description = 'Salaire pour ' . date('F Y', strtotime($chargeDate));
            if (isset($payment['base_salary'])) {
                $description .= ' (Base: ' . number_format($payment['base_salary'], 3) . ' TND';
                if (isset($payment['advances_deducted'])) {
                    $description .= ', Avances: ' . number_format($payment['advances_deducted'], 3) . ' TND';
                }
                if (isset($payment['bonus_amount'])) {
                    $description .= ', Bonus: ' . number_format($payment['bonus_amount'], 3) . ' TND';
                }
                $description .= ')';
            }
            
            $newCharge = [
                'id' => generateId(),
                'type' => 'Salaire - ' . htmlspecialchars($crewMember['name']),
                'amount' => $payment['net_payment'],
                'date' => $chargeDate,
                'description' => $description,
                'category' => 'Salaires et Avances',
                'created_at' => date('Y-m-d H:i:s'),
                'crew_id' => $payment['crew_id'],
                'payment_id' => $payment['id'],
                'salary_month' => isset($payment['period_start']) ? date('Y-m', strtotime($payment['period_start'])) : null
            ];
            $charges[] = $newCharge;
            $syncedCount++;
            echo "✓ Salaire synchronisé: " . htmlspecialchars($crewMember['name']) . " - " . number_format($payment['net_payment'], 3) . " TND<br>";
        }
    }
}

// Save updated charges
saveData('charges', $charges);

echo "<br><strong>Synchronisation terminée!</strong><br>";
echo "Total des entrées synchronisées: $syncedCount<br>";
echo "<br><a href='charges.php' class='btn btn-primary'>Voir les charges</a>";
echo " <a href='dashboard.php' class='btn btn-secondary'>Retour au tableau de bord</a>";
?>