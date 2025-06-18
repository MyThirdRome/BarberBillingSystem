<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Only admins can access crew management
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: crew_dashboard.php');
    exit;
}

checkPermission('edit');

$crew = loadData('crew');
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $salary_base = floatval($_POST['salary_base'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($name) || empty($position) || empty($username) || empty($password)) {
            $error = 'Le nom, le poste, nom d\'utilisateur et mot de passe sont obligatoires.';
        } else {
            // Check if username already exists
            $users = loadData('users');
            $usernameExists = false;
            foreach ($users as $user) {
                if ($user['username'] === $username) {
                    $usernameExists = true;
                    break;
                }
            }
            
            if ($usernameExists) {
                $error = 'Ce nom d\'utilisateur existe déjà.';
            } else {
                $newMember = [
                    'id' => generateId(),
                    'name' => $name,
                    'position' => $position,
                    'phone' => $phone,
                    'salary_base' => $salary_base,
                    'username' => $username,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Create user account for crew member
                $newUser = [
                    'id' => generateId(),
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'crew',
                    'crew_id' => $newMember['id'],
                    'name' => $name,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $crew[] = $newMember;
                $users[] = $newUser;
                saveData('crew', $crew);
                saveData('users', $users);
                $message = 'Membre d\'équipe ajouté avec succès. Identifiants: ' . $username;
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $salary_base = floatval($_POST['salary_base'] ?? 0);
        
        if (empty($name) || empty($position)) {
            $error = 'Le nom et le poste sont obligatoires.';
        } else {
            foreach ($crew as &$member) {
                if ($member['id'] === $id) {
                    $member['name'] = $name;
                    $member['position'] = $position;
                    $member['phone'] = $phone;
                    $member['salary_base'] = $salary_base;
                    $member['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            
            saveData('crew', $crew);
            $message = 'Membre d\'équipe modifié avec succès.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        $crew = array_filter($crew, function($member) use ($id) {
            return $member['id'] !== $id;
        });
        
        saveData('crew', $crew);
        $message = 'Membre d\'équipe supprimé avec succès.';
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Gestion de l'Équipe</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCrewModal">
                    <i class="fas fa-plus"></i> Ajouter un Membre
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
            
            <div class="card">
                <div class="card-body">
                    <?php if (empty($crew)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucun membre d'équipe</h5>
                            <p class="text-muted">Commencez par ajouter des membres à votre équipe.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Poste</th>
                                        <th>Téléphone</th>
                                        <th>Salaire de Base</th>
                                        <th>Date d'Ajout</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($crew as $member): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($member['name']) ?></td>
                                            <td><?= htmlspecialchars($member['position']) ?></td>
                                            <td><?= htmlspecialchars($member['phone']) ?></td>
                                            <td><?= number_format($member['salary_base'], 2) ?> TND</td>
                                            <td><?= date('d/m/Y', strtotime($member['created_at'])) ?></td>
                                            <td>
                                                <a href="crew_details.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i> Détails
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editCrew('<?= $member['id'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteCrew('<?= $member['id'] ?>', '<?= htmlspecialchars($member['name']) ?>')">
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

<!-- Add Crew Modal -->
<div class="modal fade" id="addCrewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Membre d'Équipe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nom Complet *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="position" class="form-label">Poste *</label>
                        <input type="text" class="form-control" id="position" name="position" 
                               placeholder="Ex: Coiffeur, Barbier, Apprenti" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Téléphone</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="salary_base" class="form-label">Salaire de Base (TND)</label>
                        <input type="number" class="form-control" id="salary_base" name="salary_base" 
                               step="0.01" min="0">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                                <small class="text-muted">Pour se connecter à la plateforme</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generateRandomPassword()">
                                        <i class="fas fa-random"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
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

<!-- Edit Crew Modal -->
<div class="modal fade" id="editCrewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le Membre d'Équipe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nom Complet *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_position" class="form-label">Poste *</label>
                        <input type="text" class="form-control" id="edit_position" name="position" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Téléphone</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_salary_base" class="form-label">Salaire de Base (TND)</label>
                        <input type="number" class="form-control" id="edit_salary_base" name="salary_base" 
                               step="0.01" min="0">
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

<!-- Delete Crew Modal -->
<div class="modal fade" id="deleteCrewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la Suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer <strong id="delete_name"></strong> de l'équipe ?</p>
                <p class="text-danger">Cette action est irréversible.</p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store crew data for JavaScript access
const crewData = <?= json_encode($crew) ?>;

function editCrew(id) {
    const member = crewData.find(c => c.id === id);
    if (member) {
        document.getElementById('edit_id').value = member.id;
        document.getElementById('edit_name').value = member.name;
        document.getElementById('edit_position').value = member.position;
        document.getElementById('edit_phone').value = member.phone || '';
        document.getElementById('edit_salary_base').value = member.salary_base || 0;
        
        new bootstrap.Modal(document.getElementById('editCrewModal')).show();
    }
}

function deleteCrew(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    
    new bootstrap.Modal(document.getElementById('deleteCrewModal')).show();
}

function generateRandomPassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let password = '';
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password').value = password;
    document.getElementById('password').type = 'text';
    setTimeout(() => {
        document.getElementById('password').type = 'password';
    }, 2000);
}
</script>

<?php include 'includes/footer.php'; ?>
