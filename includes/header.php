<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✂️</text></svg>">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-cut me-2"></i>
                <?= APP_NAME ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php
                    global $navigation;
                    $current_page = basename($_SERVER['PHP_SELF']);
                    
                    foreach ($navigation as $key => $nav_item):
                        // Check if user has permission to see this menu item
                        if ($nav_item['permission'] === 'admin' && $_SESSION['role'] !== 'admin') {
                            continue;
                        }
                        
                        if ($nav_item['permission'] === 'edit' && !hasPermission('edit') && $_SESSION['role'] !== 'admin') {
                            continue;
                        }
                        
                        $active_class = (basename($nav_item['url']) === $current_page) ? 'active' : '';
                    ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $active_class ?>" href="<?= $nav_item['url'] ?>">
                                <i class="<?= $nav_item['icon'] ?> me-1"></i>
                                <?= $nav_item['title'] ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?= htmlspecialchars($_SESSION['username']) ?>
                            <span class="badge bg-<?= $_SESSION['role'] === 'admin' ? 'danger' : 'secondary' ?> ms-1">
                                <?= $_SESSION['role'] === 'admin' ? 'Admin' : 'User' ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user-edit me-2"></i>Mon Profil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <main class="main-content">
        <?php
        // Show system alerts if any
        if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
            <div class="container-fluid mt-3">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-clock me-2"></i>
                    Votre session a expiré. Veuillez vous reconnecter.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        <?php endif; ?>
        
        <?php
        // Show maintenance message if needed
        if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE): ?>
            <div class="container-fluid mt-3">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-tools me-2"></i>
                    Le système est en maintenance. Certaines fonctionnalités peuvent être indisponibles.
                </div>
            </div>
        <?php endif; ?>
