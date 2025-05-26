<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkPermission('edit');

$advances = loadData('advances');
$crew = loadData('crew');
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $crew_id = $_POST['crew_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        
        if (empty($crew_id) || $amount <= 0) {
            $error = 'Veuillez sélectionner un membre d\'équipe et saisir un montant valide.';
        } else {
            $newAdvance = [
                'id' => generateId(),
                'crew_id' => $crew_id,
                'amount' => $amount,
                'reason' => $reason,
                'date' => $date,
                'status' => 'pending', // pending, deducted
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $advances[] = $newAdvance;
            saveData('advances', $advances);
            $message = 'Avance ajoutée avec succès.';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $crew_id = $_POST['crew_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $date = $_POST['date'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        
        if (empty($crew_id) || $amount <= 0) {
            $error = 'Veuillez sélectionner un membre d\'équipe et saisir un montant valide.';
        } else {
            foreach ($advances as &$advance) {
                if ($advance['id'] === $id) {
                    $advance['crew_id'] = $crew_id;
                    $advance['amount'] = $amount;
                    $advance['reason'] = $reason;
                    $advance['date'] = $date;
                    $advance['status'] = $status;
                    $advance['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            
            saveData('advances', $advances);
            $message = 'Avance modifiée avec succès.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        $advances = array_filter($advances, function($advance) use ($id) {
            return $advance['id'] !== $id;
        });
        
        saveData('advances', $advances);
        $message = 'Avance supprimée avec succès.';
    }
}

// Filter advances
$crew_filter = $_GET['crew_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$filteredAdvances = $advances;

if ($crew_filter) {
    $filteredAdvances = array_filter($filteredAdvances, function($a) use ($crew_filter) {
        return $a['crew_id'] === $crew_filter;
    });
}

if ($status_filter) {
    $filteredAdvances = array_filter($filteredAdvances, function($a) use ($status_filter) {
        return $a['status'] === $status_filter;
    });
}

if ($date_from) {
    $filteredAdvances = array_filter($filteredAdvances, function($a) use ($date_from) {
        return $a['date'] >= $date_from;
    });
}

if ($date_to) {
    $filteredAdvances = array_filter($filteredAdvances, function($a) use ($date_to) {
        return $a['date'] <= $date_to;
    });
}

// Sort by date (newest first)
usort($filteredAdvances, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculate totals by crew member
$crewAdvanceTotals = [];
foreach ($crew as $member) {
    $memberAdvances = array_filter($advances, function($a) use ($member) {
        return $a['crew_id'] === $member['id'] && $a['status'] === 'pending';
    });
    $crewAdvanceTotals[$member['id']] = array_sum(array_column($memberAdvances, 'amount'));
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Gestion des Avances</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdvanceModal">
                    <i class="fas fa-plus"></i> Ajouter une Avance
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
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <?php foreach ($crew as $member): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title"><?= htmlspecialchars($member['name']) ?></h6>
                                <h4 class="card-text text-<?= $crewAdvanceTotals[$member['id']] > 0 ? 'warning' : 'success' ?>">
                                    <?= number_format($crewAdvanceTotals[$member['id']], 2) ?> €
                                </h4>
                                <small class="text-muted">Avances en attente</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
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
                        
                        <div class="col-md-2">
                            <label for="status_filter" class="form-label">Statut</label>
                            <select class="form-control" id="status_filter" name="status_filter">
                                <option value="">Tous les statuts</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="deducted" <?= $status_filter === 'deducted' ? 'selected' : '' ?>>Déduite</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">Date de</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">Date à</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-outline-primary">Filtrer</button>
                                <a href="advances.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <?php if (empty($filteredAdvances)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-hand-holding-usd fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucune avance trouvée</h5>
                            <p class="text-muted">Commencez par enregistrer des avances ou ajustez vos filtres.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Équipe</th>
                                        <th>Montant</th>
                                        <th>Raison</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredAdvances as $advance): 
                                        $crewMember = array_filter($crew, function($c) use ($advance) {
                                            return $c['id'] === $advance['crew_id'];
                                        });
                                        $crewMember = reset($crewMember);
                                    ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($advance['date'])) ?></td>
                                            <td><?= $crewMember ? htmlspecialchars($crewMember['name']) : 'N/A' ?></td>
                                            <td><?= number_format($advance['amount'], 2) ?> €</td>
                                            <td><?= htmlspecialchars($advance['reason']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $advance['status'] === 'pending' ? 'warning' : 'success' ?>">
                                                    <?= $advance['status'] === 'pending' ? 'En attente' : 'Déduite' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editAdvance('<?= $advance['id'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteAdvance('<?= $advance['id'] ?>', '<?= number_format($advance['amount'], 2) ?>')">
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
                                <h6>Total des avances: <?= count($filteredAdvances) ?></h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6>Montant total: <?= number_format(array_sum(array_column($filteredAdvances, 'amount')), 2) ?> €</h6>
                            </div>
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
                <h5 class="modal-title">Ajouter une Avance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="crew_id" class="form-label">Membre d'Équipe *</label>
                        <select class="form-control" id="crew_id" name="crew_id" required>
                            <option value="">Sélectionner un membre</option>
                            <?php foreach ($crew as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Montant (€) *</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Raison</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" 
                                  placeholder="Motif de l'avance..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" 
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Advance Modal -->
<div class="modal fade" id="editAdvanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier l'Avance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_crew_id" class="form-label">Membre d'Équipe *</label>
                        <select class="form-control" id="edit_crew_id" name="crew_id" required>
                            <option value="">Sélectionner un membre</option>
                            <?php foreach ($crew as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_amount" class="form-label">Montant (€) *</label>
                        <input type="number" class="form-control" id="edit_amount" name="amount" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_reason" class="form-label">Raison</label>
                        <textarea class="form-control" id="edit_reason" name="reason" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="edit_date" name="date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Statut</label>
                        <select class="form-control" id="edit_status" name="status">
                            <option value="pending">En attente</option>
                            <option value="deducted">Déduite</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Modifier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Advance Modal -->
<div class="modal fade" id="deleteAdvanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la Suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cette avance de <strong id="delete_advance_amount"></strong> € ?</p>
                <p class="text-danger">Cette action est irréversible.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_advance_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store advance data for JavaScript access
const advanceData = <?= json_encode($advances) ?>;

function editAdvance(id) {
    const advance = advanceData.find(a => a.id === id);
    if (advance) {
        document.getElementById('edit_id').value = advance.id;
        document.getElementById('edit_crew_id').value = advance.crew_id;
        document.getElementById('edit_amount').value = advance.amount;
        document.getElementById('edit_reason').value = advance.reason || '';
        document.getElementById('edit_date').value = advance.date;
        document.getElementById('edit_status').value = advance.status;
        
        new bootstrap.Modal(document.getElementById('editAdvanceModal')).show();
    }
}

function deleteAdvance(id, amount) {
    document.getElementById('delete_advance_id').value = id;
    document.getElementById('delete_advance_amount').textContent = amount;
    
    new bootstrap.Modal(document.getElementById('deleteAdvanceModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
