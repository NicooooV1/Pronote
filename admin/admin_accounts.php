<?php
/**
 * Page de gestion des comptes administrateur
 * Cette page permet de gérer les comptes administrateurs existants
 * (changement de mot de passe, désactivation, etc.)
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
require_once __DIR__ . '/../API/config/admin_config.php';

// Initialiser les objets Auth et User
$auth = new Auth($pdo);
$user = new User($pdo);

// Définir des variables pour les messages
$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le jeton CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // Action de modification de mot de passe
        if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
            $admin_id = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (!$admin_id) {
                $error = "ID d'administrateur invalide.";
            } elseif ($new_password !== $confirm_password) {
                $error = "Les mots de passe ne correspondent pas.";
            } else {
                // Valider la robustesse du mot de passe
                $validation = validateStrongPassword($new_password);
                if (!$validation['valid']) {
                    $error = implode('. ', $validation['errors']);
                } else {
                    // Changer le mot de passe
                    if ($user->changePassword('administrateur', $admin_id, $new_password)) {
                        $message = "Le mot de passe a été modifié avec succès.";
                    } else {
                        $error = "Erreur lors du changement de mot de passe: " . $user->getErrorMessage();
                    }
                }
            }
        }
        
        // Action de désactivation/activation d'un compte
        else if (isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
            $admin_id = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (!$admin_id) {
                $error = "ID d'administrateur invalide.";
            } else {
                // S'assurer qu'il reste au moins un administrateur actif si désactivation
                if ($active === 0) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM administrateurs WHERE actif = 1 AND id != ?");
                    $stmt->execute([$admin_id]);
                    $activeCount = (int)$stmt->fetchColumn();
                    
                    if ($activeCount === 0) {
                        $error = "Impossible de désactiver le dernier administrateur actif.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE administrateurs SET actif = ? WHERE id = ?");
                        if ($stmt->execute([$active, $admin_id])) {
                            $message = "Le statut du compte a été mis à jour avec succès.";
                        } else {
                            $error = "Erreur lors de la mise à jour du statut.";
                        }
                    }
                } else {
                    // Activation - pas de restrictions particulières
                    $stmt = $pdo->prepare("UPDATE administrateurs SET actif = ? WHERE id = ?");
                    if ($stmt->execute([$active, $admin_id])) {
                        $message = "Le statut du compte a été mis à jour avec succès.";
                    } else {
                        $error = "Erreur lors de la mise à jour du statut.";
                    }
                }
            }
        }
    }
}

// Générer un nouveau jeton CSRF
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Récupérer la liste des administrateurs
try {
    $stmt = $pdo->query("SELECT * FROM administrateurs ORDER BY nom, prenom");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des administrateurs: " . $e->getMessage();
    $admins = [];
}

// Récupérer les informations de l'utilisateur administrateur connecté
$admin = $_SESSION['user'];
$admin_initials = strtoupper(mb_substr($admin['prenom'], 0, 1) . mb_substr($admin['nom'], 0, 1));

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des comptes administrateur - Pronote</title>
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
        
        .admin-container {
            max-width: 900px;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-primary, .btn-secondary {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 70%;
            max-width: 500px;
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
        
        .active-status {
            color: #28a745;
            font-weight: bold;
        }
        
        .inactive-status {
            color: #dc3545;
        }
        
        .password-requirements {
            margin-top: 10px;
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .current-user {
            font-weight: bold;
            background-color: #e8f4f8;
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
                <a href="admin_accounts.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span>Gestion des administrateurs</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="top-header">
            <div class="page-title">
                <h1>Gestion des comptes administrateur</h1>
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

        <div class="admin-container">
            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <h2>Liste des administrateurs</h2>
            
            <?php if (empty($admins)): ?>
                <p>Aucun administrateur trouvé.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Identifiant</th>
                            <th>Email</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): 
                            $isCurrent = $_SESSION['user']['identifiant'] === $admin['identifiant'];
                        ?>
                            <tr class="<?= $isCurrent ? 'current-user' : '' ?>">
                                <td><?= htmlspecialchars($admin['nom']) ?></td>
                                <td><?= htmlspecialchars($admin['prenom']) ?></td>
                                <td><?= htmlspecialchars($admin['identifiant']) ?></td>
                                <td><?= htmlspecialchars($admin['mail']) ?></td>
                                <td class="<?= $admin['actif'] ? 'active-status' : 'inactive-status' ?>">
                                    <?= $admin['actif'] ? 'Actif' : 'Inactif' ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-primary" onclick="openPasswordModal(<?= $admin['id'] ?>)">
                                            Changer le mot de passe
                                        </button>
                                        <?php if ($admin['actif']): ?>
                                            <button class="btn-danger" onclick="openToggleModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['nom'] . ' ' . $admin['prenom']) ?>', 0)">
                                                Désactiver
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-success" onclick="openToggleModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['nom'] . ' ' . $admin['prenom']) ?>', 1)">
                                                Activer
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour changer le mot de passe -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePasswordModal()">&times;</span>
        <h2>Changer le mot de passe</h2>
        <form method="post" action="" id="password-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="admin_id" id="password_admin_id" value="">
            
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" id="new_password" name="new_password" required minlength="12"
                       pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="12">
            </div>
            
            <div class="password-requirements">
                <p><strong>Le mot de passe doit contenir au moins :</strong></p>
                <ul>
                    <li>12 caractères</li>
                    <li>Une lettre majuscule</li>
                    <li>Une lettre minuscule</li>
                    <li>Un chiffre</li>
                    <li>Un caractère spécial</li>
                </ul>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closePasswordModal()">Annuler</button>
                <button type="submit" class="btn-primary">Changer le mot de passe</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal pour activer/désactiver un compte -->
<div id="toggleModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeToggleModal()">&times;</span>
        <h2 id="toggle-title">Modifier le statut du compte</h2>
        <p id="toggle-message"></p>
        <form method="post" action="" id="toggle-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="admin_id" id="toggle_admin_id" value="">
            <input type="hidden" name="active" id="toggle_active" value="">
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeToggleModal()">Annuler</button>
                <button type="submit" class="btn-primary" id="toggle-submit">Confirmer</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Fonctions pour la modal du changement de mot de passe
    function openPasswordModal(adminId) {
        document.getElementById('password_admin_id').value = adminId;
        document.getElementById('passwordModal').style.display = 'block';
    }
    
    function closePasswordModal() {
        document.getElementById('password-form').reset();
        document.getElementById('passwordModal').style.display = 'none';
    }
    
    // Fonctions pour la modal d'activation/désactivation
    function openToggleModal(adminId, adminName, active) {
        document.getElementById('toggle_admin_id').value = adminId;
        document.getElementById('toggle_active').value = active;
        
        const title = active ? 'Activer le compte' : 'Désactiver le compte';
        const message = active 
            ? `Êtes-vous sûr de vouloir activer le compte de ${adminName} ?` 
            : `Êtes-vous sûr de vouloir désactiver le compte de ${adminName} ?`;
        const submitText = active ? 'Activer' : 'Désactiver';
        const submitClass = active ? 'btn-success' : 'btn-danger';
        
        document.getElementById('toggle-title').innerText = title;
        document.getElementById('toggle-message').innerText = message;
        document.getElementById('toggle-submit').innerText = submitText;
        document.getElementById('toggle-submit').className = submitClass;
        
        document.getElementById('toggleModal').style.display = 'block';
    }
    
    function closeToggleModal() {
        document.getElementById('toggle-form').reset();
        document.getElementById('toggleModal').style.display = 'none';
    }
    
    // Vérification de la correspondance des mots de passe
    document.getElementById('password-form').addEventListener('submit', function(e) {
        const password = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            alert("Les mots de passe ne correspondent pas.");
            e.preventDefault();
            return false;
        }
        
        // Vérifier la robustesse du mot de passe côté client
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);
        const isLongEnough = password.length >= 12;
        
        if (!hasUppercase || !hasLowercase || !hasNumber || !hasSpecial || !isLongEnough) {
            alert("Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.");
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Fermer les modals si on clique en dehors
    window.onclick = function(event) {
        const passwordModal = document.getElementById('passwordModal');
        const toggleModal = document.getElementById('toggleModal');
        
        if (event.target === passwordModal) {
            closePasswordModal();
        }
        
        if (event.target === toggleModal) {
            closeToggleModal();
        }
    }
</script>
</body>
</html>
