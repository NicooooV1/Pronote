<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/auth.php';

// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que l'utilisateur a bien passé les étapes précédentes
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_code'])) {
    header("Location: reset_password.php");
    exit;
}

$auth = new Auth($pdo);
$error = '';
$success = '';

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validation de base
    if (empty($password) || empty($confirmPassword)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif ($password !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Changer le mot de passe
        $changeResult = $auth->changePassword($_SESSION['reset_user_id'], $password);
        
        if ($changeResult['success']) {
            $success = "Votre mot de passe a été modifié avec succès !";
            
            // Supprimer les variables de session liées à la réinitialisation
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_code']);
            unset($_SESSION['reset_username']);
            
            // Définir un message de succès pour la page de connexion
            $_SESSION['success_message'] = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.";
            
            // Rediriger vers la page de connexion après 3 secondes
            header("refresh:3;url=index.php");
        } else {
            $error = $changeResult['message'];
        }
    }
}

// Récupérer les informations pour affichage
$username = $_SESSION['reset_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer de mot de passe - PRONOTE</title>
    <link rel="stylesheet" href="assets/css/pronote-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <!-- En-tête -->
        <div class="auth-header">
            <div class="app-logo">P</div>
            <h1 class="app-title">PRONOTE</h1>
            <p class="app-subtitle">Nouveau mot de passe</p>
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
                <div>
                    <p><?= htmlspecialchars($success) ?></p>
                    <p>Vous allez être redirigé vers la page de connexion...</p>
                </div>
            </div>
        <?php else: ?>
        <!-- Formulaire de changement de mot de passe -->
        <form method="post" action="">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <p>Veuillez définir un nouveau mot de passe pour le compte <strong><?= htmlspecialchars($username) ?></strong>.</p>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password" class="required-field">Nouveau mot de passe</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control input-with-icon" required autofocus>
                    <button type="button" class="visibility-toggle" title="Afficher/Masquer le mot de passe">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-strength-meter">
                    <div class="strength-indicator" id="strength-indicator"></div>
                </div>
                <div class="password-strength-text" id="strength-text"></div>
            </div>
            
            <div id="password-requirements">
                <div class="requirement-item">
                    <span class="requirement-status" id="length-check"><i class="fas fa-times invalid"></i></span>
                    Au moins 8 caractères
                </div>
                <div class="requirement-item">
                    <span class="requirement-status" id="uppercase-check"><i class="fas fa-times invalid"></i></span>
                    Au moins une lettre majuscule
                </div>
                <div class="requirement-item">
                    <span class="requirement-status" id="number-check"><i class="fas fa-times invalid"></i></span>
                    Au moins un chiffre
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="required-field">Confirmer le mot de passe</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control input-with-icon" required>
                    <button type="button" class="visibility-toggle" title="Afficher/Masquer le mot de passe">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="verify_reset_code.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <button type="submit" name="change_password" class="btn btn-primary">
                    <i class="fas fa-save"></i> Changer le mot de passe
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion de l'affichage/masquage des mots de passe
            const toggleButtons = document.querySelectorAll('.visibility-toggle');
            
            toggleButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            });
            
            // Vérification de la complexité du mot de passe
            const passwordInput = document.getElementById('password');
            const strengthIndicator = document.getElementById('strength-indicator');
            const strengthText = document.getElementById('strength-text');
            
            // Éléments de validation
            const lengthCheck = document.getElementById('length-check');
            const uppercaseCheck = document.getElementById('uppercase-check');
            const numberCheck = document.getElementById('number-check');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Vérifications
                const hasLength = password.length >= 8;
                const hasUppercase = /[A-Z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                
                // Mettre à jour les indicateurs
                updateCheck(lengthCheck, hasLength);
                updateCheck(uppercaseCheck, hasUppercase);
                updateCheck(numberCheck, hasNumber);
                
                // Calculer la force
                if (hasLength) strength += 33;
                if (hasUppercase) strength += 33;
                if (hasNumber) strength += 34;
                
                // Mettre à jour l'indicateur visuel
                strengthIndicator.style.width = strength + '%';
                
                // Définir la couleur en fonction de la force
                if (strength < 33) {
                    strengthIndicator.style.backgroundColor = '#ff3b30';
                    strengthText.textContent = 'Faible';
                    strengthText.style.color = '#ff3b30';
                } else if (strength < 70) {
                    strengthIndicator.style.backgroundColor = '#ff9500';
                    strengthText.textContent = 'Moyen';
                    strengthText.style.color = '#ff9500';
                } else {
                    strengthIndicator.style.backgroundColor = '#34c759';
                    strengthText.textContent = 'Fort';
                    strengthText.style.color = '#34c759';
                }
            });
            
            function updateCheck(element, isValid) {
                const icon = element.querySelector('i');
                if (isValid) {
                    icon.className = 'fas fa-check valid';
                } else {
                    icon.className = 'fas fa-times invalid';
                }
            }
            
            // Vérification de la correspondance des mots de passe
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    this.style.borderColor = '#ff3b30';
                    this.style.boxShadow = '0 0 0 3px rgba(255, 59, 48, 0.1)';
                } else {
                    this.style.borderColor = '#34c759';
                    this.style.boxShadow = '0 0 0 3px rgba(52, 199, 89, 0.1)';
                }
            });
        });
    </script>
</body>
</html>