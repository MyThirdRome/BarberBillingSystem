<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Load charge types from config
require_once 'includes/config.php';

// Only admins can access charges management
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: crew_dashboard.php');
    exit;
}

checkPermission('edit');

$charges = loadData('charges');
$crew = loadData('crew');
$advances = loadData('advances');
$message = '';
$error = '';

// Get selected month from query parameter, default to current month
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $type = $_POST['type'] ?? '';
        $crew_id = $_POST['crew_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        
        if (empty($type) || $amount <= 0) {
            $error = 'Le type de charge et le montant sont obligatoires.';
        } elseif ($type === 'salary' && empty($crew_id)) {
            $error = 'Veuillez sélectionner un membre d\'équipe pour le salaire.';
        } elseif (in_array($type, ['divers']) && empty($description)) {
            $error = 'Veuillez fournir une description pour les charges diverses.';
        } else {
            $newCharge = [
                'id' => generateId(),
                'type' => $type,
                'crew_id' => $crew_id,
                'amount' => $amount,
                'description' => $description,
                'date' => $date,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $charges[] = $newCharge;
            saveData('charges', $charges);
            $message = 'Charge ajoutée avec succès.';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $type = $_POST['type'] ?? '';
        $crew_id = $_POST['crew_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['date'] ?? '';
        
        if (empty($type) || $amount <= 0) {
            $error = 'Le type de charge et le montant sont obligatoires.';
        } else {
            $chargeToEdit = null;
            foreach ($charges as &$charge) {
                if ($charge['id'] === $id) {
                    $chargeToEdit = $charge;
                    $charge['type'] = $type;
                    $charge['crew_id'] = $crew_id;
                    $charge['amount'] = $amount;
                    $charge['description'] = $description;
                    $charge['date'] = $date;
                    $charge['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            
            saveData('charges', $charges);
            
            // If this is an advance-related charge, update the corresponding advance
            if ($chargeToEdit && isset($chargeToEdit['advance_id'])) {
                foreach ($advances as &$advance) {
                    if ($advance['id'] === $chargeToEdit['advance_id']) {
                        $advance['amount'] = $amount;
                        $advance['date'] = $date;
                        // Extract reason from description (remove "Avance: " prefix)
                        $advance['reason'] = str_replace('Avance: ', '', $description);
                        $advance['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
                saveData('advances', $advances);
            }
            
            $message = 'Charge modifiée avec succès.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        // Find the charge to be deleted to check if it's advance-related
        $chargeToDelete = null;
        foreach ($charges as $charge) {
            if ($charge['id'] === $id) {
                $chargeToDelete = $charge;
                break;
            }
        }
        
        $charges = array_filter($charges, function($charge) use ($id) {
            return $charge['id'] !== $id;
        });
        
        saveData('charges', $charges);
        
        // If this was an advance-related charge, also delete the corresponding advance
        if ($chargeToDelete && isset($chargeToDelete['advance_id'])) {
            $advances = array_filter($advances, function($advance) use ($chargeToDelete) {
                return $advance['id'] !== $chargeToDelete['advance_id'];
            });
            saveData('advances', $advances);
            $message = 'Charge et avance correspondante supprimées avec succès.';
        } else {
            $message = 'Charge supprimée avec succès.';
        }
    }
}

// Calculate monthly statistics for selected month
$currentMonth = date('Y-m');

// Get all advances paid this month (actual spending this month)
$monthlyAdvances = array_filter($advances, function($a) use ($selectedMonth) {
    return date('Y-m', strtotime($a['date'])) === $selectedMonth;
});
$totalAdvancesPaid = array_sum(array_column($monthlyAdvances, 'amount'));

// Get other charges (non-salary, non-advance) paid this month
$otherCharges = array_filter($charges, function($c) use ($selectedMonth) {
    return date('Y-m', strtotime($c['date'])) === $selectedMonth && 
           strpos($c['type'], 'Salaire') === false && 
           strpos($c['type'], 'Avance') === false;
});
$totalOtherCharges = array_sum(array_column($otherCharges, 'amount'));

// Total spending this month (advances + other charges, NOT salaries)
$totalMonthlySpending = $totalAdvancesPaid + $totalOtherCharges;

// Today's spending
$today = date('Y-m-d');
$todayAdvances = array_filter($advances, function($a) use ($today) {
    return date('Y-m-d', strtotime($a['date'])) === $today;
});
$todayOtherCharges = array_filter($charges, function($c) use ($today) {
    return date('Y-m-d', strtotime($c['date'])) === $today && 
           strpos($c['type'], 'Salaire') === false && 
           strpos($c['type'], 'Avance') === false;
});
$todaySpending = array_sum(array_column($todayAdvances, 'amount')) + array_sum(array_column($todayOtherCharges, 'amount'));

// Filter charges
$type_filter = $_GET['type_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$filteredCharges = $charges;

if ($type_filter) {
    $filteredCharges = array_filter($filteredCharges, function($c) use ($type_filter) {
        return $c['type'] === $type_filter;
    });
}

if ($date_from) {
    $filteredCharges = array_filter($filteredCharges, function($c) use ($date_from) {
        return $c['date'] >= $date_from;
    });
}

if ($date_to) {
    $filteredCharges = array_filter($filteredCharges, function($c) use ($date_to) {
        return $c['date'] <= $date_to;
    });
}

// Sort by date (newest first)
usort($filteredCharges, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Use charge types from config
// $chargeTypes is already available from config.php

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Gestion des Charges</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChargeModal">
                    <i class="fas fa-plus"></i> Ajouter une Charge
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
            
            <!-- Month Selector -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="card-title mb-0">Statistiques des Charges</h5>
                                </div>
                                <div class="col-md-6">
                                    <form method="GET" class="d-flex">
                                        <select name="month" class="form-select me-2" onchange="this.form.submit()">
                                            <?php
                                            for ($i = 0; $i < 12; $i++) {
                                                $month = date('Y-m', strtotime("-$i months"));
                                                $monthName = date('F Y', strtotime($month . '-01'));
                                                $selected = ($month === $selectedMonth) ? 'selected' : '';
                                                echo "<option value='$month' $selected>$monthName</option>";
                                            }
                                            ?>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Statistics Cards -->
            <div class="row mb-4 g-3">
                <div class="col-xl-6 col-lg-6 col-md-6">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h4 class="card-title mb-1"><?= number_format($totalMonthlySpending, 2) ?></h4>
                                <small class="text-white-50">TND</small>
                                <p class="card-text mb-0 mt-1">Total Dépenses</p>
                                <small class="text-white-50"><?= date('F Y', strtotime($selectedMonth . '-01')) ?></small>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-credit-card fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-6 col-lg-6 col-md-6">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h4 class="card-title mb-1"><?= number_format($todaySpending, 2) ?></h4>
                                <small class="text-white-50">TND</small>
                                <p class="card-text mb-0 mt-1">Aujourd'hui</p>
                                <small class="text-white-50"><?= date('d/m/Y') ?></small>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-calendar-day fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="type_filter" class="form-label">Type de Charge</label>
                            <select class="form-control" id="type_filter" name="type_filter">
                                <option value="">Tous les types</option>
                                <?php foreach ($chargeTypes as $key => $config): ?>
                                    <option value="<?= $key ?>" <?= $type_filter === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($config['label']) ?>
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
                                <a href="charges.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <?php if (empty($filteredCharges)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucune charge trouvée</h5>
                            <p class="text-muted">Commencez par enregistrer des charges ou ajustez vos filtres.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Équipe</th>
                                        <th>Montant</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredCharges as $charge): 
                                        $crewMember = null;
                                        if (isset($charge['crew_id']) && $charge['crew_id']) {
                                            $crewMember = array_filter($crew, function($c) use ($charge) {
                                                return $c['id'] === $charge['crew_id'];
                                            });
                                            $crewMember = reset($crewMember);
                                        }
                                    ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($charge['date'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= getChargeTypeBadgeColor($charge['type']) ?>">
                                                    <?= htmlspecialchars($chargeTypes[$charge['type']]['label'] ?? $charge['type']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($charge['description']) ?></td>
                                            <td><?= $crewMember ? htmlspecialchars($crewMember['name']) : '-' ?></td>
                                            <td><?= number_format($charge['amount'], 2) ?> TND</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editCharge('<?= $charge['id'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteCharge('<?= $charge['id'] ?>', '<?= htmlspecialchars($chargeTypes[$charge['type']] ?? $charge['type']) ?>')">
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
                                <h6>Total des charges: <?= count($filteredCharges) ?></h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6>Montant total: <?= number_format(array_sum(array_column($filteredCharges, 'amount')), 2) ?> TND</h6>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Charge Modal -->
<div class="modal fade" id="addChargeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Charge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Type de Charge *</label>
                        <select class="form-control" id="type" name="type" required onchange="toggleChargeFields()">
                            <option value="">Sélectionner un type</option>
                            <?php foreach ($chargeTypes as $key => $config): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($config['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="crew_select" style="display: none;">
                        <label for="crew_id" class="form-label">Membre d'Équipe</label>
                        <select class="form-control" id="crew_id" name="crew_id">
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
                    
                    <div class="mb-3" id="description_field">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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

<!-- Edit Charge Modal -->
<div class="modal fade" id="editChargeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la Charge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Type de Charge *</label>
                        <select class="form-control" id="edit_type" name="type" required onchange="toggleEditChargeFields()">
                            <option value="">Sélectionner un type</option>
                            <?php foreach ($chargeTypes as $key => $config): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($config['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="edit_crew_select" style="display: none;">
                        <label for="edit_crew_id" class="form-label">Membre d'Équipe</label>
                        <select class="form-control" id="edit_crew_id" name="crew_id">
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
                    
                    <div class="mb-3" id="edit_description_field">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="edit_date" name="date">
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

<!-- Delete Charge Modal -->
<div class="modal fade" id="deleteChargeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la Suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cette charge <strong id="delete_charge_type"></strong> ?</p>
                <p class="text-danger">Cette action est irréversible.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_charge_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store charge data for JavaScript access
const chargeData = <?= json_encode($charges) ?>;

function toggleChargeFields() {
    const type = document.getElementById('type').value;
    const crewSelect = document.getElementById('crew_select');
    const descriptionField = document.getElementById('description_field');
    
    if (type === 'salary') {
        crewSelect.style.display = 'block';
        document.getElementById('crew_id').required = true;
    } else {
        crewSelect.style.display = 'none';
        document.getElementById('crew_id').required = false;
    }
    
    if (type === 'divers') {
        descriptionField.querySelector('textarea').required = true;
    } else {
        descriptionField.querySelector('textarea').required = false;
    }
}

function toggleEditChargeFields() {
    const type = document.getElementById('edit_type').value;
    const crewSelect = document.getElementById('edit_crew_select');
    
    if (type === 'salary') {
        crewSelect.style.display = 'block';
        document.getElementById('edit_crew_id').required = true;
    } else {
        crewSelect.style.display = 'none';
        document.getElementById('edit_crew_id').required = false;
    }
    
    if (type === 'divers') {
        document.getElementById('edit_description').required = true;
    } else {
        document.getElementById('edit_description').required = false;
    }
}

function editCharge(id) {
    const charge = chargeData.find(c => c.id === id);
    if (charge) {
        document.getElementById('edit_id').value = charge.id;
        document.getElementById('edit_type').value = charge.type;
        document.getElementById('edit_crew_id').value = charge.crew_id || '';
        document.getElementById('edit_amount').value = charge.amount;
        document.getElementById('edit_description').value = charge.description || '';
        document.getElementById('edit_date').value = charge.date;
        
        toggleEditChargeFields();
        
        new bootstrap.Modal(document.getElementById('editChargeModal')).show();
    }
}

function deleteCharge(id, type) {
    document.getElementById('delete_charge_id').value = id;
    document.getElementById('delete_charge_type').textContent = type;
    
    new bootstrap.Modal(document.getElementById('deleteChargeModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
