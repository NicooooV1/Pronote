<?php
// Démarrer la mise en mémoire tampon de sortie
ob_start();

// Inclusion de l'API centralisée
require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/auth.php';

// Vérifier l'authentification et les permissions
requireAuth();
if (!canManageDevoirs()) {
  header('Location: cahierdetextes.php');
  exit;
}

// Récupérer les informations de l'utilisateur connecté
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Vérifier que l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header('Location: cahierdetextes.php');
  exit;
}

// Générer ou vérifier le token CSRF
$csrf_token = csrf_token();

// Vérifier le token CSRF si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = "Erreur de validation du formulaire. Veuillez réessayer.";
    header('Location: cahierdetextes.php');
    exit;
  }
}

$id = intval($_GET['id']); // Sanitize with intval to ensure numeric value

// Vérifier que le devoir existe avant d'essayer de le supprimer
$check_stmt = $pdo->prepare('SELECT * FROM devoirs WHERE id = ?');
$check_stmt->execute([$id]);
$devoir = $check_stmt->fetch();

if (!$devoir) {
  // Le devoir n'existe pas
  $_SESSION['error_message'] = "Le devoir demandé n'existe pas.";
  header('Location: cahierdetextes.php?error=notfound');
  exit;
}

// Si l'utilisateur est un professeur (et pas un admin ou vie scolaire), 
// il peut seulement supprimer ses propres devoirs
if (isTeacher() && !isAdmin() && !isVieScolaire()) {
  if ($devoir['nom_professeur'] !== $user_fullname) {
    // Le devoir n'appartient pas au professeur connecté
    $_SESSION['error_message'] = "Vous n'avez pas les droits nécessaires pour supprimer ce devoir.";
    header('Location: cahierdetextes.php?error=unauthorized');
    exit;
  }
}

// Calculer l'état du devoir
$date_rendu = new DateTime($devoir['date_rendu']);
$aujourdhui = new DateTime();
$diff = $aujourdhui->diff($date_rendu);

$statusClass = '';
$statusText = '';

if ($date_rendu < $aujourdhui) {
    $statusClass = 'expired';
    $statusText = 'Expiré';
} elseif ($diff->days <= 3) {
    $statusClass = 'urgent';
    $statusText = 'Urgent (< 3 jours)';
} elseif ($diff->days <= 7) {
    $statusClass = 'soon';
    $statusText = 'Cette semaine';
} else {
    $statusText = 'À venir';
}

// Variables pour le template
$pageTitle = "Supprimer un devoir";

// Si c'est une requête GET, afficher la page de confirmation
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

include 'includes/header.php';
?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2>Supprimer un devoir</h2>
                <p>Vous êtes sur le point de supprimer définitivement ce devoir</p>
            </div>
            <div class="welcome-logo">
                <i class="fas fa-trash-alt"></i>
            </div>
        </div>
        
        <!-- Main Dashboard Content -->
        <div class="dashboard-content">
            <div class="alert-banner alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Attention : Cette action est irréversible. Le devoir sera définitivement supprimé de la base de données.</p>
            </div>
            
            <div class="devoir-card <?= $statusClass ?>" style="margin-top: 20px;">
                <div class="card-header">
                    <div class="devoir-title">
                        <i class="fas fa-book"></i> <?= htmlspecialchars($devoir['titre']) ?>
                        <?php if ($statusClass): ?>
                            <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="devoir-meta">
                        Ajouté le: <?= date('d/m/Y', strtotime($devoir['date_ajout'])) ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="devoir-info-grid">
                        <div class="devoir-info">
                            <div class="info-label">Classe:</div>
                            <div class="info-value"><?= htmlspecialchars($devoir['classe']) ?></div>
                        </div>
                        
                        <div class="devoir-info">
                            <div class="info-label">Matière:</div>
                            <div class="info-value"><?= htmlspecialchars($devoir['nom_matiere']) ?></div>
                        </div>
                        
                        <div class="devoir-info">
                            <div class="info-label">Professeur:</div>
                            <div class="info-value"><?= htmlspecialchars($devoir['nom_professeur']) ?></div>
                        </div>
                        
                        <div class="devoir-info">
                            <div class="info-label">Date de rendu:</div>
                            <div class="info-value date-rendu <?= $statusClass ?>">
                                <?= date('d/m/Y', strtotime($devoir['date_rendu'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="devoir-description">
                        <h4>Description:</h4>
                        <p><?= nl2br(htmlspecialchars($devoir['description'])) ?></p>
                    </div>
                    
                    <form method="post" style="margin-top: 20px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="form-actions">
                            <a href="cahierdetextes.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Confirmer la suppression
                            </button>
                        </div>
                    </form>
                </div>
            </div>

<?php include 'includes/footer.php'; ?>

<?php
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Traitement de la suppression
  try {
    // Supprimer le devoir
    $stmt = $pdo->prepare('DELETE FROM devoirs WHERE id = ?');
    $stmt->execute([$id]);
    
    $_SESSION['success_message'] = "Le devoir a été supprimé avec succès.";
    header('Location: cahierdetextes.php?success=deleted');
  } catch (PDOException $e) {
    // Journal d'erreurs
    error_log("Erreur de suppression dans supprimer_devoir.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la suppression du devoir.";
    header('Location: cahierdetextes.php?error=dbfailed');
  }
}
exit;

// Terminer la mise en mémoire tampon et envoyer la sortie
ob_end_flush();
?>