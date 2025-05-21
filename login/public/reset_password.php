<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/auth.php';

// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si l'utilisateur est déjà connecté, le rediriger vers la page d'accueil
if (isset($_SESSION['user'])) {
    header("Location: ../../accueil/accueil.php");
    exit;
}

$auth = new Auth($pdo);
$error = '';
$success = '';
$userType = '';

// Traitement du formulaire d'identification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identify'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $userType = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    if ($userType === 'personnel') {
        $userType = isset($_POST['personnel_type']) ? $_POST['personnel_type'] : 'vie_scolaire';
    }
    
    // Validation de base
    if (empty($username)) {
        $error = "Veuillez saisir votre identifiant.";
    } else {
        // Vérifier si l'utilisateur existe
        $user = $auth->findUserByUsername($username, $userType);
        
        if ($user) {
            // Générer un code de réinitialisation et envoyer un email
            $resetCode = $auth->generateResetCode($user['id']);
            
            if (!empty($user['mail'])) {
                // Simuler l'envoi d'email (pour développement)
                // Dans une application réelle, vous utiliseriez une bibliothèque d'envoi d'emails
                $success = "Un code de réinitialisation a été envoyé à l'adresse email associée à ce compte.";
                
                // Stocker les informations pour l'étape suivante
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_code'] = $resetCode;
                $_SESSION['reset_username'] = $username;
                
                // Rediriger vers la page de saisie du code
                header("Location: verify_reset_code.php");
                exit;
            } else {
                $error = "Aucune adresse email n'est associée à ce compte. Veuillez contacter l'administrateur.";
            }
        } else {
            $error = "Aucun compte trouvé avec cet identifiant et ce type d'utilisateur.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe - PRONOTE</title>
    <link rel="stylesheet" href="assets/css/pronote-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <!-- En-tête -->
        <div class="auth-header">
            <div class="app-logo">P</div>
            <h1 class="app-title">PRONOTE</h1>
            <p class="app-subtitle">Réinitialisation de mot de passe</p>
        </div>
        
        <!-- Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire d'identification -->
        <form method="post" action="">
            <p style="margin-bottom: 20px;">Pour réinitialiser votre mot de passe, veuillez saisir votre identifiant et sélectionner votre type de compte.</p>
            
            <!-- Sélecteur de profil -->
            <div class="profile-selector">
                <input type="radio" id="eleve" name="user_type" value="eleve" <?= $userType === 'eleve' ? 'checked' : '' ?>>
                <label for="eleve" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="profile-label">Élève</div>
                </label>

                <input type="radio" id="parent" name="user_type" value="parent" <?= $userType === 'parent' ? 'checked' : '' ?>>
                <label for="parent" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="profile-label">Parent</div>
                </label>

                <input type="radio" id="professeur" name="user_type" value="professeur" <?= $userType === 'professeur' ? 'checked' : '' ?>>
                <label for="professeur" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="profile-label">Professeur</div>
                </label>

                <input type="radio" id="personnel" name="user_type" value="personnel" <?= $userType === 'personnel' ? 'checked' : '' ?>>
                <label for="personnel" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="profile-label">Personnel</div>
                </label>
            </div>

            <!-- Sous-menu personnel -->
            <div id="personnel-submenu" class="personnel-options" style="display: none;">
                <label>
                    <input type="radio" name="personnel_type" value="vie_scolaire" checked>
                    <span>Vie scolaire</span>
                </label>
                <label>
                    <input type="radio" name="personnel_type" value="administrateur">
                    <span>Administrateur</span>
                </label>
            </div>
            
            <div class="form-group">
                <label for="username" class="required-field">Identifiant</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-user"></i>
                    <input type="text" id="username" name="username" class="form-control input-with-icon" required autofocus>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <button type="submit" name="identify" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Continuer
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du sous-menu personnel
            const personnelRadio = document.getElementById('personnel');
            const personnelSubmenu = document.getElementById('personnel-submenu');
            
            function togglePersonnelSubmenu() {
                personnelSubmenu.style.display = personnelRadio.checked ? 'flex' : 'none';
            }
            
            // Vérifier l'état initial
            togglePersonnelSubmenu();
            
            // Ajouter des écouteurs d'événements à tous les boutons radio
            document.querySelectorAll('input[name="user_type"]').forEach(function(radio) {
                radio.addEventListener('change', togglePersonnelSubmenu);
            });
            
            // Mettre à jour le type d'utilisateur en fonction de la sélection du sous-menu
            document.querySelectorAll('input[name="personnel_type"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    if (personnelRadio.checked) {
                        // Définir la valeur du type d'utilisateur en fonction de l'option sélectionnée
                        personnelRadio.value = this.value;
                    }
                });
            });
        });
    </script>
</body>
</html>
