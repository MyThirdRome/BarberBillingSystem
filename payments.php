<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkPermission('edit');

$payments = loadData('payments');
$crew = loadData('crew');
$advances = loadData('advances');
$work = loadData('work');
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $crew_id = $_POST['crew_id'] ?? '';
        $period_start = $_POST['period_start'] ?? '';
        $period_end = $_POST['period_end'] ?? '';
        $bonus_percentage = floatval($_POST['bonus_percentage'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($crew_id) || empty($period_start) || empty($period_end)) {
            $error = 'Veuillez sélectionner un membre d\'équipe et définir la période.';
        } else {
            // Calculate base salary and work revenue
            $crewMember = array_filter($crew, function($c) use ($crew_id) {
                return $c['id'] === $crew_id;
            });
            $crewMember = reset($crewMember);
            $baseSalary = $crewMember['salary_base'] ?? 0;
            
            // Calculate work revenue for the period
            $periodWork = array_filter($work, function($w) use ($crew_id, $period_start, $period_end) {
                $workDate = substr($w['date'], 0, 10);
                return $w['crew_id'] === $crew_id && 
                       $workDate >= $period_start && 
                       $workDate <= $period_end;
            });
            $workRevenue = array_sum(array_column($periodWork, 'amount'));
            
            // Calculate pending advances for the period
            $pendingAdvances = array_filter($advances, function($a) use ($crew_id, $period_start, $period_end) {
                $advanceDate = substr($a['date'], 0, 10);
                return $a['crew_id'] === $crew_id && 
                       $a['status'] === 'pending' &&
                       $advanceDate >= $period_start && 
                       $advanceDate <= $period_end;
            });
            $totalAdvances = array_sum(array_column($pendingAdvances, 'amount'));
            
            // Calculate payment using new logic
            // Step 1: Calculate remaining base salary (Base - Advances)
            $remainingSalary = $baseSalary - $totalAdvances;
            
            // Step 2: Calculate bonus on revenue above 2000 TND
            $bonusThreshold = 2000;
            $eligibleForBonus = max(0, $workRevenue - $bonusThreshold);
            $bonusAmount = ($eligibleForBonus * $bonus_percentage) / 100;
            
            // Step 3: Calculate final payment
            $netPayment = $remainingSalary + $bonusAmount;
            
            $newPayment = [
                'id' => generateId(),
                'crew_id' => $crew_id,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'base_salary' => $baseSalary,
                'work_revenue' => $workRevenue,
                'bonus_percentage' => $bonus_percentage,
                'bonus_threshold' => $bonusThreshold,
                'eligible_bonus' => $eligibleForBonus,
                'bonus_amount' => $bonusAmount,
                'remaining_salary' => $remainingSalary,
                'advances_deducted' => $totalAdvances,
                'net_payment' => $netPayment,
                'notes' => $notes,
                'payment_date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $payments[] = $newPayment;
            saveData('payments', $payments);
            
            // Mark advances as deducted (only those in the period)
            foreach ($advances as &$advance) {
                $advanceDate = substr($advance['date'], 0, 10);
                if ($advance['crew_id'] === $crew_id && 
                    $advance['status'] === 'pending' &&
                    $advanceDate >= $period_start && 
                    $advanceDate <= $period_end) {
                    $advance['status'] = 'deducted';
                    $advance['deducted_in_payment'] = $newPayment['id'];
                }
            }
            saveData('advances', $advances);
            
            $message = 'Paiement ajouté avec succès.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        // Find the payment to revert advances
        $paymentToDelete = array_filter($payments, function($p) use ($id) {
            return $p['id'] === $id;
        });
        $paymentToDelete = reset($paymentToDelete);
        
        if ($paymentToDelete) {
            // Revert advances to pending status
            foreach ($advances as &$advance) {
                if (isset($advance['deducted_in_payment']) && $advance['deducted_in_payment'] === $id) {
                    $advance['status'] = 'pending';
                    unset($advance['deducted_in_payment']);
                }
            }
            saveData('advances', $advances);
        }
        
        $payments = array_filter($payments, function($payment) use ($id) {
            return $payment['id'] !== $id;
        });
        
        saveData('payments', $payments);
        $message = 'Paiement supprimé avec succès.';
    }
}

// Filter payments
$crew_filter = $_GET['crew_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$filteredPayments = $payments;

if ($crew_filter) {
    $filteredPayments = array_filter($filteredPayments, function($p) use ($crew_filter) {
        return $p['crew_id'] === $crew_filter;
    });
}

if ($date_from) {
    $filteredPayments = array_filter($filteredPayments, function($p) use ($date_from) {
        return $p['payment_date'] >= $date_from;
    });
}

if ($date_to) {
    $filteredPayments = array_filter($filteredPayments, function($p) use ($date_to) {
        return $p['payment_date'] <= $date_to;
    });
}

// Sort by payment date (newest first)
usort($filteredPayments, function($a, $b) {
    return strtotime($b['payment_date']) - strtotime($a['payment_date']);
});

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Gestion des Paiements</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                    <i class="fas fa-plus"></i> Nouveau Paiement
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Previous Month Statistics -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Statistiques du Mois Précédent (<?= date('F Y', strtotime('last month')) ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Membre d'Équipe</th>
                                    <th>Salaire de Base</th>
                                    <th>Travaux Effectués</th>
                                    <th>Revenus Générés</th>
                                    <th>Avances Prises</th>
                                    <th>À Payer (Base - Avances)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $lastMonthStart = date('Y-m-01', strtotime('last month'));
                                $lastMonthEnd = date('Y-m-t', strtotime('last month'));
                                
                                foreach ($crew as $member):
                                    // Calculate work for last month
                                    $memberWork = array_filter($work, function($w) use ($member, $lastMonthStart, $lastMonthEnd) {
                                        return $w['crew_id'] === $member['id'] && 
                                               substr($w['date'], 0, 10) >= $lastMonthStart && 
                                               substr($w['date'], 0, 10) <= $lastMonthEnd;
                                    });
                                    $workCount = count($memberWork);
                                    $workRevenue = array_sum(array_column($memberWork, 'amount'));
                                    
                                    // Calculate advances for last month
                                    $memberAdvances = array_filter($advances, function($a) use ($member, $lastMonthStart, $lastMonthEnd) {
                                        return $a['crew_id'] === $member['id'] && 
                                               $a['status'] === 'pending' &&
                                               substr($a['date'], 0, 10) >= $lastMonthStart && 
                                               substr($a['date'], 0, 10) <= $lastMonthEnd;
                                    });
                                    $totalAdvances = array_sum(array_column($memberAdvances, 'amount'));
                                    
                                    $toPay = $member['salary_base'] - $totalAdvances;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($member['name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($member['position']) ?></small>
                                    </td>
                                    <td class="fw-bold"><?= number_format($member['salary_base'], 3) ?> TND</td>
                                    <td>
                                        <span class="badge bg-primary"><?= $workCount ?> travaux</span>
                                    </td>
                                    <td class="text-success fw-bold"><?= number_format($workRevenue, 3) ?> TND</td>
                                    <td class="text-warning">
                                        <?= $totalAdvances > 0 ? number_format($totalAdvances, 3) . ' TND' : 'Aucune' ?>
                                    </td>
                                    <td class="<?= $toPay >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                                        <?= number_format($toPay, 3) ?> TND
                                    </td>
                                    <td>
                                        <?php if ($workCount > 0 || $totalAdvances > 0): ?>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="createPaymentForMember('<?= $member['id'] ?>', '<?= htmlspecialchars($member['name']) ?>')">
                                                <i class="fas fa-money-bill"></i> Payer
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">Aucune activité</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="crew_filter" class="form-label">Équipe</label>
                            <select class="form-control" id="crew_filter" name="crew_filter">
                                <option value="">Toutes les équipes</option>
                                <?php foreach ($crew as $member): ?>
                                    <option value="<?= $member['id'] ?>" <?= $crew_filter === $member['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($member['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Date de</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date à</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-outline-primary">Filtrer</button>
                                <a href="payments.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <?php if (empty($filteredPayments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-money-check-alt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucun paiement trouvé</h5>
                            <p class="text-muted">Commencez par traiter des paiements ou ajustez vos filtres.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Équipe</th>
                                        <th>Période</th>
                                        <th>Salaire Base</th>
                                        <th>Revenus Travaux</th>
                                        <th>Bonus</th>
                                        <th>Avances</th>
                                        <th>Net à Payer</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredPayments as $payment): 
                                        $crewMember = array_filter($crew, function($c) use ($payment) {
                                            return $c['id'] === $payment['crew_id'];
                                        });
                                        $crewMember = reset($crewMember);
                                    ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                                            <td><?= $crewMember ? htmlspecialchars($crewMember['name']) : 'N/A' ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($payment['period_start'])) ?> - 
                                                <?= date('d/m/Y', strtotime($payment['period_end'])) ?>
                                            </td>
                                            <td><?= number_format($payment['base_salary'], 2) ?> TND</td>
                                            <td><?= number_format($payment['work_revenue'], 2) ?> TND</td>
                                            <td>
                                                <?= number_format($payment['bonus_amount'], 2) ?> TND 
                                                (<?= $payment['bonus_percentage'] ?>%)
                                            </td>
                                            <td class="text-danger">-<?= number_format($payment['advances_deducted'], 2) ?> TND</td>
                                            <td class="fw-bold text-success"><?= number_format($payment['net_payment'], 2) ?> TND</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="viewPaymentDetails('<?= $payment['id'] ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deletePayment('<?= $payment['id'] ?>', '<?= number_format($payment['net_payment'], 2) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>Total des paiements: <?= count($filteredPayments) ?></h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6>Montant total net: <?= number_format(array_sum(array_column($filteredPayments, 'net_payment')), 2) ?> TND</h6>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="crew_id" class="form-label">Membre d'Équipe *</label>
                                <select class="form-control" id="crew_id" name="crew_id" required onchange="updatePaymentPreview()">
                                    <option value="">Sélectionner un membre</option>
                                    <?php foreach ($crew as $member): ?>
                                        <option value="<?= $member['id'] ?>" 
                                                data-salary="<?= $member['salary_base'] ?>"
                                                data-name="<?= htmlspecialchars($member['name']) ?>">
                                            <?= htmlspecialchars($member['name']) ?> (Salaire: <?= number_format($member['salary_base'], 3) ?> TND)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bonus_percentage" class="form-label">Pourcentage Bonus (%)</label>
                                <input type="number" class="form-control" id="bonus_percentage" name="bonus_percentage" 
                                       step="0.1" min="0" max="100" value="0" onchange="updatePaymentPreview()">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_start" class="form-label">Début de Période *</label>
                                <input type="date" class="form-control" id="period_start" name="period_start" 
                                       value="<?= date('Y-m-01', strtotime('last month')) ?>"
                                       required onchange="updatePaymentPreview()">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_end" class="form-label">Fin de Période *</label>
                                <input type="date" class="form-control" id="period_end" name="period_end" 
                                       value="<?= date('Y-m-t', strtotime('last month')) ?>"
                                       required onchange="updatePaymentPreview()">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Calculation Preview - Embedded in Form -->
                    <div id="payment-preview" style="display: none;">
                        <hr>
                        <div class="bg-light p-3 rounded mb-3">
                            <h6 class="text-primary mb-3"><i class="fas fa-calculator"></i> Calcul du Paiement</h6>
                            
                            <!-- Step 1: Base Salary -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <strong class="text-primary">1. Salaire de Base</strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Salaire de Base:</small><br>
                                    <span id="base-salary" class="fw-bold text-success">0.000 TND</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Avances Prises:</small><br>
                                    <span id="total-advances" class="fw-bold text-warning">-0.000 TND</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Reste du Salaire:</small><br>
                                    <span id="remaining-salary" class="fw-bold text-info">0.000 TND</span>
                                </div>
                            </div>

                            <!-- Step 2: Bonus Calculation -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <strong class="text-success">2. Calcul du Bonus</strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Revenus Générés:</small><br>
                                    <span id="prev-month-work" class="fw-bold">0.000 TND</span>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Seuil Bonus:</small><br>
                                    <span class="text-muted">2000.000 TND</span>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Éligible Bonus:</small><br>
                                    <span id="eligible-bonus" class="fw-bold text-primary">0.000 TND</span>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Bonus:</small><br>
                                    <span id="bonus-amount" class="fw-bold text-success">+0.000 TND</span>
                                </div>
                            </div>

                            <!-- Step 3: Final Payment -->
                            <div class="row align-items-center p-2" style="background-color: #fff3cd; border-radius: 5px;">
                                <div class="col-md-8">
                                    <strong class="text-dark">Calcul Final:</strong><br>
                                    <span id="formula-remaining" class="fw-bold">0.000</span> TND 
                                    <small class="text-muted">(Salaire - Avances)</small> 
                                    + <span id="formula-bonus" class="fw-bold">0.000</span> TND 
                                    <small class="text-muted">(Bonus)</small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <strong class="text-success h5">
                                        = <span id="total-payment">0.000</span> TND
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label">Montant à Payer (TND) *</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.001" min="0" required readonly>
                        <small class="text-muted">Ce montant est calculé automatiquement</small>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <!-- Payment Preview -->
                    <div id="payment_preview" class="card bg-light" style="display: none;">
                        <div class="card-header">
                            <h6 class="mb-0">Aperçu du Paiement</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Salaire de Base:</strong> <span id="preview_base_salary">0.00</span> TND</p>
                                    <p><strong>Revenus Travaux:</strong> <span id="preview_work_revenue">0.00</span> TND</p>
                                    <p><strong>Bonus:</strong> <span id="preview_bonus">0.00</span> TND</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Total Brut:</strong> <span id="preview_gross">0.00</span> TND</p>
                                    <p class="text-danger"><strong>Avances:</strong> -<span id="preview_advances">0.00</span> TND</p>
                                    <p class="text-success"><strong>Net à Payer:</strong> <span id="preview_net">0.00</span> TND</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Traiter le Paiement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payment_details_content">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Payment Modal -->
<div class="modal fade" id="deletePaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la Suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer ce paiement de <strong id="delete_payment_amount"></strong> TND ?</p>
                <p class="text-warning">Les avances déduites seront remises en statut "en attente".</p>
                <p class="text-danger">Cette action est irréversible.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_payment_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store data for JavaScript access
const paymentData = <?= json_encode($payments) ?>;
const crewData = <?= json_encode($crew) ?>;
const workData = <?= json_encode($work) ?>;
const advanceData = <?= json_encode($advances) ?>;

function updatePaymentPreview() {
    const crewId = document.getElementById('crew_id').value;
    const periodStart = document.getElementById('period_start').value;
    const periodEnd = document.getElementById('period_end').value;
    const bonusPercentage = parseFloat(document.getElementById('bonus_percentage').value) || 0;
    const previewDiv = document.getElementById('payment-preview');
    
    if (!crewId || !periodStart || !periodEnd) {
        previewDiv.style.display = 'none';
        return;
    }
    
    // Get crew member data
    const crewMember = crewData.find(c => c.id === crewId);
    const baseSalary = parseFloat(crewMember?.salary_base || 0);
    
    // Calculate work revenue for the period
    const periodWork = workData.filter(w => 
        w.crew_id === crewId && 
        w.date.substring(0, 10) >= periodStart && 
        w.date.substring(0, 10) <= periodEnd
    );
    const workRevenue = periodWork.reduce((sum, w) => sum + parseFloat(w.amount), 0);
    
    // Calculate pending advances for the period
    const periodAdvances = advanceData.filter(a => 
        a.crew_id === crewId && 
        a.status === 'pending' &&
        a.date.substring(0, 10) >= periodStart && 
        a.date.substring(0, 10) <= periodEnd
    );
    const totalAdvances = periodAdvances.reduce((sum, a) => sum + parseFloat(a.amount), 0);
    
    // Step 1: Calculate remaining base salary (Base - Advances)
    const remainingSalary = baseSalary - totalAdvances;
    
    // Step 2: Calculate bonus on revenue above 2000 TND
    const bonusThreshold = 2000;
    const eligibleForBonus = Math.max(0, workRevenue - bonusThreshold);
    const bonusAmount = (eligibleForBonus * bonusPercentage) / 100;
    
    // Step 3: Calculate final payment
    const totalPayment = remainingSalary + bonusAmount;
    
    // Update all display elements
    document.getElementById('base-salary').textContent = baseSalary.toFixed(3);
    document.getElementById('total-advances').textContent = totalAdvances.toFixed(3);
    document.getElementById('remaining-salary').textContent = remainingSalary.toFixed(3);
    document.getElementById('prev-month-work').textContent = workRevenue.toFixed(3);
    document.getElementById('eligible-bonus').textContent = eligibleForBonus.toFixed(3);
    document.getElementById('bonus-amount').textContent = bonusAmount.toFixed(3);
    document.getElementById('total-payment').textContent = totalPayment.toFixed(3);
    
    // Update formula display
    document.getElementById('formula-remaining').textContent = remainingSalary.toFixed(3);
    document.getElementById('formula-bonus').textContent = bonusAmount.toFixed(3);
    
    // Auto-fill the amount field
    document.getElementById('amount').value = totalPayment.toFixed(3);
    
    previewDiv.style.display = 'block';
}

function createPaymentForMember(crewId, crewName) {
    // Set the crew member
    document.getElementById('crew_id').value = crewId;
    
    // Trigger the preview update
    updatePaymentPreview();
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('addPaymentModal'));
    modal.show();
    
    // Show notification
    showNotification(`Formulaire de paiement préparé pour ${crewName}`, 'info');
}

function viewPaymentDetails(id) {
    const payment = paymentData.find(p => p.id === id);
    const crewMember = crewData.find(c => c.id === payment.crew_id);
    
    if (payment) {
        const content = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Informations Générales</h6>
                    <p><strong>Équipe:</strong> ${crewMember?.name || 'N/A'}</p>
                    <p><strong>Période:</strong> ${new Date(payment.period_start).toLocaleDateString()} - ${new Date(payment.period_end).toLocaleDateString()}</p>
                    <p><strong>Date de Paiement:</strong> ${new Date(payment.payment_date).toLocaleDateString()}</p>
                </div>
                <div class="col-md-6">
                    <h6>Détails Financiers</h6>
                    <p><strong>Salaire de Base:</strong> ${parseFloat(payment.base_salary).toFixed(2)} TND</p>
                    <p><strong>Revenus Travaux:</strong> ${parseFloat(payment.work_revenue).toFixed(2)} TND</p>
                    <p><strong>Bonus (${payment.bonus_percentage}%):</strong> ${parseFloat(payment.bonus_amount).toFixed(2)} TND</p>
                    <p><strong>Total Brut:</strong> ${parseFloat(payment.gross_payment).toFixed(2)} TND</p>
                    <p class="text-danger"><strong>Avances Déduites:</strong> -${parseFloat(payment.advances_deducted).toFixed(2)} TND</p>
                    <p class="text-success"><strong>Net Payé:</strong> ${parseFloat(payment.net_payment).toFixed(2)} TND</p>
                </div>
            </div>
            ${payment.notes ? `<div class="mt-3"><h6>Notes</h6><p>${payment.notes}</p></div>` : ''}
        `;
        
        document.getElementById('payment_details_content').innerHTML = content;
        new bootstrap.Modal(document.getElementById('paymentDetailsModal')).show();
    }
}

function deletePayment(id, amount) {
    document.getElementById('delete_payment_id').value = id;
    document.getElementById('delete_payment_amount').textContent = amount;
    
    new bootstrap.Modal(document.getElementById('deletePaymentModal')).show();
}

// Set default period to current month
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    
    document.getElementById('period_start').value = firstDay.toISOString().split('T')[0];
    document.getElementById('period_end').value = lastDay.toISOString().split('T')[0];
});
</script>

<?php include 'includes/footer.php'; ?>
