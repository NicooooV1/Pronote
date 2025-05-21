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
$userType = isset($_POST['user_type']) ? $_POST['user_type'] : '';

// Vérifier si les tables nécessaires existent, et les créer si ce n'est pas le cas
try {
    $query = "CREATE TABLE IF NOT EXISTS demandes_reinitialisation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type VARCHAR(30) NOT NULL,
        date_demande DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        date_traitement DATETIME NULL,
        admin_id INT NULL
    )";
    $pdo->exec($query);
} catch (PDOException $e) {
    error_log("Erreur lors de la création de la table de réinitialisation: " . $e->getMessage());
}

// Traitement du formulaire de demande de réinitialisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $userType = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    // Validation de base
    if (empty($username) || empty($email) || empty($phone)) {
        $error = "Veuillez remplir tous les champs.";
    } else if ($userType === 'administrateur') {
        $error = "Les administrateurs ne peuvent pas réinitialiser leur mot de passe par cette méthode.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format d'adresse email invalide.";
    } else {
        // Formater le numéro de téléphone
        $phone = formatPhoneNumber($phone);
        
        // Vérifier si les informations correspondent à un utilisateur
        $user = $auth->findUserByCredentials($username, $email, $phone, $userType);
        
        if ($user) {
            // Créer une demande de réinitialisation
            if ($auth->createResetRequest($user['id'], $userType)) {
                // Rediriger vers la page de confirmation
                $_SESSION['reset_requested'] = true;
                $_SESSION['reset_username'] = $username;
                header("Location: reset_confirmation.php");
                exit;
            } else {
                $error = $auth->getErrorMessage();
            }
        } else {
            $error = "Les informations fournies ne correspondent à aucun utilisateur.";
        }
    }
}

/**
 * Formate un numéro de téléphone au format XX XX XX XX XX
 */
function formatPhoneNumber($phone) {
    // Supprimer tous les caractères non numériques
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Vérifier si le numéro a 10 chiffres
    if (strlen($phone) === 10) {
        // Formater au format XX XX XX XX XX
        return implode(' ', str_split($phone, 2));
    }
    
    return $phone;
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
            <p class="app-subtitle">Demande de réinitialisation de mot de passe</p>
        </div>
        
        <!-- Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Note d'information -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>
                <p>Pour des raisons de sécurité, la réinitialisation de mot de passe doit être validée par un administrateur.</p>
                <p>Veuillez fournir les informations suivantes pour confirmer votre identité.</p>
            </div>
        </div>
        
        <!-- Formulaire de demande de réinitialisation -->
        <form method="post" action="">
            <!-- Sélecteur de profil -->
            <div class="profile-selector">
                <input type="radio" id="eleve" name="user_type" value="eleve" <?= $userType === 'eleve' ? 'checked' : '' ?> required>
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

                <input type="radio" id="personnel" name="user_type" value="vie_scolaire" <?= $userType === 'vie_scolaire' ? 'checked' : '' ?>>
                <label for="personnel" class="profile-option">
                    <div class="profile-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="profile-label">Personnel</div>
                </label>
            </div>
            
            <div class="form-group">
                <label for="username" class="required-field">Identifiant</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-user"></i>
                    <input type="text" id="username" name="username" class="form-control input-with-icon" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email" class="required-field">Adresse email</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-envelope"></i>
                    <input type="email" id="email" name="email" class="form-control input-with-icon" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="phone" class="required-field">Numéro de téléphone</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-phone"></i>
                    <input type="tel" id="phone" name="phone" class="form-control input-with-icon" required placeholder="Ex: 06 12 34 56 78">
                </div>
                <small class="form-text text-muted">Format: 10 chiffres (espaces optionnels)</small>
            </div>
            
            <div class="form-actions">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <button type="submit" name="request_reset" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Faire la demande
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Masquer le type "Administrateur" pour les réinitialisations
            const radioButtons = document.querySelectorAll('input[name="user_type"]');
            const personnelRadio = document.getElementById('personnel');
            
            // S'assurer qu'une option est sélectionnée par défaut
            if (!Array.from(radioButtons).some(radio => radio.checked)) {
                radioButtons[0].checked = true;
            }
            
            // Formater automatiquement le numéro de téléphone
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Garder seulement les chiffres
                if (value.length > 10) {
                    value = value.substring(0, 10); // Limiter à 10 chiffres
                }
                
                // Formatter avec des espaces
                if (value.length >= 2) {
                    let formattedValue = '';
                    for (let i = 0; i < value.length; i += 2) {
                        if (i + 2 <= value.length) {
                            formattedValue += value.substring(i, i + 2) + ' ';
                        } else {
                            formattedValue += value.substring(i);
                        }
                    }
                    e.target.value = formattedValue.trim();
                } else {
                    e.target.value = value;
                }
            });
        });
    </script>
</body>
</html>
