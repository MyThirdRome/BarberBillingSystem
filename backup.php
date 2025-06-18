<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/backup_functions.php';

// Only admins can access backup management
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: crew_dashboard.php');
    exit;
}

// Load backup configuration
$configFile = DATA_DIR . '/backup_config.json';
if (!file_exists($configFile)) {
    $backupConfig = [
        'email' => 'helloborislav@gmail.com',
        'enabled' => true,
        'last_backup' => null,
        'backup_count' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($configFile, json_encode($backupConfig, JSON_PRETTY_PRINT));
} else {
    $backupConfig = json_decode(file_get_contents($configFile), true);
}

$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_config') {
        $email = trim($_POST['email'] ?? '');
        $enabled = isset($_POST['enabled']) ? true : false;
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Veuillez saisir une adresse email valide.';
        } else {
            $backupConfig['email'] = $email;
            $backupConfig['enabled'] = $enabled;
            $backupConfig['updated_at'] = date('Y-m-d H:i:s');
            $backupConfig['updated_by'] = $_SESSION['username'];
            
            file_put_contents($configFile, json_encode($backupConfig, JSON_PRETTY_PRINT));
            $message = 'Configuration de sauvegarde mise à jour avec succès.';
        }
    } elseif ($action === 'manual_backup') {
        $result = createBackupSystem(true);
        if ($result['success']) {
            $message = 'Sauvegarde manuelle créée et envoyée avec succès.';
            // Reload config to get updated backup info
            $backupConfig = json_decode(file_get_contents($configFile), true);
        } else {
            $error = 'Erreur lors de la création de la sauvegarde: ' . $result['error'];
        }
    }
}

$page_title = 'Sauvegarde';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Système de Sauvegarde</h1>
                <button type="button" class="btn btn-success" onclick="manualBackup()">
                    <i class="fas fa-download me-1"></i>
                    Sauvegarde Manuelle
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
            
            <div class="row">
                <!-- Backup Status Card -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Statut de la Sauvegarde
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Statut:</label>
                                        <div>
                                            <?php if ($backupConfig['enabled']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Activé
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-pause me-1"></i>Désactivé
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Email de destination:</label>
                                        <div class="fw-bold"><?= htmlspecialchars($backupConfig['email']) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Dernière sauvegarde:</label>
                                        <div class="fw-bold">
                                            <?php if ($backupConfig['last_backup']): ?>
                                                <?= date('d/m/Y à H:i', strtotime($backupConfig['last_backup'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Aucune sauvegarde</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Total sauvegardes:</label>
                                        <div class="fw-bold"><?= number_format($backupConfig['backup_count']) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Sauvegarde automatique:</strong> Toutes les heures
                                <br>
                                <small>La prochaine sauvegarde automatique aura lieu dans 
                                <?php 
                                $nextHour = date('H:i', strtotime('+1 hour', strtotime(date('Y-m-d H:00:00'))));
                                echo $nextHour;
                                ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Configuration Card -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-cog me-2"></i>
                                Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_config">
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email de destination *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($backupConfig['email']) ?>" required>
                                    <div class="form-text">
                                        L'adresse email où seront envoyées les sauvegardes
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" 
                                               <?= $backupConfig['enabled'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enabled">
                                            Activer les sauvegardes automatiques
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Si désactivé, seules les sauvegardes manuelles seront possibles
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Sauvegarder la Configuration
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Backup Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info me-2"></i>
                                Informations sur la Sauvegarde
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary">Contenu de la sauvegarde:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-file-alt text-muted me-2"></i>Données des équipes</li>
                                        <li><i class="fas fa-file-alt text-muted me-2"></i>Données des prestations</li>
                                        <li><i class="fas fa-file-alt text-muted me-2"></i>Données des charges</li>
                                        <li><i class="fas fa-file-alt text-muted me-2"></i>Données des avances</li>
                                        <li><i class="fas fa-file-alt text-muted me-2"></i>Données des paiements</li>
                                        <li><i class="fas fa-file-alt text-muted me-2"></i>Données des utilisateurs</li>
                                        <li><i class="fas fa-file-alt text-muted me-2"></i>Liste de prix</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">Format de sauvegarde:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-archive text-muted me-2"></i>Archive ZIP compressée</li>
                                        <li><i class="fas fa-calendar text-muted me-2"></i>Nom avec date et heure</li>
                                        <li><i class="fas fa-envelope text-muted me-2"></i>Envoyée par email</li>
                                        <li><i class="fas fa-shield-alt text-muted me-2"></i>Données sécurisées</li>
                                    </ul>
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
function manualBackup() {
    if (confirm('Créer et envoyer une sauvegarde manuelle maintenant ?')) {
        // Show loading state
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Création en cours...';
        btn.disabled = true;
        
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'manual_backup';
        
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>