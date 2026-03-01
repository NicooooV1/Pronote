<?php
/**
 * supprimer_devoir.php — Confirmation + suppression d'un devoir
 *
 * Allégé : utilise DevoirService pour tout (lecture, permissions, suppression).
 * SEC-2 : validateCSRFToken
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/DevoirService.php';

$pdo = getPDO();
requireAuth();

if (!canManageDevoirs()) {
    setFlashMessage('error', "Accès refusé.");
    redirectTo('cahierdetextes.php');
}

$service       = new DevoirService($pdo);
$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();

// Vérifier l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectTo('cahierdetextes.php');
}
$id = intval($_GET['id']);

$devoir = $service->getDevoirById($id);
if (!$devoir) {
    setFlashMessage('error', "Le devoir demandé n'existe pas.");
    redirectTo('cahierdetextes.php');
}

if (!$service->canUserEdit($devoir, $user_fullname, $user_role)) {
    setFlashMessage('error', "Vous n'avez pas les droits pour supprimer ce devoir.");
    redirectTo('cahierdetextes.php');
}

$csrf_token = generateCSRFToken();
$status     = $service->computeStatus($devoir['date_rendu']);

// ── POST : exécuter la suppression ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', "Erreur de validation. Veuillez réessayer.");
        redirectTo('cahierdetextes.php');
    }

    try {
        $service->delete($id);
        setFlashMessage('success', "Le devoir a été supprimé avec succès.");
    } catch (\PDOException $e) {
        logError("Erreur suppression devoir: " . $e->getMessage());
        setFlashMessage('error', "Erreur lors de la suppression.");
    }
    redirectTo('cahierdetextes.php');
}

// ── GET : afficher la confirmation ──
$pageTitle = "Supprimer un devoir";

include 'includes/header.php';
?>

            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2>Supprimer un devoir</h2>
                    <p>Vous êtes sur le point de supprimer définitivement ce devoir</p>
                </div>
                <div class="welcome-logo"><i class="fas fa-trash-alt"></i></div>
            </div>

            <div class="dashboard-content">
                <div class="alert-banner alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Attention : cette action est irréversible. Le devoir et ses pièces jointes seront définitivement supprimés.</p>
                </div>

                <div class="devoir-card <?= $status['class'] ?>" style="margin-top: 20px;">
                    <div class="card-header">
                        <div class="devoir-title">
                            <i class="fas fa-book"></i> <?= htmlspecialchars($devoir['titre']) ?>
                            <?php if ($status['class']): ?>
                                <span class="badge badge-<?= $status['class'] ?>"><?= $status['label'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="devoir-meta">Ajouté le: <?= date('d/m/Y', strtotime($devoir['date_ajout'])) ?></div>
                    </div>

                    <div class="card-body">
                        <div class="devoir-info-grid">
                            <div class="devoir-info"><div class="info-label">Classe:</div><div class="info-value"><?= htmlspecialchars($devoir['classe']) ?></div></div>
                            <div class="devoir-info"><div class="info-label">Matière:</div><div class="info-value"><?= htmlspecialchars($devoir['nom_matiere']) ?></div></div>
                            <div class="devoir-info"><div class="info-label">Professeur:</div><div class="info-value"><?= htmlspecialchars($devoir['nom_professeur']) ?></div></div>
                            <div class="devoir-info"><div class="info-label">Date de rendu:</div><div class="info-value date-rendu <?= $status['class'] ?>"><?= date('d/m/Y', strtotime($devoir['date_rendu'])) ?></div></div>
                        </div>

                        <div class="devoir-description">
                            <h4>Description:</h4>
                            <p><?= nl2br(htmlspecialchars($devoir['description'])) ?></p>
                        </div>

                        <form method="post" style="margin-top: 20px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div class="form-actions">
                                <a href="cahierdetextes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Annuler</a>
                                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Confirmer la suppression</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

<?php
include 'includes/footer.php';
ob_end_flush();
?>
