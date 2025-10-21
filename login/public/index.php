<?php
/**
 * Page de connexion Pronote - Version intégrée à l'API centralisée
 */

// Charger l'API centralisée
require_once __DIR__ . '/../../API/core.php';

// Si l'utilisateur est déjà connecté, le rediriger
if (isLoggedIn()) {
    redirect('accueil/accueil.php');
}

$error = '';
$success = '';
$last_username = $_SESSION['last_username'] ?? '';
unset($_SESSION['last_username']);

// Récupérer les messages de session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = $_POST['user_type'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    // Validation de base
    if (empty($username) || empty($password) || empty($userType)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // Authentification multi-profils pour "Personnel"
        $profilesToTry = ($userType === 'vie_scolaire')
            ? ['administrateur', 'vie_scolaire']
            : [$userType];

        $loginResult = null;
        foreach ($profilesToTry as $typeToTry) {
            $attempt = login($typeToTry, $username, $password);
            if ($attempt) {
                $loginResult = $attempt;
                break;
            }
        }

        if ($loginResult) {
            // Connexion réussie
            // Log pour débogage
            error_log("LOGIN SUCCESS: User=" . $username . ", Type=" . $userType);
            error_log("SESSION USER: " . print_r($_SESSION['user'] ?? null, true));
            
            // Redirection vers l'accueil
            redirect('accueil/accueil.php');
            exit; // Sécurité supplémentaire
        } else {
            $error = "Identifiant ou mot de passe incorrect.";
            $_SESSION['last_username'] = $username;
        }
    }
}

// Générer un token CSRF
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - PRONOTE</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <link rel="stylesheet" href="assets/css/pronote-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <!-- En-tête -->
        <div class="auth-header">
            <div class="app-logo">P</div>
            <h1 class="app-title">PRONOTE</h1>
            <p class="app-subtitle">Espace de connexion</p>
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

        <!-- Formulaire de connexion -->
        <form method="post" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <!-- Sélecteur de profil -->
            <div class="profile-selector">
                <input type="radio" id="eleve" name="user_type" value="eleve" required>
                <label for="eleve" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="profile-label">Élève</div>
                </label>

                <input type="radio" id="parent" name="user_type" value="parent">
                <label for="parent" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="profile-label">Parent</div>
                </label>

                <input type="radio" id="professeur" name="user_type" value="professeur">
                <label for="professeur" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="profile-label">Professeur</div>
                </label>

                <input type="radio" id="personnel" name="user_type" value="vie_scolaire">
                <label for="personnel" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="profile-label">Personnel</div>
                </label>
            </div>

            <!-- Champs de formulaire -->
            <div class="form-group">
                <label for="username" class="required-field">Identifiant</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-user"></i>
                    <input type="text" id="username" name="username" class="form-control input-with-icon" value="<?= htmlspecialchars($last_username) ?>" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="required-field">Mot de passe</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control input-with-icon" required autocomplete="current-password">
                    <button type="button" class="visibility-toggle" id="togglePassword" title="Afficher/Masquer le mot de passe">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-group">
                    <input type="checkbox" name="remember_me" id="remember_me">
                    <span>Se souvenir de moi</span>
                </label>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="submit" name="login" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </div>

            <!-- Liens d'aide -->
            <div class="help-links">
                <a href="reset_password.php">Mot de passe oublié ?</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion de l'affichage/masquage du mot de passe
            const toggleButton = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            if (toggleButton && passwordInput) {
                toggleButton.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    const icon = toggleButton.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }
            
            // Auto-focus sur le champ approprié
            const usernameInput = document.getElementById('username');
            if (usernameInput && usernameInput.value.trim() !== '') {
                passwordInput.focus();
            }

            // Sélection automatique du premier profil si aucun n'est sélectionné
            const radios = document.querySelectorAll('input[name="user_type"]');
            if (radios.length && !Array.from(radios).some(r => r.checked)) {
                const defaultRadio = document.getElementById('eleve');
                if (defaultRadio) defaultRadio.checked = true;
            }

            // Validation du formulaire
            const form = document.getElementById('loginForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value;
                    const userType = document.querySelector('input[name="user_type"]:checked');

                    if (!username || !password || !userType) {
                        e.preventDefault();
                        alert('Veuillez remplir tous les champs requis.');
                        return false;
                    }
                });
            }

            // Animation des profils
            const profileOptions = document.querySelectorAll('.profile-option');
            profileOptions.forEach(option => {
                option.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                option.addEventListener('mouseleave', function() {
                    const radio = this.previousElementSibling;
                    if (!radio.checked) {
                        this.style.transform = 'translateY(0)';
                    }
                });
            });
        });
    </script>
</body>
</html>