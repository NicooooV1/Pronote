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
$last_username = isset($_SESSION['last_username']) ? $_SESSION['last_username'] : '';
unset($_SESSION['last_username']);

// Initialiser les données utilisateur par défaut
$userType = '';

// Vérifie si un message de succès est passé via la session (ex: après inscription ou réinitialisation)
if(isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Traiter la soumission du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $rememberMe = isset($_POST['remember_me']);
    $userType = isset($_POST['user_type']) ? $_POST['user_type'] : '';

    // Validation de base
    if (empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // Si l'utilisateur sélectionne "Personnel", tenter d'abord avec "vie_scolaire", puis avec "administrateur"
        if ($userType === 'personnel') {
            // Essayer d'abord avec "vie_scolaire"
            $vieResult = $auth->login('vie_scolaire', $username, $password);
            
            // Si ça échoue, essayer avec "administrateur"
            if (!$vieResult['success']) {
                $loginResult = $auth->login('administrateur', $username, $password);
            } else {
                $loginResult = $vieResult;
            }
        } else {
            // Sinon, utiliser le type sélectionné
            $loginResult = $auth->login($userType, $username, $password);
        }
        
        // Vérifier que $loginResult est bien un tableau et contient une clé 'success'
        if (is_array($loginResult) && isset($loginResult['success']) && $loginResult['success']) {
            // Stocker l'utilisateur en session
            $_SESSION['user'] = $loginResult['user'];
            
            // Si l'option "Se souvenir de moi" est cochée, stocker un cookie
            if ($rememberMe && method_exists($auth, 'generateRememberMeToken')) {
                $token = $auth->generateRememberMeToken($loginResult['user']['id']);
                setcookie('remember_me', $token, time() + (86400 * 30), "/"); // 30 jours
            }
            
            // Journaliser la connexion réussie
            error_log("Connexion réussie: " . $username . " (type: " . $_SESSION['user']['profil'] . ")");
            
            // Rediriger vers la page d'accueil
            header("Location: ../../accueil/accueil.php");
            exit;
        } else {
            // Si $loginResult est un boolean ou n'a pas la structure attendue
            if (!is_array($loginResult) || !isset($loginResult['message'])) {
                $error = "Identifiant ou mot de passe incorrect.";
            } else {
                $error = $loginResult['message'];
            }
            
            // Journaliser la tentative échouée
            error_log("Tentative de connexion échouée: " . $username . " (type: " . $userType . ") - " . $error);
            
            // Garder le nom d'utilisateur pour le réafficher
            $last_username = $username;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - PRONOTE</title>
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
        <form method="post" action="">
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

            <!-- Suppression du sous-menu personnel, la détection se fait automatiquement -->

            <!-- Champs de formulaire -->
            <div class="form-group">
                <label for="username" class="required-field">Identifiant</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-user"></i>
                    <input type="text" id="username" name="username" class="form-control input-with-icon" value="<?= htmlspecialchars($last_username) ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="required-field">Mot de passe</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control input-with-icon" required>
                    <button type="button" class="visibility-toggle" title="Afficher/Masquer le mot de passe">
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
            const toggleButton = document.querySelector('.visibility-toggle');
            const passwordInput = document.getElementById('password');
            
            toggleButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                toggleButton.querySelector('i').classList.toggle('fa-eye');
                toggleButton.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            // Personnelisation supplémentaire si nécessaire
        });
    </script>
</body>
</html>