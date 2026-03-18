<?php
/**
 * Page de connexion Fronote.
 * Login unifié : aucun sélecteur de profil requis.
 * Flux : credentials → [choix profil si ambiguïté] → [2FA si activé] → accueil
 */
require_once __DIR__ . '/../API/core.php';

// Security headers
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; font-src cdnjs.cloudflare.com; img-src 'self' data:;");
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

$userService = app()->make('API\Services\UserService');
$auth        = app('auth');

// Déjà connecté → accueil
if (isLoggedIn()) {
    redirect('accueil/accueil.php');
}

$error        = '';
$success      = '';
$ambiguous    = []; // Plusieurs comptes pour le même identifiant
$lastUsername = $_SESSION['last_username'] ?? '';
unset($_SESSION['last_username']);

// Messages flash
if (isset($_SESSION['success_message'])) { $success = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message']))   { $error   = $_SESSION['error_message'];   unset($_SESSION['error_message']); }

// Remember-me : tentative de restauration automatique
if (!empty($_COOKIE['remember_token'])) {
    $remembered = $userService->validateRememberToken($_COOKIE['remember_token']);
    if ($remembered) {
        $auth->loginUser($remembered);
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
        $rememberMe = !empty($_POST['remember_me']);
        // Type imposé si l'utilisateur choisit parmi plusieurs comptes ambigus
        $forcedType = $_POST['forced_type'] ?? null;
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (empty($username) || empty($password)) {
            $error = 'Veuillez remplir votre identifiant et votre mot de passe.';
        } else {
            // 2) Rate limiting progressif
            $waitMinutes = $userService->checkLoginRateLimit($ip);
            if ($waitMinutes > 0) {
                $error = "Trop de tentatives. Réessayez dans {$waitMinutes} minute(s).";
            } else {
                // 3) Recherche du compte — type imposé ou multi-type
                $credentials = [
                    'login'    => $username,
                    'password' => $password,
                ];
                if ($forcedType) {
                    $credentials['type'] = $forcedType;
                }

                $result = $auth->attemptAndGetUser($credentials);

                if ($result === null) {
                    // Aucun compte trouvé
                    $userService->recordFailedAttempt($ip);
                    $error = 'Identifiant ou mot de passe incorrect.';
                    $_SESSION['last_username'] = $username;

                } elseif (is_array($result) && isset($result[0]) && isset($result[0]['type'])) {
                    // Plusieurs comptes — montrer un sélecteur de profil
                    $ambiguous = $result;
                    // Transmettre le username pour le re-submit
                    $_SESSION['last_username'] = $username;

                } else {
                    // Un seul compte valide → vérifier 2FA
                    $user = $result;

                    $twoFactor   = new \API\Services\TwoFactorService(getPDO());
                    $twoFAActive = $twoFactor->isEnabled((int)$user['id'], $user['type']);

                    if ($twoFAActive) {
                        // Stocker l'état pending 2FA et rediriger
                        $_SESSION['pending_2fa'] = [
                            'user_id'    => $user['id'],
                            'user_type'  => $user['type'],
                            'remember_me' => $rememberMe,
                        ];
                        redirect('login/verify_2fa.php');
                    } else {
                        // Pas de 2FA → créer la session directement
                        $auth->loginUser($user);

                        if ($rememberMe) {
                            $userService->createRememberToken((int)$user['id'], $user['type']);
                        }

                        // Forcer le changement de mot de passe si jamais changé
                        $fullUser = $userService->findById((int)$user['id'], $user['type']);
                        if ($fullUser && empty($fullUser['password_changed_at'])) {
                            $_SESSION['force_password_change'] = true;
                            $_SESSION['reset_user_id']         = $user['id'];
                            $_SESSION['reset_code']            = 'force_change';
                            $_SESSION['reset_username']        = $user['identifiant'] ?? $username;
                            redirect('login/change_password.php');
                        }

                        $userService->cleanOldAttempts();
                        redirect('accueil/accueil.php');
                    }
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();

$profilLabels = [
    'administrateur' => ['label' => 'Administrateur', 'icon' => 'fa-user-shield'],
    'vie_scolaire'   => ['label' => 'Vie scolaire',   'icon' => 'fa-user-tie'],
    'professeur'     => ['label' => 'Professeur',     'icon' => 'fa-chalkboard-teacher'],
    'eleve'          => ['label' => 'Élève',           'icon' => 'fa-user-graduate'],
    'parent'         => ['label' => 'Parent',          'icon' => 'fa-users'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - FRONOTE</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
    // Appliquer le thème système immédiatement
    (function() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    })();
    </script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="app-logo">F</div>
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

        <?php if (!empty($ambiguous)): ?>
            <!-- Plusieurs comptes pour le même identifiant → choix du profil -->
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                <div>Plusieurs profils correspondent à cet identifiant. Veuillez choisir le vôtre.</div>
            </div>
            <form method="post" action="" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="login" value="1">
                <input type="hidden" name="username" value="<?= htmlspecialchars($lastUsername) ?>">
                <input type="hidden" name="password" value="">
                <div class="profile-selector" role="radiogroup" aria-label="Choisir votre profil">
                    <?php foreach ($ambiguous as $candidate): ?>
                        <?php $lbl = $profilLabels[$candidate['type']] ?? ['label' => ucfirst($candidate['type']), 'icon' => 'fa-user']; ?>
                        <input type="radio" id="type_<?= htmlspecialchars($candidate['type']) ?>"
                               name="forced_type" value="<?= htmlspecialchars($candidate['type']) ?>" required>
                        <label for="type_<?= htmlspecialchars($candidate['type']) ?>" class="profile-option">
                            <div class="profile-icon"><i class="fas <?= $lbl['icon'] ?>" aria-hidden="true"></i></div>
                            <div class="profile-label"><?= htmlspecialchars($lbl['label']) ?></div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="help-text">Ressaisissez votre mot de passe pour confirmer.</p>
                <div class="form-group">
                    <label for="password2" class="required-field">Mot de passe</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-lock" aria-hidden="true"></i>
                        <input type="password" id="password2" name="password" class="form-control input-with-icon"
                               required autocomplete="current-password">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        <span class="btn-text"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Confirmer</span>
                    </button>
                </div>
                <div class="help-links">
                    <a href="index.php">Retour</a>
                </div>
            </form>
        <?php else: ?>
            <!-- Formulaire principal -->
            <form method="post" action="" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="login" value="1">

                <div class="form-group">
                    <label for="username" class="required-field">Identifiant ou e-mail</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-user" aria-hidden="true"></i>
                        <input type="text" id="username" name="username" class="form-control input-with-icon"
                               value="<?= htmlspecialchars($lastUsername) ?>" required autofocus
                               autocomplete="username" placeholder="votre.identifiant ou email">
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
                        <span class="btn-loading-text" style="display:none;"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Connexion…</span>
                    </button>
                </div>

                <div class="help-links">
                    <a href="reset_password.php">Mot de passe oublié ?</a>
                </div>
            </form>
        <?php endif; ?>
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
                toggle.querySelector('i').classList.toggle('fa-eye');
                toggle.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        // Auto-focus password if username filled
        var usernameInput = document.getElementById('username');
        if (usernameInput && usernameInput.value.trim()) {
            if (pwInput) pwInput.focus();
        }

        // Loading state on submit
        var form = document.getElementById('loginForm');
        var btn  = document.getElementById('loginBtn');
        if (form && btn) {
            form.addEventListener('submit', function() {
                btn.disabled = true;
                var txt  = btn.querySelector('.btn-text');
                var load = btn.querySelector('.btn-loading-text');
                if (txt)  txt.style.display  = 'none';
                if (load) load.style.display = 'inline';
            });
        }
    });
    </script>
</body>
</html>
