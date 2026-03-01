<?php
/**
 * Devoirs en ligne — Soumettre un rendu (élève)
 */
require_once __DIR__ . '/includes/RenduService.php';
$currentPage = 'rendre';
$pageTitle = 'Rendre un devoir';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isStudent()) {
    header('Location: mes_devoirs.php');
    exit;
}

$pdo = getPDO();
$service = new RenduService($pdo);
$devoirId = (int)($_GET['devoir'] ?? 0);
$devoir = $service->getDevoir($devoirId);

if (!$devoir) {
    echo '<div class="alert alert-error">Devoir introuvable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$existing = $service->getRendu($devoirId, $user['id']);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $contenu = trim($_POST['contenu'] ?? '');
    $fichier = $_FILES['fichier'] ?? null;
    
    try {
        $service->soumettre($devoirId, $user['id'], $contenu, $fichier);
        $message = 'Votre travail a été soumis avec succès !';
        $messageType = 'success';
        $existing = $service->getRendu($devoirId, $user['id']);
    } catch (Exception $e) {
        $message = 'Erreur : ' . $e->getMessage();
        $messageType = 'error';
    }
}

$isPast = strtotime($devoir['date_rendu']) < time();
?>

<div class="page-header">
    <h1><i class="fas fa-upload"></i> Rendre le devoir</h1>
    <a href="mes_devoirs.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>"><i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="devoir-detail-card">
    <div class="devoir-info">
        <h2><?= htmlspecialchars($devoir['titre']) ?></h2>
        <div class="devoir-meta">
            <span><i class="fas fa-book"></i> <?= htmlspecialchars($devoir['nom_matiere']) ?></span>
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($devoir['nom_professeur']) ?></span>
            <span><i class="fas fa-clock"></i> Échéance : <?= formatDate($devoir['date_rendu']) ?></span>
            <?php if ($isPast): ?><span class="badge badge-danger">Date dépassée</span><?php endif; ?>
        </div>
        <?php if (!empty($devoir['description'])): ?>
        <div class="devoir-description">
            <p><?= nl2br(htmlspecialchars($devoir['description'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($existing && $existing['statut'] === 'corrige'): ?>
<div class="card correction-card">
    <div class="card-header"><h3><i class="fas fa-check-circle"></i> Correction du professeur</h3></div>
    <div class="card-body">
        <?php if ($existing['note'] !== null): ?>
        <div class="note-display"><span class="note-value"><?= $existing['note'] ?></span><span class="note-sur">/<?= $existing['note_sur'] ?></span></div>
        <?php endif; ?>
        <?php if ($existing['commentaire_prof']): ?>
        <p class="prof-commentaire"><?= nl2br(htmlspecialchars($existing['commentaire_prof'])) ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3><?= $existing ? 'Modifier votre rendu' : 'Soumettre votre travail' ?></h3></div>
    <div class="card-body">
        <?php if ($isPast): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> La date de rendu est dépassée. Votre travail sera marqué en retard.</div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="contenu">Votre réponse / commentaire</label>
                <textarea name="contenu" id="contenu" rows="6" class="form-control" placeholder="Écrivez votre réponse ici..."><?= htmlspecialchars($existing['contenu'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="fichier">Pièce jointe (PDF, Word, Image ...)</label>
                <input type="file" name="fichier" id="fichier" class="form-control" accept=".pdf,.doc,.docx,.odt,.png,.jpg,.jpeg,.zip">
                <?php if ($existing && $existing['fichier_nom']): ?>
                <p class="text-muted mt-05"><i class="fas fa-paperclip"></i> Fichier actuel : <?= htmlspecialchars($existing['fichier_nom']) ?></p>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> <?= $existing ? 'Mettre à jour' : 'Soumettre' ?></button>
                <a href="mes_devoirs.php" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
