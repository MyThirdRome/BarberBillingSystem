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
    } elseif ($action === 'update_gmail_password') {
        $app_password = trim($_POST['app_password'] ?? '');
        
        if (empty($app_password)) {
            // Remove app password file if empty
            $app_password_file = DATA_DIR . '/gmail_app_password.txt';
            if (file_exists($app_password_file)) {
                unlink($app_password_file);
            }
            $message = 'Mot de passe d\'application Gmail supprimé.';
        } else {
            // Save app password
            $app_password_file = DATA_DIR . '/gmail_app_password.txt';
            if (file_put_contents($app_password_file, $app_password)) {
                $message = 'Mot de passe d\'application Gmail configuré avec succès.';
            } else {
                $error = 'Erreur lors de la sauvegarde du mot de passe d\'application.';
            }
        }
    } elseif ($action === 'import_backup') {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['backup_file'];
            $fileName = $uploadedFile['name'];
            $tempPath = $uploadedFile['tmp_name'];
            
            // Validate file type
            if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'zip') {
                $error = 'Veuillez sélectionner un fichier ZIP valide.';
            } else {
                require_once 'includes/backup_functions.php';
                $result = importBackupFromZip($tempPath);
                
                if ($result['success']) {
                    $message = 'Sauvegarde importée avec succès. ' . $result['imported_files'] . ' fichiers restaurés.';
                } else {
                    $error = 'Erreur lors de l\'importation: ' . $result['error'];
                }
            }
        } else {
            $error = 'Aucun fichier sélectionné ou erreur lors du téléchargement.';
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
                <div>
                    <button type="button" class="btn btn-success me-2" onclick="manualBackup()">
                        <i class="fas fa-download me-1"></i>
                        Sauvegarde Manuelle
                    </button>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-upload me-1"></i>
                        Importer Sauvegarde
                    </button>
                </div>
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
            
            <!-- Gmail Configuration -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fab fa-google me-2"></i>
                                Configuration Gmail
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $app_password_file = DATA_DIR . '/gmail_app_password.txt';
                            $has_app_password = file_exists($app_password_file) && !empty(trim(file_get_contents($app_password_file)));
                            ?>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Configuration requise pour les emails</h6>
                                <p class="mb-2">Pour recevoir les emails de sauvegarde, vous devez:</p>
                                <ol class="mb-0">
                                    <li>Activer l'authentification à deux facteurs sur votre compte Gmail</li>
                                    <li>Générer un "mot de passe d'application" depuis les paramètres de sécurité Google</li>
                                    <li>Entrer ce mot de passe ci-dessous (format: xxxx xxxx xxxx xxxx)</li>
                                </ol>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="update_gmail_password">
                                
                                <div class="mb-3">
                                    <label for="app_password" class="form-label">
                                        Mot de passe d'application Gmail
                                        <?php if ($has_app_password): ?>
                                            <span class="badge bg-success ms-2">Configuré</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning ms-2">Non configuré</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="password" class="form-control" id="app_password" name="app_password" 
                                           placeholder="xxxx xxxx xxxx xxxx" maxlength="19">
                                    <div class="form-text">
                                        Laissez vide pour supprimer le mot de passe configuré. 
                                        <a href="https://support.google.com/accounts/answer/185833" target="_blank">
                                            Guide de configuration Google
                                        </a>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-key me-1"></i>
                                    <?php echo $has_app_password ? 'Mettre à jour' : 'Configurer'; ?> le mot de passe
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

            <!-- Email Notifications Log -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-envelope me-2"></i>
                                Notifications Email
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $email_log_file = DATA_DIR . '/email_notifications.json';
                            $email_logs = [];
                            if (file_exists($email_log_file)) {
                                $email_logs = json_decode(file_get_contents($email_log_file), true) ?: [];
                                $email_logs = array_reverse(array_slice($email_logs, -10)); // Last 10 entries
                            }
                            ?>
                            
                            <?php if (empty($email_logs)): ?>
                                <p class="text-muted">Aucune notification email trouvée.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Destinataire</th>
                                                <th>Sujet</th>
                                                <th>Statut</th>
                                                <th>Méthode</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($email_logs as $log): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                                    <td><?php echo htmlspecialchars($log['to']); ?></td>
                                                    <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                                    <td>
                                                        <?php if ($log['status'] === 'sent'): ?>
                                                            <span class="badge bg-success">Envoyé</span>
                                                        <?php elseif ($log['status'] === 'logged'): ?>
                                                            <span class="badge bg-info">Enregistré</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Échec</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="small"><?php echo htmlspecialchars($log['method'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> Les notifications email sont actuellement enregistrées localement. 
                                    Pour recevoir les emails, vous devez configurer Gmail avec un mot de passe d'application au lieu du mot de passe habituel.
                                </div>
                            <?php endif; ?>
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

<!-- Import Backup Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">
                    <i class="fas fa-upload me-2"></i>
                    Importer une Sauvegarde
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import_backup">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention:</strong> L'importation remplacera toutes les données actuelles par celles du fichier de sauvegarde.
                        Une copie de sauvegarde de vos données actuelles sera créée automatiquement.
                    </div>
                    
                    <div class="mb-3">
                        <label for="backup_file" class="form-label">
                            <i class="fas fa-file-archive me-2"></i>
                            Fichier de sauvegarde ZIP
                        </label>
                        <input type="file" class="form-control" id="backup_file" name="backup_file" 
                               accept=".zip" required>
                        <div class="form-text">
                            Sélectionnez un fichier ZIP de sauvegarde créé par ce système.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_import" required>
                            <label class="form-check-label" for="confirm_import">
                                Je comprends que cette action remplacera toutes les données actuelles
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-upload me-1"></i>
                        Importer la Sauvegarde
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>