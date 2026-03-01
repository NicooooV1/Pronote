<?php
/**
 * Devoirs en ligne — Voir son rendu (élève)
 */
require_once __DIR__ . '/includes/RenduService.php';
$currentPage = 'voir_rendu';
$pageTitle = 'Mon rendu';
require_once __DIR__ . '/includes/header.php';
requireAuth();

$pdo = getPDO();
$service = new RenduService($pdo);
$devoirId = (int)($_GET['devoir'] ?? 0);
$rendu = $service->getRendu($devoirId, $user['id']);
$devoir = $service->getDevoir($devoirId);

if (!$rendu || !$devoir) {
    echo '<div class="alert alert-error">Rendu introuvable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> Mon rendu</h1>
    <a href="mes_devoirs.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<div class="devoir-detail-card">
    <h2><?= htmlspecialchars($devoir['titre']) ?></h2>
    <div class="devoir-meta">
        <span><i class="fas fa-book"></i> <?= htmlspecialchars($devoir['nom_matiere']) ?></span>
        <span><i class="fas fa-clock"></i> <?= formatDate($devoir['date_rendu']) ?></span>
        <?= RenduService::statutBadge($rendu['statut']) ?>
        <?php if ($rendu['en_retard']): ?><span class="badge badge-danger">En retard</span><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Votre travail</h3></div>
    <div class="card-body">
        <?php if ($rendu['contenu']): ?>
        <div class="rendu-contenu"><?= nl2br(htmlspecialchars($rendu['contenu'])) ?></div>
        <?php endif; ?>
        <?php if ($rendu['fichier_nom']): ?>
        <div class="rendu-fichier"><a href="../<?= htmlspecialchars($rendu['fichier_chemin']) ?>" target="_blank"><i class="fas fa-download"></i> <?= htmlspecialchars($rendu['fichier_nom']) ?></a></div>
        <?php endif; ?>
        <p class="text-muted">Soumis le <?= formatDateTime($rendu['date_rendu']) ?></p>
    </div>
</div>

<?php if ($rendu['statut'] === 'corrige'): ?>
<div class="card correction-card">
    <div class="card-header"><h3><i class="fas fa-check-circle"></i> Correction</h3></div>
    <div class="card-body">
        <?php if ($rendu['note'] !== null): ?>
        <div class="note-display">
            <span class="note-value"><?= $rendu['note'] ?></span><span class="note-sur">/<?= $rendu['note_sur'] ?></span>
        </div>
        <?php endif; ?>
        <?php if ($rendu['commentaire_prof']): ?>
        <div class="prof-commentaire"><?= nl2br(htmlspecialchars($rendu['commentaire_prof'])) ?></div>
        <?php endif; ?>
        <p class="text-muted">Corrigé le <?= formatDateTime($rendu['date_correction']) ?></p>
    </div>
</div>
<?php elseif ($rendu['statut'] === 'a_refaire'): ?>
<div class="card" style="border-left:3px solid #d97706">
    <div class="card-header"><h3><i class="fas fa-redo"></i> À refaire</h3></div>
    <div class="card-body">
        <p><?= nl2br(htmlspecialchars($rendu['commentaire_prof'] ?? 'Le professeur vous demande de refaire ce travail.')) ?></p>
        <a href="rendre.php?devoir=<?= $devoirId ?>" class="btn btn-primary"><i class="fas fa-upload"></i> Soumettre à nouveau</a>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
