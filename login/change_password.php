<?php
/**
 * Changement de mot de passe — Version refactorisée.
 * +CSRF, +password-strength.js externe, +countdown JS, -header("refresh").
 */
require_once __DIR__ . '/../API/core.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que l'utilisateur a passé les étapes précédentes
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_code'])) {
    header('Location: reset_password.php');
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Vérification CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Jeton de sécurité invalide. Veuillez recharger la page.';
    } else {
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirmPassword)) {
            $error = 'Veuillez remplir tous les champs.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } else {
            $changeResult = changePassword($_SESSION['reset_user_id'], $password);

            if ($changeResult['success']) {
                $success = true;
                unset($_SESSION['reset_user_id'], $_SESSION['reset_code'], $_SESSION['reset_username']);
                $_SESSION['success_message'] = 'Votre mot de passe a été réinitialisé avec succès. Connectez-vous avec votre nouveau mot de passe.';
            } else {
                $error = $changeResult['message'];
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$username  = $_SESSION['reset_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer de mot de passe - FRONOTE</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="app-logo">P</div>
            <h1 class="app-title">FRONOTE</h1>
            <p class="app-subtitle">Nouveau mot de passe</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert" aria-live="assertive">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="status" aria-live="polite">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <div>
                    <p>Votre mot de passe a été modifié avec succès !</p>
                    <p class="countdown" aria-live="polite">Redirection dans <span id="timer">5</span> secondes…</p>
                </div>
            </div>

            <script>
            (function() {
                var seconds = 5;
                var timerEl = document.getElementById('timer');
                var interval = setInterval(function() {
                    seconds--;
                    if (timerEl) timerEl.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = 'index.php';
                    }
                }, 1000);
            })();
            </script>
        <?php else: ?>
            <form method="post" action="" id="changePasswordForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="alert alert-info">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <div>
                        <p>Définissez un nouveau mot de passe pour le compte <strong><?= htmlspecialchars($username) ?></strong>.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="required-field">Nouveau mot de passe</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-lock" aria-hidden="true"></i>
                        <input type="password" id="password" name="password" class="form-control input-with-icon"
                               required autofocus autocomplete="new-password">
                        <button type="button" class="visibility-toggle" aria-label="Afficher ou masquer le mot de passe">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="password-strength-meter">
                        <div class="strength-indicator" id="strength-indicator"></div>
                    </div>
                    <div class="password-strength-text" id="strength-text"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="required-field">Confirmer le mot de passe</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-lock" aria-hidden="true"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control input-with-icon"
                               required autocomplete="new-password">
                        <button type="button" class="visibility-toggle" aria-label="Afficher ou masquer le mot de passe">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="verify_reset_code.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
                    </a>
                    <button type="submit" name="change_password" class="btn btn-primary" id="submitBtn">
                        <span class="btn-text"><i class="fas fa-save" aria-hidden="true"></i> Changer le mot de passe</span>
                    </button>
                </div>
            </form>

            <script src="assets/js/password-strength.js"></script>
        <?php endif; ?>
    </div>
</body>
</html>
