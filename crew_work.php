<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user has crew_id (crew members, viewers, or users with crew association)
if (!isset($_SESSION['user']) || empty($_SESSION['user']['crew_id'])) {
    header('Location: login.php');
    exit;
}

$crew_id = $_SESSION['user']['crew_id'] ?? null;
$crew_name = $_SESSION['user']['name'] ?? '';

$work = loadData('work');
$priceList = loadData('price_list');
$message = '';
$error = '';

// Handle success messages from redirects
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'work_added':
            $message = 'Prestation ajoutée avec succès.';
            break;
    }
}

// If crew_id is missing, try to find it from the crew data
if (empty($crew_id)) {
    $crew = loadData('crew');
    foreach ($crew as $member) {
        if ($member['username'] === $_SESSION['username']) {
            $crew_id = $member['id'];
            $_SESSION['user']['crew_id'] = $crew_id;
            break;
        }
    }
    error_log("Fixed missing crew_id for " . $_SESSION['username'] . ": " . ($crew_id ?? 'still missing'));
}

// Debug: Check if price list is loaded
if (empty($priceList)) {
    error_log("Price list is empty for crew member: " . $crew_name);
}

// Filter work for current crew member - only if crew_id is available
$myWork = [];
if (!empty($crew_id)) {
    $myWork = array_filter($work, function($w) use ($crew_id) {
        return isset($w['crew_id']) && $w['crew_id'] === $crew_id;
    });
} else {
    error_log("Warning: crew_id is empty for user " . $_SESSION['username'] . " - cannot filter work properly");
    // For safety, show no work if crew_id is missing
    $myWork = [];
}

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $selected_services = $_POST['services'] ?? [];
        $date = $_POST['date'] ?? date('Y-m-d H:i:s');
        $notes = trim($_POST['notes'] ?? '');
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        
        if (empty($selected_services)) {
            $error = 'Veuillez sélectionner au moins une prestation.';
        } else {
            // Calculate total amount and service details
            $total_amount = 0;
            $service_names = [];
            
            foreach ($selected_services as $service_id) {
                foreach ($priceList as $service) {
                    if ($service['id'] === $service_id) {
                        $total_amount += $service['price'];
                        $service_names[] = $service['name'];
                        break;
                    }
                }
            }
            
            $newWork = [
                'id' => generateId(),
                'type' => implode(', ', $service_names),
                'services' => $selected_services,
                'crew_id' => $crew_id,
                'amount' => $total_amount,
                'date' => $date,
                'notes' => $notes,
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'customer_email' => $customer_email,
                'added_by' => 'crew',
                'added_by_name' => $crew_name,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $work[] = $newWork;
            saveData('work', $work);
            
            // Redirect to prevent duplicate submissions on refresh
            header('Location: crew_work.php?success=work_added');
            exit;
        }
    }
}

$page_title = 'Mes Prestations';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Mes Prestations</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorkModal">
                    <i class="fas fa-plus me-1"></i>
                    Ajouter une Prestation
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <?php if (empty($myWork)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-cut fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucune prestation enregistrée</h5>
                            <p class="text-muted">Commencez par ajouter vos première prestations.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Prestations</th>
                                        <th>Client</th>
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
                                    
                                    foreach ($myWork as $w): ?>
                                        <tr>
                                            <td class="fw-bold"><?= date('d/m/Y H:i', strtotime($w['date'])) ?></td>
                                            <td><?= htmlspecialchars($w['type']) ?></td>
                                            <td>
                                                <?php if (!empty($w['customer_name'])): ?>
                                                    <strong><?= htmlspecialchars($w['customer_name']) ?></strong>
                                                    <?php if (!empty($w['customer_phone'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($w['customer_phone']) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-success"><?= number_format($w['amount'], 3) ?> TND</td>
                                            <td class="text-muted"><?= htmlspecialchars($w['notes'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title text-primary"><?= count($myWork) ?></h5>
                                            <p class="card-text mb-0">Total Prestations</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title text-success"><?= number_format(array_sum(array_column($myWork, 'amount')), 3) ?> TND</h5>
                                            <p class="card-text mb-0">Total Revenus</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title text-info"><?= number_format(array_sum(array_column($myWork, 'amount')) / max(count($myWork), 1), 3) ?> TND</h5>
                                            <p class="card-text mb-0">Moyenne par Prestation</p>
                                        </div>
                                    </div>
                                </div>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une Prestation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Prestations *</label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($priceList)): ?>
                                <div class="text-center py-3">
                                    <p class="text-muted">Aucune prestation disponible dans la liste de prix.</p>
                                    <p class="text-muted small">Contactez l'administrateur pour ajouter des prestations.</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                // Group services by category
                                $servicesByCategory = [];
                                foreach ($priceList as $service) {
                                    $category = $service['category'] ?: 'Autres';
                                    $servicesByCategory[$category][] = $service;
                                }
                                
                                foreach ($servicesByCategory as $category => $services): ?>
                                    <h6 class="text-primary mt-2 mb-2"><?= htmlspecialchars($category) ?></h6>
                                    <?php foreach ($services as $service): ?>
                                        <div class="form-check">
                                            <input class="form-check-input service-checkbox" type="checkbox" 
                                                   name="services[]" value="<?= $service['id'] ?>" 
                                                   id="service_<?= $service['id'] ?>"
                                                   data-price="<?= $service['price'] ?>"
                                                   onchange="updateTotal()">
                                            <label class="form-check-label d-flex justify-content-between w-100" for="service_<?= $service['id'] ?>">
                                                <span>
                                                    <strong><?= htmlspecialchars($service['name']) ?></strong>
                                                    <?php if (!empty($service['description'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($service['description']) ?></small>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="text-success fw-bold"><?= number_format($service['price'], 3) ?> TND</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2">
                            <strong>Total: <span id="service-total" class="text-success">0.000 TND</span></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date" class="form-label">Date et Heure</label>
                        <input type="datetime-local" class="form-control" id="date" name="date" 
                               value="<?= date('Y-m-d\TH:i') ?>">
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
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Function to update total price when services are selected
function updateTotal() {
    const checkboxes = document.querySelectorAll('.service-checkbox:checked');
    let total = 0;
    
    checkboxes.forEach(checkbox => {
        total += parseFloat(checkbox.dataset.price);
    });
    
    document.getElementById('service-total').textContent = total.toFixed(3) + ' TND';
}
</script>

<?php include 'includes/footer.php'; ?>