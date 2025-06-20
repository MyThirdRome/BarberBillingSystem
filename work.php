<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Only admins can access work management
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: crew_dashboard.php');
    exit;
}

checkPermission('edit');

$work = loadData('work');
$crew = loadData('crew');
$priceList = loadData('price_list');
$message = '';
$error = '';

// Handle success messages from redirects
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'work_added':
            $message = 'Travail ajouté avec succès.';
            break;
        case 'work_updated':
            $message = 'Travail modifié avec succès.';
            break;
        case 'work_deleted':
            $message = 'Travail supprimé avec succès.';
            break;
    }
}

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $crew_id = $_POST['crew_id'] ?? '';
        $selected_services = $_POST['services'] ?? [];
        $date = $_POST['date'] ?? date('Y-m-d H:i:s');
        $notes = trim($_POST['notes'] ?? '');
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        
        if (empty($crew_id) || empty($selected_services)) {
            $error = 'Veuillez sélectionner un membre d\'équipe et au moins une prestation.';
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
                'added_by' => 'admin',
                'added_by_name' => $_SESSION['username'] ?? 'Administrateur',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $work[] = $newWork;
            saveData('work', $work);
            
            // Redirect to prevent duplicate submissions on refresh
            header('Location: work.php?success=work_added');
            exit;
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $type = trim($_POST['type'] ?? '');
        $crew_id = $_POST['crew_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        
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
                    $w['customer_name'] = $customer_name;
                    $w['customer_phone'] = $customer_phone;
                    $w['customer_email'] = $customer_email;
                    $w['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            
            saveData('work', $work);
            
            // Redirect to prevent duplicate submissions on refresh
            header('Location: work.php?success=work_updated');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        $work = array_filter($work, function($w) use ($id) {
            return $w['id'] !== $id;
        });
        
        saveData('work', $work);
        
        // Redirect to prevent duplicate submissions on refresh
        header('Location: work.php?success=work_deleted');
        exit;
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
                <h1 class="h3">Gestion des Prestations</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorkModal">
                    <i class="fas fa-plus"></i> Ajouter une Prestation
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
                    <div class="text-center py-5" id="no-work-message" style="display: none;">
                        <i class="fas fa-cut fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun travail trouvé</h5>
                        <p class="text-muted">Commencez par enregistrer des travaux ou ajustez vos filtres.</p>
                    </div>
                    <div class="table-responsive" id="work-table-container">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type de Travail</th>
                                        <th>Équipe</th>
                                        <th>Client</th>
                                        <th>Montant</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="work-table-tbody">
                                    <!-- Work entries will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Controls -->
                        <nav aria-label="Work pagination" id="work-pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <!-- Pagination buttons will be generated by JavaScript -->
                            </ul>
                        </nav>
                        
                        <div class="row mt-3" id="work-summary">
                            <div class="col-md-6">
                                <h6 id="work-count">Total des travaux: 0</h6>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6 id="work-total">Montant total: 0.00 TND</h6>
                            </div>
                        </div>
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
                <h5 class="modal-title">Ajouter une Prestation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
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
                        <label class="form-label">Prestations *</label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
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

<!-- Edit Work Modal -->
<div class="modal fade" id="editWorkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la Prestation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Type de Prestation *</label>
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
                    
                    <hr>
                    <h6 class="text-primary">Informations Client</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_customer_name" class="form-label">Nom du Client</label>
                                <input type="text" class="form-control" id="edit_customer_name" name="customer_name" 
                                       placeholder="Nom complet du client">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_customer_phone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="edit_customer_phone" name="customer_phone" 
                                       placeholder="+216 XX XXX XXX">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_customer_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_customer_email" name="customer_email" 
                               placeholder="client@example.com">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3" 
                                  placeholder="Notes sur le service ou le client..."></textarea>
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

// Function to update total price when services are selected
function updateTotal() {
    const checkboxes = document.querySelectorAll('.service-checkbox:checked');
    let total = 0;
    
    checkboxes.forEach(checkbox => {
        total += parseFloat(checkbox.dataset.price);
    });
    
    document.getElementById('service-total').textContent = total.toFixed(3) + ' TND';
}

function editWork(id) {
    const work = workData.find(w => w.id === id);
    if (work) {
        document.getElementById('edit_id').value = work.id;
        document.getElementById('edit_type').value = work.type;
        document.getElementById('edit_crew_id').value = work.crew_id;
        document.getElementById('edit_amount').value = work.amount;
        document.getElementById('edit_date').value = work.date.substring(0, 16);
        document.getElementById('edit_notes').value = work.notes || '';
        document.getElementById('edit_customer_name').value = work.customer_name || '';
        document.getElementById('edit_customer_phone').value = work.customer_phone || '';
        document.getElementById('edit_customer_email').value = work.customer_email || '';
        
        new bootstrap.Modal(document.getElementById('editWorkModal')).show();
    }
}

function deleteWork(id, type) {
    document.getElementById('delete_work_id').value = id;
    document.getElementById('delete_work_type').textContent = type;
    
    new bootstrap.Modal(document.getElementById('deleteWorkModal')).show();
}

// Work pagination functionality
const allWorkData = <?= json_encode($filteredWork) ?>;
const allCrewData = <?= json_encode($crew) ?>;
console.log('Filtered work data:', allWorkData);
console.log('All work data:', <?= json_encode($work) ?>);
let currentWorkPage = 1;
const workItemsPerPage = 10;

function displayWorkPage(page = 1) {
    const totalItems = allWorkData.length;
    const totalPages = Math.ceil(totalItems / workItemsPerPage);
    const startIndex = (page - 1) * workItemsPerPage;
    const endIndex = startIndex + workItemsPerPage;
    const pageItems = allWorkData.slice(startIndex, endIndex);
    
    const tbody = document.getElementById('work-table-tbody');
    tbody.innerHTML = '';
    
    pageItems.forEach(work => {
        const crewMember = allCrewData.find(c => c.id === work.crew_id);
        const row = document.createElement('tr');
        
        const customerInfo = work.customer_name ? 
            `<strong>${work.customer_name}</strong><br>
             ${work.customer_phone ? `<small class="text-muted">${work.customer_phone}</small><br>` : ''}
             ${work.customer_email ? `<small class="text-muted">${work.customer_email}</small>` : ''}` :
            '<span class="text-muted">-</span>';
        
        row.innerHTML = `
            <td>${new Date(work.date).toLocaleDateString('fr-FR')} ${new Date(work.date).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}</td>
            <td>${work.type}</td>
            <td>${crewMember ? crewMember.name : 'N/A'}</td>
            <td>${customerInfo}</td>
            <td>${parseFloat(work.amount).toLocaleString('fr-FR', {minimumFractionDigits: 2})} TND</td>
            <td>${work.notes || ''}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editWork('${work.id}')">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteWork('${work.id}', '${work.type}')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Update summary
    const totalAmount = allWorkData.reduce((sum, work) => sum + parseFloat(work.amount), 0);
    document.getElementById('work-count').textContent = `Total des travaux: ${totalItems}`;
    document.getElementById('work-total').textContent = `Montant total: ${totalAmount.toLocaleString('fr-FR', {minimumFractionDigits: 2})} TND`;
    
    // Update pagination
    if (totalPages > 1) {
        updateWorkPagination(page, totalPages);
        document.getElementById('work-pagination').style.display = 'block';
    } else {
        document.getElementById('work-pagination').style.display = 'none';
    }
}

function updateWorkPagination(currentPage, totalPages) {
    const pagination = document.querySelector('#work-pagination .pagination');
    pagination.innerHTML = '';
    
    // Previous button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" onclick="changeWorkPage(${currentPage - 1})">Précédent</a>`;
    pagination.appendChild(prevLi);
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        const firstLi = document.createElement('li');
        firstLi.className = 'page-item';
        firstLi.innerHTML = `<a class="page-link" href="#" onclick="changeWorkPage(1)">1</a>`;
        pagination.appendChild(firstLi);
        
        if (startPage > 2) {
            const dotsLi = document.createElement('li');
            dotsLi.className = 'page-item disabled';
            dotsLi.innerHTML = '<span class="page-link">...</span>';
            pagination.appendChild(dotsLi);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#" onclick="changeWorkPage(${i})">${i}</a>`;
        pagination.appendChild(li);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const dotsLi = document.createElement('li');
            dotsLi.className = 'page-item disabled';
            dotsLi.innerHTML = '<span class="page-link">...</span>';
            pagination.appendChild(dotsLi);
        }
        
        const lastLi = document.createElement('li');
        lastLi.className = 'page-item';
        lastLi.innerHTML = `<a class="page-link" href="#" onclick="changeWorkPage(${totalPages})">${totalPages}</a>`;
        pagination.appendChild(lastLi);
    }
    
    // Next button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" onclick="changeWorkPage(${currentPage + 1})">Suivant</a>`;
    pagination.appendChild(nextLi);
}

function changeWorkPage(page) {
    const totalPages = Math.ceil(allWorkData.length / workItemsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentWorkPage = page;
        displayWorkPage(page);
        window.scrollTo(0, 0);
    }
}

// Initialize work display
document.addEventListener('DOMContentLoaded', function() {
    console.log('Work data length:', allWorkData.length);
    console.log('Work data:', allWorkData);
    
    if (allWorkData && allWorkData.length > 0) {
        displayWorkPage(1);
        document.getElementById('no-work-message').style.display = 'none';
        document.getElementById('work-table-container').style.display = 'block';
    } else {
        console.log('No work data found, showing empty message');
        document.getElementById('no-work-message').style.display = 'block';
        document.getElementById('work-table-container').style.display = 'none';
        document.getElementById('work-pagination').style.display = 'none';
        document.getElementById('work-summary').style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
