<?php
/**
 * M45 – Signalements — Détail (admin/VS)
 */
$pageTitle = 'Détail signalement';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/signalements/signaler.php'); }

$id = (int)($_GET['id'] ?? 0);
$sig = $signalementService->getSignalement($id);
if (!$sig) { header('Location: signalements.php'); exit; }

$types = SignalementService::typesSignalement();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'statut') {
        $signalementService->changerStatut($id, $_POST['statut'], getUserId());
    } elseif ($action === 'note') {
        $signalementService->ajouterNote($id, trim($_POST['note']));
    }
    header('Location: detail.php?id=' . $id);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-shield-alt"></i> Signalement #<?= $id ?></h1>
        <a href="signalements.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="detail-status">
        <?= SignalementService::statutBadge($sig['statut']) ?>
        <?= SignalementService::urgenceBadge($sig['urgence']) ?>
        <form method="post" class="inline-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="statut">
            <select name="statut" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="nouveau" <?= $sig['statut'] === 'nouveau' ? 'selected' : '' ?>>Nouveau</option>
                <option value="en_cours" <?= $sig['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                <option value="traite" <?= $sig['statut'] === 'traite' ? 'selected' : '' ?>>Traité</option>
                <option value="classe" <?= $sig['statut'] === 'classe' ? 'selected' : '' ?>>Classé</option>
            </select>
        </form>
    </div>

    <div class="detail-cards">
        <div class="card">
            <div class="card-header"><h2>Informations</h2></div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item"><label>Type</label><span><?= $types[$sig['type']] ?? $sig['type'] ?></span></div>
                    <div class="detail-item"><label>Urgence</label><span><?= $sig['urgence'] ?></span></div>
                    <div class="detail-item"><label>Date signalement</label><span><?= formatDateTime($sig['date_signalement']) ?></span></div>
                    <div class="detail-item"><label>Date des faits</label><span><?= $sig['date_faits'] ? formatDate($sig['date_faits']) : '—' ?></span></div>
                    <div class="detail-item"><label>Lieu</label><span><?= htmlspecialchars($sig['lieu'] ?: '—') ?></span></div>
                    <div class="detail-item"><label>Auteur</label><span><?= $sig['anonyme'] ? '<i class="fas fa-user-secret"></i> Anonyme' : ($sig['auteur_type'] . ' #' . $sig['auteur_id']) ?></span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Description</h2></div>
            <div class="card-body"><p><?= nl2br(htmlspecialchars($sig['description'])) ?></p></div>
        </div>

        <?php if ($sig['personnes_impliquees']): ?>
        <div class="card">
            <div class="card-header"><h2>Personnes impliquées</h2></div>
            <div class="card-body"><p><?= nl2br(htmlspecialchars($sig['personnes_impliquees'])) ?></p></div>
        </div>
        <?php endif; ?>

        <?php if ($sig['temoins']): ?>
        <div class="card">
            <div class="card-header"><h2>Témoins</h2></div>
            <div class="card-body"><p><?= nl2br(htmlspecialchars($sig['temoins'])) ?></p></div>
        </div>
        <?php endif; ?>

        <!-- Notes de traitement -->
        <div class="card">
            <div class="card-header"><h2>Notes de suivi</h2></div>
            <div class="card-body">
                <?php if ($sig['notes_traitement']): ?>
                <pre class="notes-pre"><?= htmlspecialchars($sig['notes_traitement']) ?></pre>
                <?php endif; ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="note">
                    <div class="form-group">
                        <textarea name="note" class="form-control" rows="3" placeholder="Ajouter une note de suivi..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
