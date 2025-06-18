<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Only admins can access price list management
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: crew_dashboard.php');
    exit;
}

$priceList = loadData('price_list');
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || $price <= 0) {
            $error = 'Le nom de la prestation et le prix sont obligatoires.';
        } else {
            $newService = [
                'id' => generateId(),
                'name' => $name,
                'price' => $price,
                'category' => $category,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['username']
            ];
            
            $priceList[] = $newService;
            saveData('price_list', $priceList);
            $message = 'Prestation ajoutée avec succès.';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || $price <= 0) {
            $error = 'Le nom de la prestation et le prix sont obligatoires.';
        } else {
            foreach ($priceList as &$service) {
                if ($service['id'] === $id) {
                    $service['name'] = $name;
                    $service['price'] = $price;
                    $service['category'] = $category;
                    $service['description'] = $description;
                    $service['updated_at'] = date('Y-m-d H:i:s');
                    $service['updated_by'] = $_SESSION['username'];
                    break;
                }
            }
            
            saveData('price_list', $priceList);
            $message = 'Prestation modifiée avec succès.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        $priceList = array_filter($priceList, function($service) use ($id) {
            return $service['id'] !== $id;
        });
        
        saveData('price_list', $priceList);
        $message = 'Prestation supprimée avec succès.';
    }
}

$page_title = 'Liste de Prix';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Liste de Prix</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
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
                    <?php if (empty($priceList)): ?>
                        <p class="text-muted text-center py-4">Aucune prestation dans la liste de prix.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prix</th>
                                        <th>Catégorie</th>
                                        <th>Description</th>
                                        <th>Créé le</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Sort by category then by name
                                    usort($priceList, function($a, $b) {
                                        $catCompare = strcmp($a['category'] ?? '', $b['category'] ?? '');
                                        if ($catCompare === 0) {
                                            return strcmp($a['name'], $b['name']);
                                        }
                                        return $catCompare;
                                    });
                                    
                                    foreach ($priceList as $service): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($service['name']) ?></td>
                                            <td class="text-success fw-bold"><?= number_format($service['price'], 3) ?> TND</td>
                                            <td>
                                                <?php if (!empty($service['category'])): ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($service['category']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($service['description'] ?? '') ?></td>
                                            <td class="text-muted small"><?= date('d/m/Y', strtotime($service['created_at'])) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                                        onclick="editService('<?= $service['id'] ?>', '<?= htmlspecialchars($service['name'], ENT_QUOTES) ?>', '<?= $service['price'] ?>', '<?= htmlspecialchars($service['category'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($service['description'] ?? '', ENT_QUOTES) ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteService('<?= $service['id'] ?>', '<?= htmlspecialchars($service['name'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
    </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une Prestation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nom de la Prestation *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Prix (TND) *</label>
                        <input type="number" class="form-control" id="price" name="price" step="0.001" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">Catégorie</label>
                        <input type="text" class="form-control" id="category" name="category" 
                               placeholder="Ex: Coupe, Coloration, Soin...">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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

<!-- Edit Service Modal -->
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la Prestation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nom de la Prestation *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">Prix (TND) *</label>
                        <input type="number" class="form-control" id="edit_price" name="price" step="0.001" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category" class="form-label">Catégorie</label>
                        <input type="text" class="form-control" id="edit_category" name="category" 
                               placeholder="Ex: Coupe, Coloration, Soin...">
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la Suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer cette prestation ?</p>
                    <p class="fw-bold text-danger" id="delete_service_name"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editService(id, name, price, category, description) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_description').value = description;
    
    var editModal = new bootstrap.Modal(document.getElementById('editServiceModal'));
    editModal.show();
}

function deleteService(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_service_name').textContent = name;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteServiceModal'));
    deleteModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>