<?php
/**
 * Vérification du code TOTP (étape 2FA du login).
 * Prérequis : $_SESSION['pending_2fa'] doit être défini.
 */
require_once __DIR__ . '/../API/core.php';

// Security headers
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; font-src cdnjs.cloudflare.com; img-src 'self' data:;");
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// Déjà connecté → accueil
if (isLoggedIn()) {
    redirect('accueil/accueil.php');
}

// Pas de pending_2fa → retour login
if (empty($_SESSION['pending_2fa'])) {
    redirect('login/index.php');
}

$pending     = $_SESSION['pending_2fa'];
$userId      = (int) $pending['user_id'];
$userType    = $pending['user_type'];
$rememberMe  = $pending['remember_me'] ?? false;

$userService = app()->make('API\Services\UserService');
$auth        = app('auth');
$twoFactor   = new \API\Services\TwoFactorService(getPDO());

$error   = '';
$success = '';
$ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Jeton de sécurité invalide. Veuillez recharger la page.';
    } elseif (isset($_POST['cancel'])) {
        unset($_SESSION['pending_2fa']);
        redirect('login/index.php');
    } else {
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');

        if (strlen($code) !== 6) {
            $error = 'Le code doit contenir exactement 6 chiffres.';
        } elseif (!$twoFactor->validateLogin($userId, $userType, $code)) {
            $error = 'Code incorrect. Vérifiez votre application d\'authentification et réessayez.';
        } else {
            // Code valide → créer la session
            unset($_SESSION['pending_2fa']);

            $user = $userService->findById($userId, $userType);
            if (!$user) {
                $error = 'Utilisateur introuvable.';
            } else {
                $auth->loginUser($user);

                if ($rememberMe) {
                    $userService->createRememberToken($userId, $userType);
                }

                // Forcer changement de mot de passe si jamais changé
                if (empty($user['password_changed_at'])) {
                    $_SESSION['force_password_change'] = true;
                    $_SESSION['reset_user_id']         = $userId;
                    $_SESSION['reset_code']            = 'force_change';
                    $_SESSION['reset_username']        = $user['identifiant'] ?? '';
                    redirect('login/change_password.php');
                }

                $userService->cleanOldAttempts();
                redirect('accueil/accueil.php');
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
    <title>Vérification 2FA - FRONOTE</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
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
            <div class="app-logo" style="background: var(--color-success, #22c55e);">
                <i class="fas fa-shield-alt" style="font-size:1.5rem;"></i>
            </div>
            <h1 class="app-title">FRONOTE</h1>
            <p class="app-subtitle">Authentification à deux facteurs</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert" aria-live="assertive">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <div class="alert alert-info" role="note" style="margin-bottom: 1.5rem;">
            <i class="fas fa-mobile-alt" aria-hidden="true"></i>
            <div>Ouvrez votre application d'authentification (Google Authenticator, Authy…) et saisissez le code à 6 chiffres affiché.</div>
        </div>

        <form method="post" action="" id="twoFaForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="code" class="required-field">Code de vérification</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-key" aria-hidden="true"></i>
                    <input type="text" id="code" name="code"
                           class="form-control input-with-icon"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           placeholder="000000" required autofocus
                           autocomplete="one-time-code"
                           style="letter-spacing: 0.3em; font-size: 1.4rem; text-align: center;">
                </div>
                <small class="help-text" style="color: var(--text-muted, #6b7280); margin-top: .4rem; display:block;">
                    Le code est valide 30 secondes. Actualisez si nécessaire.
                </small>
            </div>

            <div class="form-actions" style="gap: .75rem; display:flex; flex-direction:column;">
                <button type="submit" class="btn btn-primary" id="verifyBtn">
                    <span class="btn-text"><i class="fas fa-check-circle" aria-hidden="true"></i> Vérifier</span>
                    <span class="btn-loading-text" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Vérification…</span>
                </button>
                <button type="submit" name="cancel" value="1" class="btn btn-secondary"
                        formnovalidate style="background:transparent; color: var(--text-muted,#6b7280); border: 1px solid currentColor;">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la connexion
                </button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var codeInput = document.getElementById('code');
        var form      = document.getElementById('twoFaForm');
        var btn       = document.getElementById('verifyBtn');

        // Auto-format : ne garder que les chiffres, soumettre automatiquement quand 6 chiffres
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
                if (this.value.length === 6) {
                    form.submit();
                }
            });
        }

        // Loading state
        if (form && btn) {
            form.addEventListener('submit', function(e) {
                if (e.submitter && e.submitter.name === 'cancel') return;
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
