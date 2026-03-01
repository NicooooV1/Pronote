<?php
/**
 * M14 – Réunions — Mes RDV (parent)
 */
$pageTitle = 'Mes rendez-vous';
require_once __DIR__ . '/includes/header.php';

if (!isParent()) { header('Location: reunions.php'); exit; }

$mesRdv = $reunionService->getReservationsParent(getUserId());

// Annulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $reunionService->annulerReservation((int)$_POST['reservation_id']);
    $_SESSION['success_message'] = 'Réservation annulée.';
    header('Location: mes_rdv.php');
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-handshake"></i> Mes rendez-vous</h1>
        <a href="reunions.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Réunions</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (empty($mesRdv)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-check"></i>
            <p>Vous n'avez aucun rendez-vous réservé.</p>
            <a href="reunions.php" class="btn btn-primary">Voir les réunions</a>
        </div>
    <?php else: ?>
        <div class="rdv-list">
            <?php foreach ($mesRdv as $rdv): ?>
            <div class="rdv-card">
                <div class="rdv-date">
                    <div class="rdv-day"><?= date('d', strtotime($rdv['reunion_date'])) ?></div>
                    <div class="rdv-month"><?= strftime('%b', strtotime($rdv['reunion_date'])) ?: date('M', strtotime($rdv['reunion_date'])) ?></div>
                </div>
                <div class="rdv-info">
                    <h3><?= htmlspecialchars($rdv['reunion_titre']) ?></h3>
                    <p><i class="fas fa-user-tie"></i> <?= htmlspecialchars($rdv['prof_prenom'] . ' ' . $rdv['prof_nom']) ?></p>
                    <p><i class="fas fa-clock"></i> <?= substr($rdv['heure_debut'], 0, 5) ?> — <?= substr($rdv['heure_fin'], 0, 5) ?></p>
                    <p><i class="fas fa-child"></i> <?= htmlspecialchars($rdv['eleve_prenom'] . ' ' . $rdv['eleve_nom']) ?></p>
                    <?php if ($rdv['salle']): ?><p><i class="fas fa-door-open"></i> <?= htmlspecialchars($rdv['salle']) ?></p><?php endif; ?>
                </div>
                <div class="rdv-actions">
                    <?php if ($rdv['statut'] === 'confirmee'): ?>
                    <span class="badge badge-success">Confirmé</span>
                    <form method="post" onsubmit="return confirm('Annuler ce rendez-vous ?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="reservation_id" value="<?= $rdv['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> Annuler</button>
                    </form>
                    <?php else: ?>
                    <span class="badge badge-danger">Annulé</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
