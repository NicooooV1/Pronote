<?php
/**
 * Page de gestion des comptes administrateur
 * Cette page permet de gérer les comptes administrateurs existants
 * (changement de mot de passe, désactivation, etc.)
 */

// Inclure l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification et les droits administrateur
requireAuth();
requireRole('administrateur');

// Récupérer l'utilisateur actuel et la connexion DB
$currentUser = getCurrentUser();
$pdo = getPDO();

// Charger les classes Auth et User pour la gestion des comptes
require_once __DIR__ . '/../login/src/auth.php';
require_once __DIR__ . '/../login/src/user.php';

// Initialiser les objets Auth et User
$auth = new Auth($pdo);
$user = new User($pdo);

// Définir des variables pour les messages
$message = '';
$error = '';

// Générer un nouveau jeton CSRF si inexistant
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
            $active = isset($_POST['active']) && $_POST['active'] == '1' ? 1 : 0;
            
            if (!$admin_id) {
                $error = "ID d'administrateur invalide.";
            } else {
                // S'assurer qu'il reste au moins un administrateur actif si désactivation
                if ($active === 0) {
                    try {
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
                    } catch (PDOException $e) {
                        $error = "Erreur de base de données: " . $e->getMessage();
                    }
                } else {
                    // Activation - pas de restrictions particulières
                    try {
                        $stmt = $pdo->prepare("UPDATE administrateurs SET actif = ? WHERE id = ?");
                        if ($stmt->execute([$active, $admin_id])) {
                            $message = "Le statut du compte a été mis à jour avec succès.";
                        } else {
                            $error = "Erreur lors de la mise à jour du statut.";
                        }
                    } catch (PDOException $e) {
                        $error = "Erreur de base de données: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Récupérer la liste des administrateurs
try {
    $stmt = $pdo->query("SELECT * FROM administrateurs ORDER BY nom, prenom");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure the 'actif' field exists for all admins with a default value
    foreach ($admins as &$admin_row) {
        if (!isset($admin_row['actif'])) {
            $admin_row['actif'] = 1; // Default to active if not set
        }
    }
    unset($admin_row); // Break the reference
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des administrateurs: " . $e->getMessage();
    $admins = [];
}

// Récupérer les informations de l'utilisateur administrateur connecté
$admin = getCurrentUser();
$admin_initials = getUserInitials();

<?php
$pageTitle = 'Gestion des comptes administrateur';
$currentPage = 'admin_accounts';
ob_start();
?>
<style>
        .admin-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f5f7fa;
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f9fafb; }
        .action-buttons { display: flex; gap: 10px; }
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
            margin: 10% auto;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 0.6;
        }
        .close:hover { color: black; }
        .active-status { color: #28a745; font-weight: bold; }
        .inactive-status { color: #dc3545; }
        .password-requirements {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            font-size: 14px;
            color: #6c757d;
        }
        .password-requirements ul { margin-top: 8px; padding-left: 25px; }
        .password-requirements li { margin-bottom: 5px; }
        .current-user { font-weight: 600; background-color: #e8f4f8; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include 'includes/header.php';
?>

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
                        <?php foreach ($admins as $admin_user): 
                            $currentAdmin = getCurrentUser();
                            $isCurrent = ($currentAdmin['identifiant'] ?? '') === $admin_user['identifiant'];
                            $isActive = isset($admin_user['actif']) ? (bool)$admin_user['actif'] : true;
                        ?>
                            <tr class="<?= $isCurrent ? 'current-user' : '' ?>">
                                <td><?= htmlspecialchars($admin_user['nom']) ?></td>
                                <td><?= htmlspecialchars($admin_user['prenom']) ?></td>
                                <td><?= htmlspecialchars($admin_user['identifiant']) ?></td>
                                <td><?= htmlspecialchars($admin_user['mail']) ?></td>
                                <td class="<?= $isActive ? 'active-status' : 'inactive-status' ?>">
                                    <?= $isActive ? 'Actif' : 'Inactif' ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-primary" onclick="openPasswordModal(<?= $admin_user['id'] ?>)">
                                            Changer le mot de passe
                                        </button>
                                        <?php if ($isActive): ?>
                                            <button class="btn-danger" onclick="openToggleModal(<?= $admin_user['id'] ?>, '<?= htmlspecialchars($admin_user['nom'] . ' ' . $admin_user['prenom']) ?>', 0)">
                                                Désactiver
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-success" onclick="openToggleModal(<?= $admin_user['id'] ?>, '<?= htmlspecialchars($admin_user['nom'] . ' ' . $admin_user['prenom']) ?>', 1)">
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
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
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
<?php include 'includes/footer.php'; ?>
