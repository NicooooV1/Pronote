<?php
// Inclure l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification et les droits administrateur
requireAuth();
requireRole('administrateur');

// Récupérer la connexion DB
$pdo = getPDO();

// Charger les classes nécessaires
require_once __DIR__ . '/../login/src/auth.php';
require_once __DIR__ . '/../login/src/user.php';

// Initialisation
$auth = new Auth($pdo);
$user = new User($pdo);
$admin = getCurrentUser();
$admin_initials = getUserInitials();

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

<?php
$currentPage = 'reset_password';
ob_start();
?>
<style>
        .auth-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .search-form { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-input { flex: 1; min-width: 200px; }
        .form-select { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .users-list { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .users-list th, .users-list td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .users-list th { background: #f5f7fa; font-weight: 600; color: #4a5568; font-size: 14px; }
        .users-list tr:hover { background: #f9fafb; }
        .btn-sm { padding: 6px 10px; font-size: 13px; }
        .user-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .user-detail { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
        .user-label { font-weight: 500; width: 120px; color: #666; }
        .user-value { color: #333; }
        .credentials-info { background: #f5f5f5; border: 1px dashed #ccc; padding: 15px; margin: 15px 0; border-radius: 6px; font-family: monospace; }
        .input-group { position: relative; }
        .input-group-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999; }
        .input-with-icon { padding-left: 35px !important; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include 'includes/header.php';
?>

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

<?php include 'includes/footer.php'; ?>
