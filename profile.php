<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$users = loadData('users');
$message = '';
$error = '';

// Find current user
$currentUser = null;
foreach ($users as $user) {
    if ($user['id'] === $_SESSION['user_id']) {
        $currentUser = $user;
        break;
    }
}

if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// Handle password change
if ($_POST) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!password_verify($current_password, $currentUser['password'])) {
        $error = 'Le mot de passe actuel est incorrect.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Les nouveaux mots de passe ne correspondent pas.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } else {
        // Update password
        foreach ($users as &$user) {
            if ($user['id'] === $_SESSION['user_id']) {
                $user['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                $user['password_changed_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        
        saveData('users', $users);
        $message = 'Mot de passe modifié avec succès.';
        
        // Update current user data
        $currentUser['password_changed_at'] = date('Y-m-d H:i:s');
    }
}

// Calculate user statistics
$work = loadData('work');
$payments = loadData('payments');
$advances = loadData('advances');

// User activity stats (if they have a crew member associated)
$userStats = [
    'total_logins' => $currentUser['login_count'] ?? 0,
    'last_login' => $currentUser['last_login'] ?? 'Jamais',
    'account_created' => $currentUser['created_at'],
    'password_changed' => $currentUser['password_changed_at'] ?? 'Jamais'
];

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Mon Profil</h1>
            
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
            
            <div class="row">
                <!-- Profile Information -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informations du Compte</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Nom d'utilisateur:</strong></div>
                                <div class="col-sm-8"><?= htmlspecialchars($currentUser['username']) ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Rôle:</strong></div>
                                <div class="col-sm-8">
                                    <span class="badge bg-<?= $currentUser['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                        <?= $currentUser['role'] === 'admin' ? 'Administrateur' : 'Utilisateur' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Permissions:</strong></div>
                                <div class="col-sm-8">
                                    <?php if (!empty($currentUser['permissions'])): ?>
                                        <?php 
                                        $permissionLabels = [
                                            'view' => 'Consultation',
                                            'edit' => 'Modification',
                                            'admin' => 'Administration'
                                        ];
                                        foreach ($currentUser['permissions'] as $perm): ?>
                                            <span class="badge bg-light text-dark me-1">
                                                <?= $permissionLabels[$perm] ?? $perm ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune permission spécifique</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Compte créé:</strong></div>
                                <div class="col-sm-8"><?= date('d/m/Y H:i', strtotime($userStats['account_created'])) ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Dernière connexion:</strong></div>
                                <div class="col-sm-8">
                                    <?= $userStats['last_login'] !== 'Jamais' ? date('d/m/Y H:i', strtotime($userStats['last_login'])) : 'Jamais' ?>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-sm-4"><strong>Mot de passe modifié:</strong></div>
                                <div class="col-sm-8">
                                    <?= $userStats['password_changed'] !== 'Jamais' ? date('d/m/Y H:i', strtotime($userStats['password_changed'])) : 'Jamais' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Changer le Mot de Passe</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Mot de passe actuel *</label>
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe *</label>
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" minlength="6" required>
                                    <div class="form-text">Minimum 6 caractères</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe *</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" minlength="6" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Changer le Mot de Passe
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Security Tips -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Conseils de Sécurité</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-shield-alt text-success me-3 mt-1"></i>
                                        <div>
                                            <h6>Mot de passe fort</h6>
                                            <p class="text-muted small">Utilisez au moins 8 caractères avec des lettres, chiffres et symboles.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-clock text-warning me-3 mt-1"></i>
                                        <div>
                                            <h6>Changement régulier</h6>
                                            <p class="text-muted small">Changez votre mot de passe tous les 3-6 mois.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-user-secret text-info me-3 mt-1"></i>
                                        <div>
                                            <h6>Confidentialité</h6>
                                            <p class="text-muted small">Ne partagez jamais vos identifiants avec d'autres personnes.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    
    form.addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Les nouveaux mots de passe ne correspondent pas.');
            return false;
        }
    });
    
    // Real-time password match validation
    const newPasswordField = document.getElementById('new_password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    function validatePasswordMatch() {
        if (confirmPasswordField.value && newPasswordField.value !== confirmPasswordField.value) {
            confirmPasswordField.setCustomValidity('Les mots de passe ne correspondent pas');
        } else {
            confirmPasswordField.setCustomValidity('');
        }
    }
    
    newPasswordField.addEventListener('input', validatePasswordMatch);
    confirmPasswordField.addEventListener('input', validatePasswordMatch);
});
</script>

<?php include 'includes/footer.php'; ?>
