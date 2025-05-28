<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user is crew member
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'crew') {
    header('Location: login.php');
    exit;
}

$crew_id = $_SESSION['user']['crew_id'];
$crew_name = $_SESSION['user']['name'];

// Handle messages
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

// Load data
$work = loadData('work');
$advances = loadData('advances');
$payments = loadData('payments');

// Filter data for current crew member
$myWork = array_filter($work, function($w) use ($crew_id) {
    return $w['crew_id'] === $crew_id;
});

$myAdvances = array_filter($advances, function($a) use ($crew_id) {
    return $a['crew_id'] === $crew_id;
});

$myPayments = array_filter($payments, function($p) use ($crew_id) {
    return $p['crew_id'] === $crew_id;
});

// Calculate statistics
$totalEarnings = array_sum(array_column($myWork, 'amount'));
$pendingAdvances = array_sum(array_column(array_filter($myAdvances, function($a) {
    return $a['status'] === 'pending';
}), 'amount'));

$thisMonthWork = array_filter($myWork, function($w) {
    return date('Y-m', strtotime($w['date'])) === date('Y-m');
});
$thisMonthEarnings = array_sum(array_column($thisMonthWork, 'amount'));

$lastMonthWork = array_filter($myWork, function($w) {
    return date('Y-m', strtotime($w['date'])) === date('Y-m', strtotime('last month'));
});
$lastMonthEarnings = array_sum(array_column($lastMonthWork, 'amount'));

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Bonjour, <?= htmlspecialchars($crew_name) ?></h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorkModal">
                    <i class="fas fa-plus"></i> Ajouter Travail
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
                            <h4 class="card-title"><?= number_format($lastMonthEarnings, 3) ?> TND</h4>
                            <p class="card-text">Mois Précédent</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x"></i>
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
                            <h4 class="card-title"><?= number_format($totalEarnings, 3) ?> TND</h4>
                            <p class="card-text">Total Général</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-coins fa-2x"></i>
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
                            <p class="card-text">Avances en Attente</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-hand-holding-usd fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Work -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Mes Prestations Récentes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($myWork)): ?>
                        <p class="text-muted">Aucune prestation enregistrée.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Montant</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Sort by date (newest first)
                                    usort($myWork, function($a, $b) {
                                        return strtotime($b['date']) - strtotime($a['date']);
                                    });
                                    
                                    foreach (array_slice($myWork, 0, 10) as $w): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($w['date'])) ?></td>
                                            <td><?= htmlspecialchars($w['type']) ?></td>
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
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Mes Avances</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($myAdvances)): ?>
                        <p class="text-muted">Aucune avance.</p>
                    <?php else: ?>
                        <?php 
                        // Sort by date (newest first)
                        usort($myAdvances, function($a, $b) {
                            return strtotime($b['date']) - strtotime($a['date']);
                        });
                        
                        foreach (array_slice($myAdvances, 0, 5) as $a): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" 
                                 style="background-color: <?= $a['status'] === 'pending' ? '#fff3cd' : '#d1ecf1' ?>">
                                <div>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($a['date'])) ?></small><br>
                                    <strong><?= number_format($a['amount'], 3) ?> TND</strong>
                                </div>
                                <span class="badge bg-<?= $a['status'] === 'pending' ? 'warning' : 'info' ?>">
                                    <?= $a['status'] === 'pending' ? 'En attente' : 'Déduite' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Mes Paiements</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($myPayments)): ?>
                        <p class="text-muted">Aucun paiement.</p>
                    <?php else: ?>
                        <?php 
                        // Sort by date (newest first)
                        usort($myPayments, function($a, $b) {
                            return strtotime($b['payment_date']) - strtotime($a['payment_date']);
                        });
                        
                        foreach (array_slice($myPayments, 0, 3) as $p): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded bg-light">
                                <div>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($p['payment_date'])) ?></small><br>
                                    <strong class="text-success"><?= number_format($p['net_payment'], 3) ?> TND</strong>
                                </div>
                                <i class="fas fa-check-circle text-success"></i>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Work Modal -->
<div class="modal fade" id="addWorkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Travail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="crew_work.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="crew_id" value="<?= $crew_id ?>">
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Type de Service *</label>
                        <input type="text" class="form-control" id="type" name="type" 
                               placeholder="Ex: Coupe, Brushing, Coloration, Barbe..." required>
                        <small class="text-muted">Décrivez le service effectué</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Montant (TND) *</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.001" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date" class="form-label">Date et Heure *</label>
                        <input type="datetime-local" class="form-control" id="date" name="date" 
                               value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    
                    <hr>
                    <h6 class="text-primary">Informations Client</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">Nom du Client</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                       placeholder="Nom complet du client">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" 
                                       placeholder="+216 XX XXX XXX">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customer_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="customer_email" name="customer_email" 
                               placeholder="client@example.com">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Notes sur le service ou le client..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>