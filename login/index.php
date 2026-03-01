<?php
/**
 * Page de connexion Fronote.
 * CSRF vérifié, rate limiting, remember me.
 */
require_once __DIR__ . '/../API/core.php';

// UserService centralisé (remember-me, rate limiting)
$userService = app()->make('API\Services\UserService');

// Si déjà connecté → accueil
if (isLoggedIn()) {
    redirect('accueil/accueil.php');
}

$error   = '';
$success = '';
$lastUsername = $_SESSION['last_username'] ?? '';
unset($_SESSION['last_username']);

// Messages flash
if (isset($_SESSION['success_message'])) { $success = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message']))   { $error   = $_SESSION['error_message'];   unset($_SESSION['error_message']); }

// Remember-me : tentative de restauration automatique
if (!empty($_COOKIE['remember_token']) && !isLoggedIn()) {
    $remembered = $userService->validateRememberToken($_COOKIE['remember_token']);
    if ($remembered) {
        redirect('accueil/accueil.php');
    }
}

// --- Traitement du formulaire ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // 1) Vérification CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Jeton de sécurité invalide. Veuillez recharger la page.';
    } else {
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';
        $userType   = $_POST['user_type'] ?? '';
        $rememberMe = !empty($_POST['remember_me']);
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (empty($username) || empty($password) || empty($userType)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            // 2) Rate limiting
            $waitMinutes = $userService->checkLoginRateLimit($ip);
            if ($waitMinutes > 0) {
                $error = "Trop de tentatives. Réessayez dans {$waitMinutes} minute(s).";
            } else {
                // 3) Tentative d'authentification
                $profilesToTry = ($userType === 'vie_scolaire')
                    ? ['administrateur', 'vie_scolaire']
                    : [$userType];

                $loginResult = null;
                foreach ($profilesToTry as $type) {
                    $attempt = login($type, $username, $password);
                    if ($attempt) {
                        $loginResult = $attempt;
                        break;
                    }
                }

                if ($loginResult) {
                    // 4) Remember Me
                    if ($rememberMe) {
                        $user = getCurrentUser();
                        if ($user) {
                            $userService->createRememberToken($user['id']);
                        }
                    }

                    // Nettoyage périodique
                    $userService->cleanOldAttempts();

                    redirect('accueil/accueil.php');
                    exit;
                } else {
                    // Enregistrer la tentative échouée
                    $userService->recordFailedAttempt($ip);
                    $error = 'Identifiant ou mot de passe incorrect.';
                    $_SESSION['last_username'] = $username;
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - FRONOTE</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="app-logo">P</div>
            <h1 class="app-title">FRONOTE</h1>
            <p class="app-subtitle">Espace de connexion</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert" aria-live="assertive">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="status" aria-live="polite">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="login" value="1">

            <!-- Sélecteur de profil -->
            <div class="profile-selector" role="radiogroup" aria-label="Type de profil">
                <input type="radio" id="eleve" name="user_type" value="eleve" required>
                <label for="eleve" class="profile-option">
                    <div class="profile-icon"><i class="fas fa-user-graduate" aria-hidden="true"></i></div>
                    <div class="profile-label">Élève</div>
                </label>

                <input type="radio" id="parent" name="user_type" value="parent">
                <label for="parent" class="profile-option">
                    <div class="profile-icon"><i class="fas fa-users" aria-hidden="true"></i></div>
                    <div class="profile-label">Parent</div>
                </label>

                <input type="radio" id="professeur" name="user_type" value="professeur">
                <label for="professeur" class="profile-option">
                    <div class="profile-icon"><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i></div>
                    <div class="profile-label">Professeur</div>
                </label>

                <input type="radio" id="personnel" name="user_type" value="vie_scolaire">
                <label for="personnel" class="profile-option">
                    <div class="profile-icon"><i class="fas fa-user-tie" aria-hidden="true"></i></div>
                    <div class="profile-label">Personnel</div>
                </label>
            </div>

            <div class="form-group">
                <label for="username" class="required-field">Identifiant</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-user" aria-hidden="true"></i>
                    <input type="text" id="username" name="username" class="form-control input-with-icon"
                           value="<?= htmlspecialchars($lastUsername) ?>" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="required-field">Mot de passe</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" class="form-control input-with-icon"
                           required autocomplete="current-password">
                    <button type="button" class="visibility-toggle" aria-label="Afficher ou masquer le mot de passe">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-group">
                    <input type="checkbox" name="remember_me" id="remember_me">
                    <span>Se souvenir de moi</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span class="btn-text"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Se connecter</span>
                </button>
            </div>

            <div class="help-links">
                <a href="reset_password.php">Mot de passe oublié ?</a>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        var toggle = document.querySelector('.visibility-toggle');
        var pwInput = document.getElementById('password');
        if (toggle && pwInput) {
            toggle.addEventListener('click', function() {
                var type = pwInput.type === 'password' ? 'text' : 'password';
                pwInput.type = type;
                var icon = toggle.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }

        // Auto-focus password if username filled
        var usernameInput = document.getElementById('username');
        if (usernameInput && usernameInput.value.trim()) {
            pwInput.focus();
        }

        // Default profile selection
        var radios = document.querySelectorAll('input[name="user_type"]');
        if (radios.length && !Array.from(radios).some(function(r) { return r.checked; })) {
            document.getElementById('eleve').checked = true;
        }

        // Loading state on submit
        var form = document.getElementById('loginForm');
        var btn = document.getElementById('loginBtn');
        if (form && btn) {
            form.addEventListener('submit', function(e) {
                var username = document.getElementById('username').value.trim();
                var password = document.getElementById('password').value;
                var userType = document.querySelector('input[name="user_type"]:checked');
                if (!username || !password || !userType) {
                    e.preventDefault();
                    return;
                }
                btn.classList.add('btn-loading');
                btn.disabled = true;
            });
        }
    });
    </script>
</body>
</html>
