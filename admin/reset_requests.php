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

// Récupérer les informations de l'utilisateur administrateur
$admin = $_SESSION['user'];
$admin_initials = strtoupper(mb_substr($admin['prenom'], 0, 1) . mb_substr($admin['nom'], 0, 1));

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
        
        .requests-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-light);
        }
        
        .requests-table th,
        .requests-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .requests-table th {
            background-color: #f5f5f5;
            font-weight: 500;
        }
        
        .requests-table tr:hover {
            background-color: rgba(15, 76, 129, 0.05);
        }
        
        .request-date {
            white-space: nowrap;
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .request-type {
            text-transform: capitalize;
            font-size: 14px;
        }
        
        .request-type-eleve {
            color: var(--accent-notes);
        }
        
        .request-type-parent {
            color: var(--accent-agenda);
        }
        
        .request-type-professeur {
            color: var(--accent-cahier);
        }
        
        .request-type-vie_scolaire {
            color: var(--accent-messagerie);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 6px 10px;
            font-size: 13px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .user-credentials {
            background-color: #f5f5f5;
            border: 1px dashed #ccc;
            padding: var(--space-md);
            margin: var(--space-md) 0;
            border-radius: var(--radius-sm);
            font-family: monospace;
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
                <a href="reset_requests.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Demandes de réinitialisation</span>
                </a>
                <a href="admin_accounts.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span>Gestion des administrateurs</span>
                </a>
                <a href="user_accounts.php" class="sidebar-nav-item">
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
    </div>
</div>

</body>
</html>
