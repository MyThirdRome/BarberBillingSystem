<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkPermission('edit');

// Get crew member ID
$crew_id = $_GET['id'] ?? '';
if (empty($crew_id)) {
    header('Location: crew.php');
    exit;
}

// Load data
$crew = loadData('crew');
$work = loadData('work');
$advances = loadData('advances');
$payments = loadData('payments');

// Find the specific crew member
$crewMember = null;
foreach ($crew as $member) {
    if ($member['id'] === $crew_id) {
        $crewMember = $member;
        break;
    }
}

if (!$crewMember) {
    header('Location: crew.php');
    exit;
}

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_advance') {
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d H:i:s');
        
        if ($amount <= 0) {
            $error = 'Le montant doit être supérieur à 0.';
        } else {
            $newAdvance = [
                'id' => generateId(),
                'crew_id' => $crew_id,
                'amount' => $amount,
                'reason' => $reason,
                'date' => $date,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $advances[] = $newAdvance;
            saveData('advances', $advances);
            $message = 'Avance ajoutée avec succès.';
            
            // Reload data
            $advances = loadData('advances');
        }
    } elseif ($action === 'add_payment') {
        $amount = floatval($_POST['amount'] ?? 0);
        $bonus_percentage = floatval($_POST['bonus_percentage'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d H:i:s');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($amount <= 0) {
            $error = 'Le montant doit être supérieur à 0.';
        } else {
            $newPayment = [
                'id' => generateId(),
                'crew_id' => $crew_id,
                'amount' => $amount,
                'bonus_percentage' => $bonus_percentage,
                'date' => $date,
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $payments[] = $newPayment;
            saveData('payments', $payments);
            $message = 'Paiement ajouté avec succès.';
            
            // Reload data
            $payments = loadData('payments');
        }
    }
}

// Filter data for this crew member
$crewWork = array_filter($work, function($w) use ($crew_id) {
    return $w['crew_id'] === $crew_id;
});

$crewAdvances = array_filter($advances, function($a) use ($crew_id) {
    return $a['crew_id'] === $crew_id;
});

$crewPayments = array_filter($payments, function($p) use ($crew_id) {
    return $p['crew_id'] === $crew_id;
});

// Calculate statistics
$totalWork = array_sum(array_column($crewWork, 'amount'));
$pendingAdvances = array_sum(array_column(array_filter($crewAdvances, function($a) {
    return $a['status'] === 'pending';
}), 'amount'));
$totalAdvances = array_sum(array_column($crewAdvances, 'amount'));
$totalPayments = array_sum(array_column($crewPayments, 'amount'));

// This month statistics
$thisMonth = date('Y-m');
$thisMonthWork = array_filter($crewWork, function($w) use ($thisMonth) {
    return date('Y-m', strtotime($w['date'])) === $thisMonth;
});
$thisMonthEarnings = array_sum(array_column($thisMonthWork, 'amount'));

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="crew.php">Équipe</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($crewMember['name']) ?></li>
                        </ol>
                    </nav>
                    <h1 class="h3">Détails de <?= htmlspecialchars($crewMember['name']) ?></h1>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addAdvanceModal">
                        <i class="fas fa-hand-holding-usd"></i> Nouvelle Avance
                    </button>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="fas fa-money-bill-wave"></i> Nouveau Paiement
                    </button>
                </div>
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
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($thisMonthEarnings, 3) ?> TND</h4>
                            <p class="card-text">Ce Mois</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($totalWork, 3) ?> TND</h4>
                            <p class="card-text">Total Travaux</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-cut fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($pendingAdvances, 3) ?> TND</h4>
                            <p class="card-text">Avances Pending</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-hand-holding-usd fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($totalPayments, 3) ?> TND</h4>
                            <p class="card-text">Total Paiements</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Informations Personnelles</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6"><strong>Nom:</strong></div>
                        <div class="col-sm-6"><?= htmlspecialchars($crewMember['name']) ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>Téléphone:</strong></div>
                        <div class="col-sm-6"><?= htmlspecialchars($crewMember['phone'] ?? '-') ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>Email:</strong></div>
                        <div class="col-sm-6"><?= htmlspecialchars($crewMember['email'] ?? '-') ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>Salaire:</strong></div>
                        <div class="col-sm-6"><?= number_format($crewMember['salary'], 3) ?> TND</div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>% Bonus:</strong></div>
                        <div class="col-sm-6"><?= number_format($crewMember['bonus_percentage'], 1) ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs for different sections -->
    <ul class="nav nav-tabs" id="crewTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="work-tab" data-bs-toggle="tab" data-bs-target="#work" 
                    type="button" role="tab">Travaux Récents</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="advances-tab" data-bs-toggle="tab" data-bs-target="#advances" 
                    type="button" role="tab">Avances</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" 
                    type="button" role="tab">Paiements</button>
        </li>
    </ul>

    <div class="tab-content" id="crewTabsContent">
        <!-- Work Tab -->
        <div class="tab-pane fade show active" id="work" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($crewWork)): ?>
                        <p class="text-muted">Aucun travail enregistré.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Client</th>
                                        <th>Montant</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    usort($crewWork, function($a, $b) {
                                        return strtotime($b['date']) - strtotime($a['date']);
                                    });
                                    
                                    foreach (array_slice($crewWork, 0, 20) as $w): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($w['date'])) ?></td>
                                            <td><?= htmlspecialchars($w['type']) ?></td>
                                            <td>
                                                <?php if (!empty($w['customer_name'])): ?>
                                                    <strong><?= htmlspecialchars($w['customer_name']) ?></strong><br>
                                                    <?php if (!empty($w['customer_phone'])): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($w['customer_phone']) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-success"><?= number_format($w['amount'], 3) ?> TND</td>
                                            <td><?= htmlspecialchars($w['notes']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Advances Tab -->
        <div class="tab-pane fade" id="advances" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($crewAdvances)): ?>
                        <p class="text-muted">Aucune avance enregistrée.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Motif</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    usort($crewAdvances, function($a, $b) {
                                        return strtotime($b['date']) - strtotime($a['date']);
                                    });
                                    
                                    foreach ($crewAdvances as $advance): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($advance['date'])) ?></td>
                                            <td class="fw-bold text-warning"><?= number_format($advance['amount'], 3) ?> TND</td>
                                            <td><?= htmlspecialchars($advance['reason']) ?></td>
                                            <td>
                                                <?php if ($advance['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">En Attente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Déduite</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payments Tab -->
        <div class="tab-pane fade" id="payments" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($crewPayments)): ?>
                        <p class="text-muted">Aucun paiement enregistré.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>% Bonus</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    usort($crewPayments, function($a, $b) {
                                        return strtotime($b['date']) - strtotime($a['date']);
                                    });
                                    
                                    foreach ($crewPayments as $payment): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($payment['date'])) ?></td>
                                            <td class="fw-bold text-success"><?= number_format($payment['amount'], 3) ?> TND</td>
                                            <td><?= number_format($payment['bonus_percentage'], 1) ?>%</td>
                                            <td><?= htmlspecialchars($payment['notes']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Advance Modal -->
<div class="modal fade" id="addAdvanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle Avance - <?= htmlspecialchars($crewMember['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_advance">
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Montant (TND) *</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.001" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motif *</label>
                        <input type="text" class="form-control" id="reason" name="reason" 
                               placeholder="Motif de l'avance" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="datetime-local" class="form-control" id="date" name="date" 
                               value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Ajouter Avance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Paiement - <?= htmlspecialchars($crewMember['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_payment">
                    
                    <div class="mb-3">
                        <label for="payment_amount" class="form-label">Montant (TND) *</label>
                        <input type="number" class="form-control" id="payment_amount" name="amount" 
                               step="0.001" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bonus_percentage" class="form-label">Pourcentage Bonus</label>
                        <input type="number" class="form-control" id="bonus_percentage" name="bonus_percentage" 
                               step="0.1" min="0" max="100" value="<?= $crewMember['bonus_percentage'] ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Date</label>
                        <input type="datetime-local" class="form-control" id="payment_date" name="date" 
                               value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="payment_notes" name="notes" rows="3" 
                                  placeholder="Notes sur ce paiement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Enregistrer Paiement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>