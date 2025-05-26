<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkPermission('edit');

$work = loadData('work');
$crew = loadData('crew');
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $type = trim($_POST['type'] ?? '');
        $crew_id = $_POST['crew_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d H:i:s');
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($type) || empty($crew_id) || $amount <= 0) {
            $error = 'Le type de travail, l\'équipe et le montant sont obligatoires.';
        } else {
            $newWork = [
                'id' => generateId(),
                'type' => $type,
                'crew_id' => $crew_id,
                'amount' => $amount,
                'date' => $date,
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $work[] = $newWork;
            saveData('work', $work);
            $message = 'Travail ajouté avec succès.';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $type = trim($_POST['type'] ?? '');
        $crew_id = $_POST['crew_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($type) || empty($crew_id) || $amount <= 0) {
            $error = 'Le type de travail, l\'équipe et le montant sont obligatoires.';
        } else {
            foreach ($work as &$w) {
                if ($w['id'] === $id) {
                    $w['type'] = $type;
                    $w['crew_id'] = $crew_id;
                    $w['amount'] = $amount;
                    $w['date'] = $date;
                    $w['notes'] = $notes;
                    $w['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            
            saveData('work', $work);
            $message = 'Travail modifié avec succès.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        $work = array_filter($work, function($w) use ($id) {
            return $w['id'] !== $id;
        });
        
        saveData('work', $work);
        $message = 'Travail supprimé avec succès.';
    }
}

// Filter and search
$search = $_GET['search'] ?? '';
$crew_filter = $_GET['crew_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$filteredWork = $work;

if ($search) {
    $filteredWork = array_filter($filteredWork, function($w) use ($search) {
        return stripos($w['type'], $search) !== false || stripos($w['notes'], $search) !== false;
    });
}

if ($crew_filter) {
    $filteredWork = array_filter($filteredWork, function($w) use ($crew_filter) {
        return $w['crew_id'] === $crew_filter;
    });
}

if ($date_from) {
    $filteredWork = array_filter($filteredWork, function($w) use ($date_from) {
        return substr($w['date'], 0, 10) >= $date_from;
    });
}

if ($date_to) {
    $filteredWork = array_filter($filteredWork, function($w) use ($date_to) {
        return substr($w['date'], 0, 10) <= $date_to;
    });
}

// Sort by date (newest first)
usort($filteredWork, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Gestion des Travaux</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorkModal">
                    <i class="fas fa-plus"></i> Ajouter un Travail
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
                        <div class="col-md-3">
                            <label for="search" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?= htmlspecialchars($search) ?>" placeholder="Type de travail, notes...">
                        </div>
                        
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
                            <label for="date_from" class="form-label">Date de</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">Date à</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-outline-primary">Filtrer</button>
                                <a href="work.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <?php if (empty($filteredWork)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-cut fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucun travail trouvé</h5>
                            <p class="text-muted">Commencez par enregistrer des travaux ou ajustez vos filtres.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type de Travail</th>
                                        <th>Équipe</th>
                                        <th>Montant</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredWork as $w): 
                                        $crewMember = array_filter($crew, function($c) use ($w) {
                                            return $c['id'] === $w['crew_id'];
                                        });
                                        $crewMember = reset($crewMember);
                                    ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($w['date'])) ?></td>
                                            <td><?= htmlspecialchars($w['type']) ?></td>
                                            <td><?= $crewMember ? htmlspecialchars($crewMember['name']) : 'N/A' ?></td>
                                            <td><?= number_format($w['amount'], 2) ?> TND</td>
                                            <td><?= htmlspecialchars($w['notes']) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editWork('<?= $w['id'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteWork('<?= $w['id'] ?>', '<?= htmlspecialchars($w['type']) ?>')">
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
                                <h6>Total des travaux: <?= count($filteredWork) ?></h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6>Montant total: <?= number_format(array_sum(array_column($filteredWork, 'amount')), 2) ?> TND</h6>
                            </div>
                        </div>
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
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Type de Travail *</label>
                        <input type="text" class="form-control" id="type" name="type" 
                               placeholder="Ex: Coupe, Barbe, Coloration..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="crew_id" class="form-label">Équipe *</label>
                        <select class="form-control" id="crew_id" name="crew_id" required>
                            <option value="">Sélectionner un membre</option>
                            <?php foreach ($crew as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Montant (TND) *</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date" class="form-label">Date et Heure</label>
                        <input type="datetime-local" class="form-control" id="date" name="date" 
                               value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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

<!-- Edit Work Modal -->
<div class="modal fade" id="editWorkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le Travail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Type de Travail *</label>
                        <input type="text" class="form-control" id="edit_type" name="type" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_crew_id" class="form-label">Équipe *</label>
                        <select class="form-control" id="edit_crew_id" name="crew_id" required>
                            <option value="">Sélectionner un membre</option>
                            <?php foreach ($crew as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_amount" class="form-label">Montant (TND) *</label>
                        <input type="number" class="form-control" id="edit_amount" name="amount" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date" class="form-label">Date et Heure</label>
                        <input type="datetime-local" class="form-control" id="edit_date" name="date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
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

<!-- Delete Work Modal -->
<div class="modal fade" id="deleteWorkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la Suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer le travail <strong id="delete_work_type"></strong> ?</p>
                <p class="text-danger">Cette action est irréversible.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_work_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store work data for JavaScript access
const workData = <?= json_encode($work) ?>;

function editWork(id) {
    const work = workData.find(w => w.id === id);
    if (work) {
        document.getElementById('edit_id').value = work.id;
        document.getElementById('edit_type').value = work.type;
        document.getElementById('edit_crew_id').value = work.crew_id;
        document.getElementById('edit_amount').value = work.amount;
        document.getElementById('edit_date').value = work.date.substring(0, 16);
        document.getElementById('edit_notes').value = work.notes || '';
        
        new bootstrap.Modal(document.getElementById('editWorkModal')).show();
    }
}

function deleteWork(id, type) {
    document.getElementById('delete_work_id').value = id;
    document.getElementById('delete_work_type').textContent = type;
    
    new bootstrap.Modal(document.getElementById('deleteWorkModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
