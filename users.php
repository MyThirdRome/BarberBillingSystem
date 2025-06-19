<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Only admins can manage users
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$users = loadData('users');
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'viewer';
        $permissions = $_POST['permissions'] ?? [];
        
        if (empty($username) || empty($password)) {
            $error = 'Le nom d\'utilisateur et le mot de passe sont obligatoires.';
        } elseif ($password !== $confirm_password) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($password) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            // Check if username already exists
            $existingUser = array_filter($users, function($u) use ($username) {
                return $u['username'] === $username;
            });
            
            if (!empty($existingUser)) {
                $error = 'Ce nom d\'utilisateur existe déjà.';
            } else {
                $newUser = [
                    'id' => generateId(),
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'permissions' => $permissions,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $_SESSION['user_id']
                ];
                
                $users[] = $newUser;
                saveData('users', $users);
                $message = 'Utilisateur ajouté avec succès.';
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? 'viewer';
        $permissions = $_POST['permissions'] ?? [];
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($username)) {
            $error = 'Le nom d\'utilisateur est obligatoire.';
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (!empty($new_password) && strlen($new_password) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            foreach ($users as &$user) {
                if ($user['id'] === $id) {
                    // Find the original user to preserve their crew status
                    $originalUser = null;
                    foreach ($users as $origUser) {
                        if ($origUser['id'] === $id) {
                            $originalUser = $origUser;
                            break;
                        }
                    }
                    
                    $user['username'] = $username;
                    
                    // If user has crew_id, keep them as crew member regardless of form selection
                    if (!empty($originalUser['crew_id'])) {
                        $user['role'] = 'crew';
                    } else {
                        $user['role'] = $role;
                    }
                    
                    $user['permissions'] = $permissions;
                    if (!empty($new_password)) {
                        $user['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                    $user['updated_at'] = date('Y-m-d H:i:s');
                    $user['updated_by'] = $_SESSION['user_id'];
                    break;
                }
            }
            
            saveData('users', $users);
            $message = 'Utilisateur modifié avec succès.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        // Don't allow deleting own account
        if ($id === $_SESSION['user_id']) {
            $error = 'Vous ne pouvez pas supprimer votre propre compte.';
        } else {
            $users = array_filter($users, function($user) use ($id) {
                return $user['id'] !== $id;
            });
            
            saveData('users', $users);
            $message = 'Utilisateur supprimé avec succès.';
        }
    }
}

$permissionOptions = [
    'view' => 'Consultation',
    'edit' => 'Modification',
    'admin' => 'Administration'
];

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Gestion des Utilisateurs</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Ajouter un Utilisateur
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
            
            <?php if (isset($_GET['returned']) && $_GET['returned'] == 1): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-arrow-left me-2"></i>
                    Vous êtes revenu à votre session administrateur.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucun utilisateur</h5>
                            <p class="text-muted">Commencez par ajouter des utilisateurs au système.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom d'utilisateur</th>
                                        <th>Rôle</th>
                                        <th>Permissions</th>
                                        <th>Date de Création</th>
                                        <th>Dernière Connexion</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr <?= $user['id'] === $_SESSION['user_id'] ? 'class="table-info"' : '' ?>>
                                            <td>
                                                <?= htmlspecialchars($user['username']) ?>
                                                <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                                    <span class="badge bg-primary">Vous</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $roleLabels = [
                                                    'admin' => ['label' => 'Administrateur', 'color' => 'danger'],
                                                    'crew' => ['label' => 'Équipe', 'color' => 'success'],
                                                    'viewer' => ['label' => 'Consultation', 'color' => 'info'],
                                                    'user' => ['label' => 'Utilisateur', 'color' => 'secondary']
                                                ];
                                                $roleInfo = $roleLabels[$user['role']] ?? ['label' => ucfirst($user['role']), 'color' => 'secondary'];
                                                ?>
                                                <span class="badge bg-<?= $roleInfo['color'] ?>">
                                                    <?= $roleInfo['label'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($user['permissions'])): ?>
                                                    <?php foreach ($user['permissions'] as $perm): ?>
                                                        <span class="badge bg-light text-dark me-1">
                                                            <?= $permissionOptions[$perm] ?? $perm ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Aucune</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <?= isset($user['last_login']) ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais' ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editUser('<?= $user['id'] ?>'); return false;">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                                            onclick="loginAsUser('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username']) ?>')"
                                                            title="Se connecter en tant que <?= htmlspecialchars($user['username']) ?>">
                                                        <i class="fas fa-sign-in-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteUser('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username']) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe *</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               minlength="6" required>
                        <div class="form-text">Minimum 6 caractères</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               minlength="6" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle</label>
                        <select class="form-control" id="role" name="role">
                            <option value="user">Utilisateur</option>
                            <option value="crew">Équipe</option>
                            <option value="viewer">Consultation</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="perm_view" name="permissions[]" value="view" checked>
                            <label class="form-check-label" for="perm_view">Consultation</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="perm_edit" name="permissions[]" value="edit">
                            <label class="form-check-label" for="perm_edit">Modification</label>
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

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier l'Utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Nom d'utilisateur *</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Rôle</label>
                        <select class="form-control" id="edit_role" name="role">
                            <option value="user">Utilisateur</option>
                            <option value="crew">Équipe</option>
                            <option value="viewer">Consultation</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_perm_view" name="permissions[]" value="view">
                            <label class="form-check-label" for="edit_perm_view">Consultation</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_perm_edit" name="permissions[]" value="edit">
                            <label class="form-check-label" for="edit_perm_edit">Modification</label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nouveau mot de passe (optionnel)</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                        <div class="form-text">Laissez vide pour conserver le mot de passe actuel</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" class="form-control" id="edit_confirm_password" name="confirm_password" minlength="6">
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

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la Suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="delete_username"></strong> ?</p>
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
// Store user data for JavaScript access
const userData = <?= json_encode($users, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
console.log('userData type:', typeof userData, 'value:', userData);

window.editUser = function(id) {
    try {
        console.log('editUser called with ID:', id);
        
        // Ensure userData is an array
        const userArray = Array.isArray(userData) ? userData : Object.values(userData);
        console.log('userArray:', userArray);
        
        // Find user in data
        const user = userArray.find(u => u.id === id);
        if (!user) {
            alert('Utilisateur non trouvé');
            return;
        }
        
        console.log('Found user:', user);
        
        // Fill form fields
        document.getElementById('edit_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_role').value = user.role || 'viewer';
        
        // Clear checkboxes first
        document.getElementById('edit_perm_view').checked = false;
        document.getElementById('edit_perm_edit').checked = false;
        
        // Set permissions if they exist
        if (user.permissions && Array.isArray(user.permissions)) {
            user.permissions.forEach(perm => {
                const checkbox = document.getElementById('edit_perm_' + perm);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
        
        // Clear password fields
        document.getElementById('new_password').value = '';
        document.getElementById('edit_confirm_password').value = '';
        
        // Show modal
        const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        editModal.show();
        
    } catch (error) {
        console.error('Error in editUser:', error);
        alert('Erreur lors de l\'ouverture du formulaire d\'édition');
    }
};

window.loginAsUser = function(userId, username) {
    if (confirm(`Voulez-vous vous connecter en tant que ${username} dans un nouvel onglet ?`)) {
        // Create a form to submit the login request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin_login_as.php';
        form.target = '_blank';
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'csrf_token';
        tokenInput.value = 'skip_csrf';
        
        form.appendChild(userIdInput);
        form.appendChild(tokenInput);
        document.body.appendChild(form);
        
        form.submit();
        document.body.removeChild(form);
    }
};

function deleteUser(id, username) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_username').textContent = username;
    
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}

// Test modal functionality
function testModal() {
    console.log('Testing modal...');
    const modalEl = document.getElementById('editUserModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        console.log('Modal shown successfully');
    } else {
        console.error('Modal element not found');
    }
}

// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up event listeners...');
    
    // Test if Bootstrap is loaded  
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
    } else {
        console.log('Bootstrap is available');
    }
    
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const password = form.querySelector('input[name="password"], input[name="new_password"]');
            const confirm = form.querySelector('input[name="confirm_password"]');
            
            if (password && confirm && password.value && password.value !== confirm.value) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
