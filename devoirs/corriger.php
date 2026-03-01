<?php
/**
 * Devoirs en ligne — Correction par le professeur
 */
require_once __DIR__ . '/includes/RenduService.php';
$currentPage = 'corriger';
$pageTitle = 'Corriger les rendus';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isTeacher() && !isAdmin() && !isVieScolaire()) {
    header('Location: mes_devoirs.php');
    exit;
}

$pdo = getPDO();
$service = new RenduService($pdo);
$devoirId = (int)($_GET['devoir'] ?? 0);
$message = '';

// POST: corriger un rendu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $renduId = (int)$_POST['rendu_id'];
    $action = $_POST['action'] ?? 'corriger';
    
    if ($action === 'corriger') {
        $note = isset($_POST['note']) && $_POST['note'] !== '' ? (float)$_POST['note'] : null;
        $noteSur = (float)($_POST['note_sur'] ?? 20);
        $commentaire = trim($_POST['commentaire'] ?? '');
        $service->corriger($renduId, $note, $noteSur, $commentaire);
        $message = 'Correction enregistrée.';
    } elseif ($action === 'refaire') {
        $commentaire = trim($_POST['commentaire'] ?? '');
        $service->demanderRefaire($renduId, $commentaire);
        $message = 'Demande de refaire envoyée.';
    }
}

if ($devoirId) {
    $devoir = $service->getDevoir($devoirId);
    $rendus = $service->getRendusDevoir($devoirId);
    $stats = $service->getStatsDevoir($devoirId);
} else {
    // Liste de tous les devoirs avec rendus
    $stmt = $pdo->prepare("
        SELECT d.*, (SELECT COUNT(*) FROM devoirs_rendus WHERE devoir_id = d.id) AS nb_rendus,
               (SELECT COUNT(*) FROM devoirs_rendus WHERE devoir_id = d.id AND statut = 'corrige') AS nb_corriges
        FROM devoirs d WHERE nom_professeur = ? AND EXISTS (SELECT 1 FROM devoirs_rendus WHERE devoir_id = d.id)
        ORDER BY d.date_rendu DESC
    ");
    $stmt->execute([$user_fullname]);
    $devoirsAvecRendus = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="page-header">
    <h1><i class="fas fa-check-double"></i> <?= $devoirId ? 'Corriger : ' . htmlspecialchars($devoir['titre'] ?? '') : 'Corrections' ?></h1>
    <?php if ($devoirId): ?>
    <a href="corriger.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    <?php endif; ?>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($devoirId && !empty($devoir)): ?>
    <!-- Stats du devoir -->
    <div class="stats-row">
        <div class="stat-card"><span class="stat-value"><?= $stats['total_eleves'] ?? 0 ?></span><span class="stat-label">Élèves</span></div>
        <div class="stat-card primary"><span class="stat-value"><?= $stats['total_rendus'] ?? 0 ?></span><span class="stat-label">Rendus</span></div>
        <div class="stat-card success"><span class="stat-value"><?= $stats['corriges'] ?? 0 ?></span><span class="stat-label">Corrigés</span></div>
        <div class="stat-card info"><span class="stat-value"><?= $stats['moyenne_notes'] ? number_format($stats['moyenne_notes'], 1) : '-' ?></span><span class="stat-label">Moyenne</span></div>
    </div>

    <!-- Liste des rendus -->
    <?php foreach ($rendus as $r): ?>
    <div class="rendu-card <?= $r['statut'] === 'corrige' ? 'rendu-corrige' : '' ?>">
        <div class="rendu-header">
            <div class="rendu-eleve">
                <strong><?= htmlspecialchars($r['eleve_prenom'] . ' ' . $r['eleve_nom']) ?></strong>
                <span class="text-muted"><?= htmlspecialchars($r['classe']) ?></span>
            </div>
            <div class="rendu-meta">
                <?= RenduService::statutBadge($r['statut']) ?>
                <?php if ($r['en_retard']): ?><span class="badge badge-danger">En retard</span><?php endif; ?>
                <span class="text-muted"><?= formatDateTime($r['date_rendu']) ?></span>
            </div>
        </div>
        <?php if ($r['contenu']): ?>
        <div class="rendu-contenu"><?= nl2br(htmlspecialchars($r['contenu'])) ?></div>
        <?php endif; ?>
        <?php if ($r['fichier_nom']): ?>
        <div class="rendu-fichier"><a href="../<?= htmlspecialchars($r['fichier_chemin']) ?>" target="_blank"><i class="fas fa-paperclip"></i> <?= htmlspecialchars($r['fichier_nom']) ?></a></div>
        <?php endif; ?>

        <form method="POST" class="correction-form">
            <?= csrfField() ?>
            <input type="hidden" name="rendu_id" value="<?= $r['id'] ?>">
            <div class="correction-grid">
                <div class="form-group">
                    <label>Note</label>
                    <div class="note-input">
                        <input type="number" name="note" step="0.5" min="0" max="20" value="<?= $r['note'] ?? '' ?>" class="form-control" placeholder="—">
                        <span>/</span>
                        <input type="number" name="note_sur" value="<?= $r['note_sur'] ?? 20 ?>" class="form-control" style="width:60px">
                    </div>
                </div>
                <div class="form-group" style="flex:1">
                    <label>Commentaire</label>
                    <textarea name="commentaire" rows="2" class="form-control" placeholder="Commentaire pour l'élève..."><?= htmlspecialchars($r['commentaire_prof'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="correction-actions">
                <button type="submit" name="action" value="corriger" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Valider correction</button>
                <button type="submit" name="action" value="refaire" class="btn btn-sm btn-warning"><i class="fas fa-redo"></i> À refaire</button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>

    <?php if (empty($rendus)): ?>
    <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucun rendu pour ce devoir.</p></div>
    <?php endif; ?>

<?php else: ?>
    <!-- Liste des devoirs avec rendus -->
    <div class="data-table-container">
        <table class="data-table">
            <thead><tr><th>Devoir</th><th>Matière</th><th>Classe</th><th class="text-center">Échéance</th><th class="text-center">Rendus</th><th class="text-center">Corrigés</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($devoirsAvecRendus ?? [] as $d): ?>
                <tr>
                    <td class="fw-500"><?= htmlspecialchars($d['titre']) ?></td>
                    <td><?= htmlspecialchars($d['nom_matiere']) ?></td>
                    <td><?= htmlspecialchars($d['classe']) ?></td>
                    <td class="text-center"><?= formatDate($d['date_rendu']) ?></td>
                    <td class="text-center"><span class="badge badge-info"><?= $d['nb_rendus'] ?></span></td>
                    <td class="text-center"><span class="badge badge-success"><?= $d['nb_corriges'] ?></span></td>
                    <td><a href="corriger.php?devoir=<?= $d['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-pen"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($devoirsAvecRendus ?? [])): ?>
                <tr><td colspan="7" class="text-center text-muted">Aucun rendu reçu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
