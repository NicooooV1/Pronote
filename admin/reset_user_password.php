<?php
require_once '../login/config/database.php';
require_once '../login/src/auth.php';
require_once '../login/src/user.php';

// Démarrer ou reprendre une session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user']) || 
    !isset($_SESSION['user']['profil']) || 
    $_SESSION['user']['profil'] !== 'administrateur') {
    
    // Rediriger vers la page de connexion
    header("Location: ../login/public/index.php");
    exit;
}

// Initialisation
$auth = new Auth($pdo);
$user = new User($pdo);
$admin = $_SESSION['user'];
$admin_initials = strtoupper(mb_substr($admin['prenom'], 0, 1) . mb_substr($admin['nom'], 0, 1));

$error = '';
$success = '';
$usersList = [];
$selectedUser = null;
$generatedPassword = '';

// Récupérer la liste des utilisateurs
try {
    $usersList = $user->getAllUsers();
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des utilisateurs: " . $e->getMessage();
}

// Traitement de la recherche d'utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchTerm = isset($_POST['search_term']) ? trim($_POST['search_term']) : '';
    $userType = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    if (empty($searchTerm)) {
        $error = "Veuillez saisir un terme de recherche.";
    } else {
        try {
            $usersList = $user->searchUsers($searchTerm, $userType);
            if (empty($usersList)) {
                $error = "Aucun utilisateur trouvé.";
            }
        } catch (Exception $e) {
            $error = "Erreur lors de la recherche: " . $e->getMessage();
        }
    }
}

// Traitement de la sélection d'utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_user'])) {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($userId <= 0) {
        $error = "Utilisateur invalide.";
    } else {
        try {
            $selectedUser = $user->getUserById($userId);
            if (!$selectedUser) {
                $error = "Utilisateur non trouvé.";
            }
        } catch (Exception $e) {
            $error = "Erreur lors de la récupération de l'utilisateur: " . $e->getMessage();
        }
    }
}

// Traitement de la réinitialisation du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($userId <= 0) {
        $error = "Utilisateur invalide.";
    } else {
        try {
            $resetResult = $auth->resetPassword($userId);
            
            if ($resetResult['success']) {
                $success = "Le mot de passe a été réinitialisé avec succès.";
                $generatedPassword = $resetResult['password'];
                
                // Récupérer les informations de l'utilisateur
                $selectedUser = $user->getUserById($userId);
                
                // Journaliser la réinitialisation
                error_log("Mot de passe réinitialisé pour l'utilisateur ID {$userId} par admin: {$admin['identifiant']}");
            } else {
                $error = $resetResult['message'];
            }
        } catch (Exception $e) {
            $error = "Erreur lors de la réinitialisation du mot de passe: " . $e->getMessage();
        }
    }
}

// Titre de la page
$pageTitle = "Réinitialisation de mot de passe";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - PRONOTE</title>
    <link rel="stylesheet" href="../assets/css/pronote-theme.css">
    <link rel="stylesheet" href="../login/public/assets/css/pronote-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: block;
            padding: 0;
            margin: 0;
            min-height: 100vh;
            background-color: var(--background-color);
        }
        
        .auth-container {
            box-shadow: none;
            border-radius: 0;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .users-list {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .users-list th, .users-list td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .users-list th {
            background-color: #f5f5f5;
            font-weight: 500;
        }
        
        .users-list tr:hover {
            background-color: rgba(15, 76, 129, 0.05);
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
        }
        
        .user-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }
        
        .user-detail {
            margin-bottom: 10px;
        }
        
        .user-label {
            font-weight: 500;
            display: inline-block;
            width: 150px;
        }
        
        .user-value {
            font-weight: normal;
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="../accueil/accueil.php" class="logo-container">
            <div class="app-logo">P</div>
            <div class="app-title">PRONOTE</div>
        </a>
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">Navigation</div>
            <div class="sidebar-nav">
                <a href="../accueil/accueil.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                    <span>Accueil</span>
                </a>
                <a href="../notes/notes.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                    <span>Notes</span>
                </a>
                <a href="../agenda/agenda.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                    <span>Agenda</span>
                </a>
                <a href="../cahierdetextes/cahierdetextes.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                    <span>Cahier de textes</span>
                </a>
                <a href="../messagerie/index.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                    <span>Messagerie</span>
                </a>
                <a href="../absences/absences.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                    <span>Absences</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">Administration</div>
            <div class="sidebar-nav">
                <a href="../login/public/register.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Ajouter un utilisateur</span>
                </a>
                <a href="reset_user_password.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon"><i class="fas fa-key"></i></span>
                    <span>Réinitialiser mot de passe</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="top-header">
            <div class="page-title">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
            
            <div class="header-actions">
                <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                <div class="user-avatar" title="<?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?>">
                    <?= $admin_initials ?>
                </div>
            </div>
        </div>

        <div class="auth-container">
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
            
            <!-- Formulaire de recherche -->
            <form method="post" action="" class="search-form">
                <div class="input-group search-input">
                    <i class="input-group-icon fas fa-search"></i>
                    <input type="text" name="search_term" placeholder="Rechercher un utilisateur..." class="form-control input-with-icon">
                </div>
                
                <select name="user_type" class="form-select">
                    <option value="">Tous les profils</option>
                    <option value="eleve">Élève</option>
                    <option value="parent">Parent</option>
                    <option value="professeur">Professeur</option>
                    <option value="vie_scolaire">Vie scolaire</option>
                    <option value="administrateur">Administrateur</option>
                </select>
                
                <button type="submit" name="search" class="btn btn-primary">
                    <i class="fas fa-search"></i> Rechercher
                </button>
            </form>
            
            <?php if (!empty($generatedPassword) && $selectedUser): ?>
                <div class="alert alert-success">
                    <i class="fas fa-key"></i>
                    <div>
                        <p>Le mot de passe de <strong><?= htmlspecialchars($selectedUser['prenom'] . ' ' . $selectedUser['nom']) ?></strong> a été réinitialisé.</p>
                        <div class="credentials-info">
                            <p><strong>Identifiant :</strong> <?= htmlspecialchars($selectedUser['identifiant']) ?></p>
                            <p><strong>Nouveau mot de passe :</strong> <?= htmlspecialchars($generatedPassword) ?></p>
                            <p class="warning">Veuillez communiquer ces informations à l'utilisateur de façon sécurisée.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($selectedUser && empty($generatedPassword)): ?>
                <!-- Informations de l'utilisateur sélectionné -->
                <div class="user-info">
                    <h3>Informations de l'utilisateur</h3>
                    <div class="user-detail">
                        <span class="user-label">Identifiant :</span>
                        <span class="user-value"><?= htmlspecialchars($selectedUser['identifiant']) ?></span>
                    </div>
                    <div class="user-detail">
                        <span class="user-label">Nom :</span>
                        <span class="user-value"><?= htmlspecialchars($selectedUser['nom']) ?></span>
                    </div>
                    <div class="user-detail">
                        <span class="user-label">Prénom :</span>
                        <span class="user-value"><?= htmlspecialchars($selectedUser['prenom']) ?></span>
                    </div>
                    <div class="user-detail">
                        <span class="user-label">Type :</span>
                        <span class="user-value"><?= htmlspecialchars($selectedUser['profil']) ?></span>
                    </div>
                    <div class="user-detail">
                        <span class="user-label">Email :</span>
                        <span class="user-value"><?= htmlspecialchars($selectedUser['mail'] ?? 'Non défini') ?></span>
                    </div>
                    
                    <form method="post" action="">
                        <input type="hidden" name="user_id" value="<?= $selectedUser['id'] ?>">
                        <button type="submit" name="reset_password" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?');">
                            <i class="fas fa-key"></i> Réinitialiser le mot de passe
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Liste des utilisateurs -->
            <?php if (!empty($usersList)): ?>
                <h3>Liste des utilisateurs</h3>
                <table class="users-list">
                    <thead>
                        <tr>
                            <th>Identifiant</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Profil</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['identifiant']) ?></td>
                                <td><?= htmlspecialchars($u['nom']) ?></td>
                                <td><?= htmlspecialchars($u['prenom']) ?></td>
                                <td><?= htmlspecialchars($u['profil']) ?></td>
                                <td>
                                    <form method="post" action="">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="select_user" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-user"></i> Sélectionner
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>Aucun utilisateur trouvé. Veuillez modifier vos critères de recherche.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
