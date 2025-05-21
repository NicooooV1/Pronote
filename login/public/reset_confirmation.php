<?php
// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que l'utilisateur vient bien de la page de demande de réinitialisation
if (!isset($_SESSION['reset_requested']) || !$_SESSION['reset_requested']) {
    header("Location: reset_password.php");
    exit;
}

// Récupérer les informations
$username = isset($_SESSION['reset_username']) ? $_SESSION['reset_username'] : 'l\'utilisateur';

// Nettoyer les variables de session
unset($_SESSION['reset_requested']);
unset($_SESSION['reset_username']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande envoyée - PRONOTE</title>
    <link rel="stylesheet" href="assets/css/pronote-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <!-- En-tête -->
        <div class="auth-header">
            <div class="app-logo">P</div>
            <h1 class="app-title">PRONOTE</h1>
            <p class="app-subtitle">Demande de réinitialisation</p>
        </div>
        
        <!-- Message de confirmation -->
        <div class="success-message">
            <h3><i class="fas fa-check-circle"></i> Demande envoyée avec succès</h3>
            <p>Votre demande de réinitialisation de mot de passe pour le compte <strong><?= htmlspecialchars($username) ?></strong> a bien été prise en compte.</p>
            <br>
            <p>Un administrateur va traiter votre demande dans les plus brefs délais. Une fois votre mot de passe réinitialisé, vous recevrez vos nouveaux identifiants de connexion par email.</p>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>
                <p>Pour des raisons de sécurité, le processus de réinitialisation nécessite une validation manuelle par un administrateur.</p>
                <p>Si vous n'avez pas reçu d'email dans les prochaines 48 heures, veuillez contacter le support.</p>
            </div>
        </div>
        
        <div class="form-actions">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Retour à la page de connexion
            </a>
        </div>
    </div>
</body>
</html>
