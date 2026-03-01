<?php
/**
 * Supprimer un événement — Module Agenda
 * Nettoyé : EventRepository::delete(), canDeleteEvent(), POST uniquement,
 *           Post-Redirect-Get avec flash messages.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/EventRepository.php';

requireAuth();

$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();
$repo          = new EventRepository($pdo);

// Accepter l'id depuis POST (modal details) ou GET (lien direct)
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    setFlashMessage('error', "Identifiant d'événement invalide.");
    header('Location: agenda.php');
    exit;
}

// Récupérer l'événement
$evenement = $repo->findById($id);
if (!$evenement) {
    setFlashMessage('error', "L'événement n'existe pas.");
    header('Location: agenda.php');
    exit;
}

// Permission (canDeleteEvent de auth.php)
if (!canDeleteEvent($evenement)) {
    setFlashMessage('error', "Vous n'avez pas les droits pour supprimer cet événement.");
    header('Location: details_evenement.php?id=' . $id);
    exit;
}

/* ── POST → suppression effective + redirect ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', "Erreur de sécurité. Veuillez réessayer.");
        header('Location: details_evenement.php?id=' . $id);
        exit;
    }

    try {
        $repo->delete($id);
        error_log("Événement ID=$id ('{$evenement['titre']}') supprimé par $user_fullname");
        setFlashMessage('success', "L'événement a été supprimé avec succès.");
        header('Location: agenda.php');
        exit;
    } catch (Exception $e) {
        error_log("Erreur suppression événement $id: " . $e->getMessage());
        setFlashMessage('error', "Erreur lors de la suppression.");
        header('Location: details_evenement.php?id=' . $id);
        exit;
    }
}

/* ── GET → page de confirmation avec formulaire POST ── */
$type_info   = EventRepository::getTypeInfo($evenement['type_evenement']);
$csrf_token  = csrf_token();

try {
    $date_debut = new DateTime($evenement['date_debut']);
    $date_fin   = new DateTime($evenement['date_fin']);
} catch (Exception $e) {
    $date_debut = $date_fin = new DateTime();
}
$fmt_d = 'd/m/Y';
$fmt_h = 'H:i';

$pageTitle = "Supprimer l'événement";
include 'includes/header.php';
?>

<div class="calendar-navigation">
    <a href="details_evenement.php?id=<?= (int) $id ?>" class="back-button">
        <i class="fas fa-arrow-left"></i> Retour aux détails
    </a>
</div>

<div class="event-delete-container">
    <div class="event-delete-header">
        <h1>Supprimer l'événement</h1>
    </div>

    <div class="event-delete-body">
        <div class="event-summary">
            <h2><?= htmlspecialchars($evenement['titre']) ?></h2>
            <div class="event-summary-detail">
                <span class="event-type-badge" style="background-color: <?= htmlspecialchars($type_info['couleur']) ?>">
                    <i class="fas fa-<?= htmlspecialchars($type_info['icone']) ?>"></i>
                    <?= htmlspecialchars($type_info['nom']) ?>
                </span>
            </div>
            <div class="event-summary-detail">
                <i class="far fa-calendar-alt"></i>
                <?php if ($date_debut->format('Y-m-d') === $date_fin->format('Y-m-d')): ?>
                    Le <?= $date_debut->format($fmt_d) ?> de <?= $date_debut->format($fmt_h) ?> à <?= $date_fin->format($fmt_h) ?>
                <?php else: ?>
                    Du <?= $date_debut->format($fmt_d . ' à ' . $fmt_h) ?>
                    au <?= $date_fin->format($fmt_d . ' à ' . $fmt_h) ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($evenement['lieu'])): ?>
            <div class="event-summary-detail">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($evenement['lieu']) ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="event-delete-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Attention : cette action est irréversible. L'événement sera définitivement supprimé.</p>
        </div>

        <form method="post" class="delete-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="form-actions">
                <a href="details_evenement.php?id=<?= (int) $id ?>" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
            </div>
        </form>
    </div>
</div>

<?php
include 'includes/footer.php';
ob_end_flush();
?>
