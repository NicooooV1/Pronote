<?php
/**
 * M26 – Inscriptions — Détail
 */
$pageTitle = 'Détail inscription';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$insc = $inscriptionService->getInscription($id);
if (!$insc) { header('Location: inscriptions.php'); exit; }

// Vérifier accès
$isGestionnaire = isAdmin() || isPersonnelVS();
if (!$isGestionnaire && (!isParent() || $insc['parent_id'] !== getUserId())) {
    header('Location: inscriptions.php');
    exit;
}

$documents = $inscriptionService->getDocuments($id);

// Actions admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire) {
    $action = $_POST['action'] ?? '';
    if ($action === 'statut') {
        $inscriptionService->changerStatut($id, $_POST['statut'], getUserId());
    } elseif ($action === 'valider_doc') {
        $inscriptionService->validerDocument((int)$_POST['doc_id'], (bool)$_POST['valide']);
    }
    header('Location: detail.php?id=' . $id);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-file-alt"></i> Inscription #<?= $id ?></h1>
        <a href="inscriptions.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <!-- Statut -->
    <div class="detail-status">
        <?= InscriptionService::statutBadge($insc['statut']) ?>
        <?php if ($isGestionnaire): ?>
        <form method="post" class="inline-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="statut">
            <select name="statut" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="soumise" <?= $insc['statut'] === 'soumise' ? 'selected' : '' ?>>Soumise</option>
                <option value="en_revision" <?= $insc['statut'] === 'en_revision' ? 'selected' : '' ?>>En révision</option>
                <option value="acceptee" <?= $insc['statut'] === 'acceptee' ? 'selected' : '' ?>>Acceptée</option>
                <option value="refusee" <?= $insc['statut'] === 'refusee' ? 'selected' : '' ?>>Refusée</option>
                <option value="liste_attente" <?= $insc['statut'] === 'liste_attente' ? 'selected' : '' ?>>Liste d'attente</option>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <!-- Infos élève -->
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-child"></i> Informations élève</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item"><label>Nom</label><span><?= htmlspecialchars($insc['nom_eleve']) ?></span></div>
                <div class="detail-item"><label>Prénom</label><span><?= htmlspecialchars($insc['prenom_eleve']) ?></span></div>
                <div class="detail-item"><label>Date de naissance</label><span><?= formatDate($insc['date_naissance']) ?></span></div>
                <div class="detail-item"><label>Sexe</label><span><?= $insc['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?></span></div>
                <div class="detail-item"><label>Classe demandée</label><span><?= htmlspecialchars($insc['classe_nom'] ?? 'Non précisée') ?></span></div>
                <div class="detail-item"><label>Établissement précédent</label><span><?= htmlspecialchars($insc['etablissement_precedent'] ?: '—') ?></span></div>
            </div>
        </div>
    </div>

    <!-- Contact -->
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-phone"></i> Contact</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item full-width"><label>Adresse</label><span><?= htmlspecialchars($insc['adresse']) ?></span></div>
                <div class="detail-item"><label>Téléphone</label><span><?= htmlspecialchars($insc['telephone']) ?></span></div>
                <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($insc['email_contact']) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Documents -->
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-paperclip"></i> Documents (<?= count($documents) ?>)</h2></div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <p class="text-muted">Aucun document joint.</p>
            <?php else: ?>
            <div class="doc-list">
                <?php foreach ($documents as $doc): ?>
                <div class="doc-item">
                    <i class="fas fa-file"></i>
                    <div class="doc-info">
                        <span class="doc-type"><?= InscriptionService::typesDocument()[$doc['type_document']] ?? $doc['type_document'] ?></span>
                        <span class="doc-date"><?= formatDateTime($doc['date_ajout']) ?></span>
                    </div>
                    <div class="doc-status">
                        <?php if ($doc['valide'] === null): ?>
                            <span class="badge badge-secondary">En attente</span>
                        <?php elseif ($doc['valide']): ?>
                            <span class="badge badge-success">Validé</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Refusé</span>
                        <?php endif; ?>
                    </div>
                    <div class="doc-actions">
                        <a href="download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-download"></i></a>
                        <?php if ($isGestionnaire): ?>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="valider_doc">
                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                            <button name="valide" value="1" class="btn btn-sm btn-success" title="Valider"><i class="fas fa-check"></i></button>
                            <button name="valide" value="0" class="btn btn-sm btn-danger" title="Refuser"><i class="fas fa-times"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($insc['observations']): ?>
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-comment"></i> Observations</h2></div>
        <div class="card-body"><p><?= nl2br(htmlspecialchars($insc['observations'])) ?></p></div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
