<?php
// Inclure l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification et les droits administrateur
requireAuth();
requireRole('administrateur');

// Récupérer la connexion DB et l'utilisateur
$pdo = getPDO();
$admin = getCurrentUser();
$admin_initials = getUserInitials();

// Charger les classes nécessaires
require_once __DIR__ . '/../login/src/auth.php';
require_once __DIR__ . '/../login/src/user.php';

$error = '';
$success = '';
$requests = [];
$selectedUser = null;
$newPassword = '';

// Traitement des actions sur les demandes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_request'])) {
        // Approuver une demande
        $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $userType = isset($_POST['user_type']) ? $_POST['user_type'] : '';
        
        if ($requestId > 0 && $userId > 0 && !empty($userType)) {
            // Générer un nouveau mot de passe
            $newPassword = generateRandomPassword(10);
            
            // Mettre à jour le mot de passe de l'utilisateur
            $user = new User($pdo);
            if ($user->changePassword($userType, $userId, $newPassword)) {
                // Mettre à jour le statut de la demande
                $stmt = $pdo->prepare("UPDATE demandes_reinitialisation SET status = 'approved', date_traitement = NOW(), admin_id = ? WHERE id = ?");
                if ($stmt->execute([$admin['id'], $requestId])) {
                    $success = "Le mot de passe a été réinitialisé avec succès.";
                    
                    // Récupérer les informations de l'utilisateur pour l'affichage
                    $selectedUser = $user->getById($userId, $userType);
                    
                    // Journaliser l'action
                    error_log("Réinitialisation de mot de passe approuvée: ID utilisateur=$userId, Type=$userType, Admin={$admin['identifiant']}");
                } else {
                    $error = "Erreur lors de la mise à jour du statut de la demande.";
                }
            } else {
                $error = "Erreur lors de la réinitialisation du mot de passe: " . $user->getErrorMessage();
            }
        } else {
            $error = "Paramètres invalides pour la réinitialisation.";
        }
    } elseif (isset($_POST['reject_request'])) {
        // Rejeter une demande
        $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        
        if ($requestId > 0) {
            $stmt = $pdo->prepare("UPDATE demandes_reinitialisation SET status = 'rejected', date_traitement = NOW(), admin_id = ? WHERE id = ?");
            if ($stmt->execute([$admin['id'], $requestId])) {
                $success = "La demande a été rejetée.";
                
                // Journaliser l'action
                error_log("Réinitialisation de mot de passe rejetée: ID demande=$requestId, Admin={$admin['identifiant']}");
            } else {
                $error = "Erreur lors de la mise à jour du statut de la demande.";
            }
        } else {
            $error = "ID de demande invalide.";
        }
    }
}

// Récupérer les demandes en attente
try {
    $stmt = $pdo->query("
        SELECT r.*, 
               CASE 
                   WHEN r.user_type = 'eleve' THEN (SELECT CONCAT(prenom, ' ', nom) FROM eleves WHERE id = r.user_id)
                   WHEN r.user_type = 'parent' THEN (SELECT CONCAT(prenom, ' ', nom) FROM parents WHERE id = r.user_id)
                   WHEN r.user_type = 'professeur' THEN (SELECT CONCAT(prenom, ' ', nom) FROM professeurs WHERE id = r.user_id)
                   WHEN r.user_type = 'vie_scolaire' THEN (SELECT CONCAT(prenom, ' ', nom) FROM vie_scolaire WHERE id = r.user_id)
                   ELSE 'Utilisateur inconnu'
               END AS nom_complet,
               CASE 
                   WHEN r.user_type = 'eleve' THEN (SELECT identifiant FROM eleves WHERE id = r.user_id)
                   WHEN r.user_type = 'parent' THEN (SELECT identifiant FROM parents WHERE id = r.user_id)
                   WHEN r.user_type = 'professeur' THEN (SELECT identifiant FROM professeurs WHERE id = r.user_id)
                   WHEN r.user_type = 'vie_scolaire' THEN (SELECT identifiant FROM vie_scolaire WHERE id = r.user_id)
                   ELSE 'Inconnu'
               END AS identifiant,
               CASE 
                   WHEN r.user_type = 'eleve' THEN (SELECT mail FROM eleves WHERE id = r.user_id)
                   WHEN r.user_type = 'parent' THEN (SELECT mail FROM parents WHERE id = r.user_id)
                   WHEN r.user_type = 'professeur' THEN (SELECT mail FROM professeurs WHERE id = r.user_id)
                   WHEN r.user_type = 'vie_scolaire' THEN (SELECT mail FROM vie_scolaire WHERE id = r.user_id)
                   ELSE 'Inconnu'
               END AS email
        FROM demandes_reinitialisation r
        WHERE r.status = 'pending'
        ORDER BY r.date_demande DESC
    ");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des demandes de réinitialisation: " . $e->getMessage();
}

/**
 * Génère un mot de passe aléatoire
 */
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $password;
}

// Titre de la page
$pageTitle = "Demandes de réinitialisation de mot de passe";
?>

<?php
$currentPage = 'reset_requests';
ob_start();
?>
<style>
        .requests-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .requests-table th, .requests-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .requests-table th { background-color: #f5f5f5; font-weight: 500; }
        .requests-table tr:hover { background-color: rgba(15, 76, 129, 0.05); }
        .request-date { white-space: nowrap; font-size: 14px; color: #8e9aaf; }
        .request-type { text-transform: capitalize; font-size: 14px; }
        .request-type-eleve { color: #f59e0b; }
        .request-type-parent { color: #3b82f6; }
        .request-type-professeur { color: #10b981; }
        .request-type-vie_scolaire { color: #8b5cf6; }
        .action-buttons { display: flex; gap: 10px; }
        .btn-sm { padding: 6px 10px; font-size: 13px; }
        .empty-state { text-align: center; padding: 40px 0; color: #8e9aaf; }
        .empty-state i { font-size: 40px; margin-bottom: 15px; opacity: 0.5; }
        .user-credentials {
            background-color: #f5f5f5;
            border: 1px dashed #ccc;
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            font-family: monospace;
        }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include 'includes/header.php';
?>

        <div class="requests-container">
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
            
            <?php if ($selectedUser && !empty($newPassword)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-key"></i>
                    <div>
                        <p>Le mot de passe de <strong><?= htmlspecialchars($selectedUser['prenom'] . ' ' . $selectedUser['nom']) ?></strong> a été réinitialisé.</p>
                        <div class="user-credentials">
                            <p><strong>Identifiant :</strong> <?= htmlspecialchars($selectedUser['identifiant']) ?></p>
                            <p><strong>Nouveau mot de passe :</strong> <?= htmlspecialchars($newPassword) ?></p>
                            <p class="warning">Veuillez communiquer ces informations à l'utilisateur de façon sécurisée.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Liste des demandes en attente -->
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>Aucune demande en attente</h3>
                    <p>Il n'y a actuellement aucune demande de réinitialisation de mot de passe à traiter.</p>
                </div>
            <?php else: ?>
                <h3>Demandes en attente (<?= count($requests) ?>)</h3>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Type</th>
                            <th>Email</th>
                            <th>Date de demande</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($request['nom_complet']) ?></strong><br>
                                    <small><?= htmlspecialchars($request['identifiant']) ?></small>
                                </td>
                                <td class="request-type request-type-<?= htmlspecialchars($request['user_type']) ?>">
                                    <?= htmlspecialchars(ucfirst($request['user_type'])) ?>
                                </td>
                                <td><?= htmlspecialchars($request['email']) ?></td>
                                <td class="request-date">
                                    <?= date('d/m/Y H:i', strtotime($request['date_demande'])) ?>
                                </td>
                                <td class="action-buttons">
                                    <form method="post" action="" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $request['user_id'] ?>">
                                        <input type="hidden" name="user_type" value="<?= $request['user_type'] ?>">
                                        <button type="submit" name="approve_request" class="btn btn-success btn-sm" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?');">
                                            <i class="fas fa-check"></i> Approuver
                                        </button>
                                    </form>
                                    
                                    <form method="post" action="" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <button type="submit" name="reject_request" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Êtes-vous sûr de vouloir rejeter cette demande ?');">
                                            <i class="fas fa-times"></i> Rejeter
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
<?php include 'includes/footer.php'; ?>
