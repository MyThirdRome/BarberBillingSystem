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
            
            // Calculate pending advances
            $pendingAdvances = array_filter($advances, function($a) use ($crew_id) {
                return $a['crew_id'] === $crew_id && $a['status'] === 'pending';
            });
            $totalAdvances = array_sum(array_column($pendingAdvances, 'amount'));
            
            // Calculate payment
            $bonusAmount = ($baseSalary + $workRevenue) * ($bonus_percentage / 100);
            $grossPayment = $baseSalary + $workRevenue + $bonusAmount;
            $netPayment = $grossPayment - $totalAdvances;
            
            $newPayment = [
                'id' => generateId(),
                'crew_id' => $crew_id,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'base_salary' => $baseSalary,
                'work_revenue' => $workRevenue,
                'bonus_percentage' => $bonus_percentage,
                'bonus_amount' => $bonusAmount,
                'gross_payment' => $grossPayment,
                'advances_deducted' => $totalAdvances,
                'net_payment' => $netPayment,
                'notes' => $notes,
                'payment_date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $payments[] = $newPayment;
            saveData('payments', $payments);
            
            // Mark advances as deducted
            foreach ($advances as &$advance) {
                if ($advance['crew_id'] === $crew_id && $advance['status'] === 'pending') {
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
                                            <td><?= number_format($payment['base_salary'], 2) ?> €</td>
                                            <td><?= number_format($payment['work_revenue'], 2) ?> €</td>
                                            <td>
                                                <?= number_format($payment['bonus_amount'], 2) ?> € 
                                                (<?= $payment['bonus_percentage'] ?>%)
                                            </td>
                                            <td class="text-danger">-<?= number_format($payment['advances_deducted'], 2) ?> €</td>
                                            <td class="fw-bold text-success"><?= number_format($payment['net_payment'], 2) ?> €</td>
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
                                <h6>Montant total net: <?= number_format(array_sum(array_column($filteredPayments, 'net_payment')), 2) ?> €</h6>
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
                                            <?= htmlspecialchars($member['name']) ?>
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
                                       required onchange="updatePaymentPreview()">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_end" class="form-label">Fin de Période *</label>
                                <input type="date" class="form-control" id="period_end" name="period_end" 
                                       required onchange="updatePaymentPreview()">
                            </div>
                        </div>
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
                                    <p><strong>Salaire de Base:</strong> <span id="preview_base_salary">0.00</span> €</p>
                                    <p><strong>Revenus Travaux:</strong> <span id="preview_work_revenue">0.00</span> €</p>
                                    <p><strong>Bonus:</strong> <span id="preview_bonus">0.00</span> €</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Total Brut:</strong> <span id="preview_gross">0.00</span> €</p>
                                    <p class="text-danger"><strong>Avances:</strong> -<span id="preview_advances">0.00</span> €</p>
                                    <p class="text-success"><strong>Net à Payer:</strong> <span id="preview_net">0.00</span> €</p>
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
                <p>Êtes-vous sûr de vouloir supprimer ce paiement de <strong id="delete_payment_amount"></strong> € ?</p>
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
    
    if (!crewId || !periodStart || !periodEnd) {
        document.getElementById('payment_preview').style.display = 'none';
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
    
    // Calculate pending advances
    const pendingAdvances = advanceData.filter(a => 
        a.crew_id === crewId && a.status === 'pending'
    );
    const totalAdvances = pendingAdvances.reduce((sum, a) => sum + parseFloat(a.amount), 0);
    
    // Calculate payment
    const bonusAmount = (baseSalary + workRevenue) * (bonusPercentage / 100);
    const grossPayment = baseSalary + workRevenue + bonusAmount;
    const netPayment = grossPayment - totalAdvances;
    
    // Update preview
    document.getElementById('preview_base_salary').textContent = baseSalary.toFixed(2);
    document.getElementById('preview_work_revenue').textContent = workRevenue.toFixed(2);
    document.getElementById('preview_bonus').textContent = bonusAmount.toFixed(2);
    document.getElementById('preview_gross').textContent = grossPayment.toFixed(2);
    document.getElementById('preview_advances').textContent = totalAdvances.toFixed(2);
    document.getElementById('preview_net').textContent = netPayment.toFixed(2);
    
    document.getElementById('payment_preview').style.display = 'block';
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
                    <p><strong>Salaire de Base:</strong> ${parseFloat(payment.base_salary).toFixed(2)} €</p>
                    <p><strong>Revenus Travaux:</strong> ${parseFloat(payment.work_revenue).toFixed(2)} €</p>
                    <p><strong>Bonus (${payment.bonus_percentage}%):</strong> ${parseFloat(payment.bonus_amount).toFixed(2)} €</p>
                    <p><strong>Total Brut:</strong> ${parseFloat(payment.gross_payment).toFixed(2)} €</p>
                    <p class="text-danger"><strong>Avances Déduites:</strong> -${parseFloat(payment.advances_deducted).toFixed(2)} €</p>
                    <p class="text-success"><strong>Net Payé:</strong> ${parseFloat(payment.net_payment).toFixed(2)} €</p>
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
