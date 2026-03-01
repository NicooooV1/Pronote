<?php
/**
 * Vérification du code de réinitialisation — Version refactorisée.
 * -config.php, +API/core.php, +hash_equals, +CSRF, +design system CSS.
 */
require_once __DIR__ . '/../API/core.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier qu'un code de réinitialisation est en attente
if (!isset($_SESSION['reset_code'])) {
    header('Location: reset_password.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    // Vérification CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Jeton de sécurité invalide. Veuillez recharger la page.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $expected = $_SESSION['reset_code'] ?? '';

        // Comparaison constante (évite le timing attack)
        if ($code !== '' && hash_equals($expected, $code)) {
            header('Location: change_password.php');
            exit;
        } else {
            $error = 'Code de réinitialisation invalide.';
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
    <title>Vérifier le code - FRONOTE</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="app-logo">P</div>
            <h1 class="app-title">FRONOTE</h1>
            <p class="app-subtitle">Vérification du code</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert" aria-live="assertive">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <div class="alert alert-info">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <div>
                <p>Saisissez le code de réinitialisation qui vous a été communiqué.</p>
            </div>
        </div>

        <form method="post" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="code" class="required-field">Code de réinitialisation</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-key" aria-hidden="true"></i>
                    <input type="text" id="code" name="code" class="form-control input-with-icon"
                           required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="6">
                </div>
            </div>

            <div class="form-actions">
                <a href="reset_password.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
                </a>
                <button type="submit" name="verify_code" class="btn btn-primary">
                    <i class="fas fa-check" aria-hidden="true"></i> Vérifier le code
                </button>
            </div>

            <div class="help-links">
                <a href="reset_password.php">Je n'ai pas reçu de code</a>
            </div>
        </form>
    </div>
</body>
</html>
