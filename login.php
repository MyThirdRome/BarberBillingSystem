<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    error_log("LOGIN ATTEMPT - Username: " . $username . ", Password length: " . strlen($password));
    
    if (empty($username) || empty($password)) {
        error_log("LOGIN FAILED - Empty username or password");
        $error = 'Veuillez saisir votre nom d\'utilisateur et mot de passe.';
    } else {
        $users = loadData('users');
        
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                error_log("FOUND USER: " . $username . " - Testing password");
                if (password_verify($password, $user['password'])) {
                    error_log("LOGIN SUCCESS for: " . $username . " (role: " . $user['role'] . ")");
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user;
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['permissions'] = $user['permissions'] ?? [];
                
                // For users with crew_id (crew, viewer, user roles), ensure crew_id is properly set
                if (!empty($user['crew_id']) || $user['role'] === 'crew') {
                    // If crew_id is missing from user data, find it from crew records
                    if (empty($user['crew_id'])) {
                        $crew = loadData('crew');
                        foreach ($crew as $member) {
                            if ($member['username'] === $user['username']) {
                                $_SESSION['user']['crew_id'] = $member['id'];
                                $_SESSION['user']['name'] = $member['name'];
                                
                                // Update user record with missing crew_id
                                foreach ($users as &$u) {
                                    if ($u['id'] === $user['id']) {
                                        $u['crew_id'] = $member['id'];
                                        $u['name'] = $member['name'];
                                        break;
                                    }
                                }
                                saveData('users', $users);
                                break;
                            }
                        }
                    }
                    error_log("REDIRECTING to crew_dashboard.php for: " . $username);
                    header('Location: crew_dashboard.php');
                } else {
                    error_log("REDIRECTING to dashboard.php for: " . $username);
                    header('Location: dashboard.php');
                }
                exit;
                } else {
                    error_log("PASSWORD FAILED for: " . $username);
                }
            }
        }
        
        $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion Salon de Coiffure</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h1 class="h3 mb-3 fw-normal">Gestion Salon</h1>
                            <p class="text-muted">Connectez-vous à votre compte</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
