<?php
/**
 * Page de connexion Pronote - Version intégrée à l'API centralisée
 */

// Inclure l'API centralisée - chemin uniformisé
require_once __DIR__ . '/../../API/core.php';

// Démarrer la session si pas encore fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si l'utilisateur est déjà connecté, le rediriger
if (isLoggedIn()) {
    redirect('/accueil/accueil.php');
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
        try {
            // Utilisation exclusive de l'API centralisée
            $loginResult = authenticateUser($username, $password, $userType, $rememberMe);

            if ($loginResult['success']) {
                // Connexion réussie
                $_SESSION['user'] = $loginResult['user'];
                
                // Gestion du "Se souvenir de moi"
                if ($rememberMe && isset($loginResult['remember_token'])) {
                    setcookie('remember_me', $loginResult['remember_token'], time() + (86400 * 30), "/");
                }
                
                redirect('/accueil/accueil.php');
            } else {
                $error = $loginResult['message'] ?? "Identifiant ou mot de passe incorrect.";
                $last_username = $username;
            }
        } catch (Exception $e) {
            $error = "Une erreur système s'est produite. Veuillez réessayer.";
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
    <style>
        /* Styles modernisés pour la page de login */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .auth-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 480px;
            margin: 20px;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .app-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0f4c81 0%, #2980b9 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        
        .app-title {
            font-size: 32px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 10px;
        }
        
        .app-subtitle {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .profile-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .profile-selector input[type="radio"] {
            display: none;
        }
        
        .profile-option {
            padding: 20px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .profile-option:hover {
            border-color: #3498db;
            background: #f0f8ff;
        }
        
        .profile-selector input[type="radio"]:checked + .profile-option {
            border-color: #3498db;
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .profile-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .profile-label {
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .required-field::after {
            content: " *";
            color: #e74c3c;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 18px;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .visibility-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            font-size: 18px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn i {
            margin-right: 10px;
        }
        
        .help-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .help-links a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        
        .help-links a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 600px) {
            .profile-selector {
                grid-template-columns: 1fr;
            }
            
            .auth-container {
                margin: 10px;
                padding: 30px 20px;
            }
        }
    </style>
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
                <input type="radio" id="eleve" name="user_type" value="eleve" required>
                <label for="eleve" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="profile-label">Élève</div>
                </label>

                <input type="radio" id="parent" name="user_type" value="parent" required>
                <label for="parent" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="profile-label">Parent</div>
                </label>

                <input type="radio" id="professeur" name="user_type" value="professeur" required>
                <label for="professeur" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="profile-label">Professeur</div>
                </label>

                <input type="radio" id="personnel" name="user_type" value="vie_scolaire" required>
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
                    <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($last_username) ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="required-field">Mot de passe</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" required>
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
                <button type="submit" name="login" class="btn">
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
            
            if (toggleButton && passwordInput) {
                toggleButton.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    const icon = toggleButton.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }
            
            // Auto-focus sur le champ mot de passe si l'identifiant est déjà rempli
            const usernameInput = document.getElementById('username');
            if (usernameInput && usernameInput.value.trim() !== '') {
                passwordInput.focus();
            }
        });
    </script>
</body>
</html>