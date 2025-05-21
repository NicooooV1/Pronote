<?php
/**
 * Page de gestion des comptes utilisateur
 * Cette page permet de gérer les comptes utilisateurs existants
 * (activation/désactivation des comptes)
 */

// Démarrer la session pour vérifier l'authentification
session_start();

// Vérifier si l'utilisateur est un administrateur
if (!isset($_SESSION['user']) || $_SESSION['user']['profil'] !== 'administrateur') {
    header('Location: ../login/public/index.php');
    exit;
}

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../login/config/database.php';
require_once __DIR__ . '/../login/src/auth.php';
require_once __DIR__ . '/../login/src/user.php';

// Initialiser les objets Auth et User
$auth = new Auth($pdo);
$user = new User($pdo);

// Définir des variables pour les messages
$message = '';
$error = '';

// Générer un jeton CSRF si inexistant
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le jeton CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // Action d'activation/désactivation d'un compte
        if (isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $userType = isset($_POST['user_type']) ? $_POST['user_type'] : '';
            $active = isset($_POST['active']) && $_POST['active'] == '1' ? 1 : 0;
            
            if (!$userId || empty($userType)) {
                $error = "ID d'utilisateur ou type invalide.";
            } else {
                // S'assurer que la colonne actif existe dans la table
                try {
                    $table = '';
                    switch ($userType) {
                        case 'eleve': $table = 'eleves'; break;
                        case 'parent': $table = 'parents'; break;
                        case 'professeur': $table = 'professeurs'; break;
                        case 'vie_scolaire': $table = 'vie_scolaire'; break;
                        default: $error = "Type d'utilisateur invalide."; break;
                    }
                    
                    if (!$error) {
                        // Vérifier si la colonne actif existe
                        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'actif'");
                        $stmt->execute();
                        $columnExists = $stmt->fetch();
                        
                        if (!$columnExists) {
                            // Ajouter la colonne
                            $pdo->exec("ALTER TABLE `$table` ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1");
                            $message = "Structure de table mise à jour: colonne 'actif' ajoutée.";
                        }
                        
                        // Mettre à jour le statut
                        $stmt = $pdo->prepare("UPDATE `$table` SET actif = ? WHERE id = ?");
                        if ($stmt->execute([$active, $userId])) {
                            $message = "Le statut du compte a été mis à jour avec succès.";
                        } else {
                            $error = "Erreur lors de la mise à jour du statut.";
                        }
                    }
                } catch (PDOException $e) {
                    $error = "Erreur de base de données: " . $e->getMessage();
                }
            }
        }
    }
}

// Traitement de la recherche
$searchTerm = '';
$userType = '';
$usersList = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchTerm = isset($_POST['search_term']) ? trim($_POST['search_term']) : '';
    $userType = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    try {
        $usersList = $user->searchUsers($searchTerm, $userType);
    } catch (Exception $e) {
        $error = "Erreur lors de la recherche: " . $e->getMessage();
        $usersList = [];
    }
} else {
    // Chargement initial limité
    try {
        $usersList = $user->getAllUsers(100); // Limiter à 100 utilisateurs par défaut
    } catch (Exception $e) {
        $error = "Erreur lors du chargement des utilisateurs: " . $e->getMessage();
        $usersList = [];
    }
}

// Récupérer les informations de l'utilisateur administrateur connecté
$admin = $_SESSION['user'];
$admin_initials = strtoupper(mb_substr($admin['prenom'], 0, 1) . mb_substr($admin['nom'], 0, 1));

// Titre de la page
$pageTitle = "Gestion des comptes utilisateur";
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
        
        .user-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Structure principale de l'application */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Barre latérale */
        .sidebar {
            width: 260px;
            background-color: #0f4c81;
            color: white;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            text-decoration: none;
            color: white;
        }
        
        .app-logo {
            width: 40px;
            height: 40px;
            background-color: #fff;
            color: #0f4c81;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
            margin-right: 10px;
        }
        
        .app-title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .sidebar-section {
            margin-bottom: 30px;
            padding: 0 20px;
        }
        
        .sidebar-section-header {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 600;
        }
        
        .sidebar-nav {
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-nav-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.8);
            border-radius: 6px;
            margin-bottom: 5px;
            transition: background-color 0.2s, color 0.2s;
        }
        
        .sidebar-nav-item:hover, .sidebar-nav-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar-nav-icon {
            margin-right: 10px;
            width: 24px;
            text-align: center;
            font-size: 16px;
        }
        
        /* Contenu principal */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* En-tête */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            font-size: 28px;
            font-weight: 500;
            color: #0f4c81;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
        }
        
        .logout-button {
            text-decoration: none;
            color: #777;
            font-size: 20px;
            margin-right: 20px;
            transition: color 0.2s;
        }
        
        .logout-button:hover {
            color: #ff3b30;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: #0f4c81;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 16px;
        }
        
        /* Tableau d'utilisateurs */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f5f7fa;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background-color: #f9fafb;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s, color 0.2s;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 100;
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 250px;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .profile-eleve {
            background-color: #e3f2fd;
            color: #0d47a1;
        }
        
        .profile-parent {
            background-color: #e8f5e9;
            color: #1b5e20;
        }
        
        .profile-professeur {
            background-color: #fff3e0;
            color: #e65100;
        }
        
        .profile-vie_scolaire {
            background-color: #f3e5f5;
            color: #6a1b9a;
        }
        
        .active-status {
            color: #28a745;
            font-weight: bold;
        }
        
        .inactive-status {
            color: #dc3545;
        }
        
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination-btn {
            padding: 5px 10px;
            margin: 0 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background-color: white;
            cursor: pointer;
        }
        
        .pagination-btn.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
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
                <a href="reset_user_password.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-key"></i></span>
                    <span>Réinitialiser mot de passe</span>
                </a>
                <a href="reset_requests.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Demandes de réinitialisation</span>
                </a>
                <a href="admin_accounts.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span>Gestion des administrateurs</span>
                </a>
                <a href="user_accounts.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon"><i class="fas fa-users-cog"></i></span>
                    <span>Gestion des utilisateurs</span>
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

        <div class="user-container">
            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>
            
            <!-- Formulaire de recherche -->
            <form method="post" action="" class="search-form">
                <div class="input-group search-input">
                    <i class="input-group-icon fas fa-search"></i>
                    <input type="text" name="search_term" placeholder="Rechercher un utilisateur..." 
                           class="form-control input-with-icon" value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                
                <select name="user_type" class="form-select">
                    <option value="">Tous les profils</option>
                    <option value="eleve" <?= $userType === 'eleve' ? 'selected' : '' ?>>Élève</option>
                    <option value="parent" <?= $userType === 'parent' ? 'selected' : '' ?>>Parent</option>
                    <option value="professeur" <?= $userType === 'professeur' ? 'selected' : '' ?>>Professeur</option>
                    <option value="vie_scolaire" <?= $userType === 'vie_scolaire' ? 'selected' : '' ?>>Vie scolaire</option>
                </select>
                
                <button type="submit" name="search" class="btn btn-primary">
                    <i class="fas fa-search"></i> Rechercher
                </button>
            </form>
            
            <!-- Tableau des utilisateurs -->
            <?php if (empty($usersList)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>Aucun utilisateur trouvé. Veuillez modifier vos critères de recherche.</div>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Identifiant</th>
                            <th>Profil</th>
                            <th>Email</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $userItem): 
                            // Vérifier si l'utilisateur a un statut actif (par défaut true si non défini)
                            $isActive = isset($userItem['actif']) ? (bool)$userItem['actif'] : true;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($userItem['nom']) ?></td>
                                <td><?= htmlspecialchars($userItem['prenom']) ?></td>
                                <td><?= htmlspecialchars($userItem['identifiant']) ?></td>
                                <td>
                                    <span class="profile-badge profile-<?= htmlspecialchars($userItem['profil']) ?>">
                                        <?= htmlspecialchars($userItem['profil']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($userItem['mail']) ?></td>
                                <td class="<?= $isActive ? 'active-status' : 'inactive-status' ?>">
                                    <?= $isActive ? 'Actif' : 'Inactif' ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn <?= $isActive ? 'btn-danger' : 'btn-success' ?>"
                                                onclick="openToggleModal(<?= $userItem['id'] ?>, '<?= htmlspecialchars($userItem['nom'] . ' ' . $userItem['prenom']) ?>', '<?= htmlspecialchars($userItem['profil']) ?>', <?= $isActive ? 0 : 1 ?>)">
                                            <i class="fas fa-<?= $isActive ? 'ban' : 'check' ?>"></i>
                                            <?= $isActive ? 'Désactiver' : 'Activer' ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination si nécessaire -->
                <?php if (count($usersList) >= 100): ?>
                    <div class="alert alert-info" style="margin-top: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <div>Les résultats sont limités. Utilisez la recherche pour affiner votre liste.</div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour activer/désactiver un compte -->
<div id="toggleModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeToggleModal()">&times;</span>
        <h2 id="toggle-title">Modifier le statut du compte</h2>
        <p id="toggle-message"></p>
        <form method="post" action="" id="toggle-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="user_id" id="toggle_user_id" value="">
            <input type="hidden" name="user_type" id="toggle_user_type" value="">
            <input type="hidden" name="active" id="toggle_active" value="">
            
            <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeToggleModal()">Annuler</button>
                <button type="submit" class="btn" id="toggle-submit">Confirmer</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Fonctions pour la modal d'activation/désactivation
    function openToggleModal(userId, userName, userType, active) {
        document.getElementById('toggle_user_id').value = userId;
        document.getElementById('toggle_user_type').value = userType;
        document.getElementById('toggle_active').value = active;
        
        const title = active ? 'Activer le compte' : 'Désactiver le compte';
        const message = active 
            ? `Êtes-vous sûr de vouloir activer le compte de ${userName} ?` 
            : `Êtes-vous sûr de vouloir désactiver le compte de ${userName} ?
               L'utilisateur ne pourra plus se connecter à PRONOTE.`;
        const submitText = active ? 'Activer' : 'Désactiver';
        const submitClass = active ? 'btn-success' : 'btn-danger';
        
        document.getElementById('toggle-title').innerText = title;
        document.getElementById('toggle-message').innerText = message;
        document.getElementById('toggle-submit').innerText = submitText;
        document.getElementById('toggle-submit').className = `btn ${submitClass}`;
        
        document.getElementById('toggleModal').style.display = 'block';
    }
    
    function closeToggleModal() {
        document.getElementById('toggle-form').reset();
        document.getElementById('toggleModal').style.display = 'none';
    }
    
    // Fermer les modals si on clique en dehors
    window.onclick = function(event) {
        const toggleModal = document.getElementById('toggleModal');
        
        if (event.target === toggleModal) {
            closeToggleModal();
        }
    }
</script>
</body>
</html>
