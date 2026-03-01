<?php
/**
 * M31 – Fiche santé individuelle — Consultation / édition
 */
$pageTitle = 'Fiche santé';
$activePage = 'fiches';
require_once __DIR__ . '/includes/header.php';

$eleveId = (int)($_GET['eleve'] ?? 0);

// Vérif droit d'accès
if (isParent()) {
    $enfants = $infirmerieService->getEnfantsParent(getUserId());
    $ids = array_column($enfants, 'id');
    if (!in_array($eleveId, $ids)) { redirect('/infirmerie/infirmerie.php'); }
} elseif (isEleve()) {
    $eleveId = getUserId();
} elseif (!isAdmin() && !isPersonnelVS()) {
    redirect('/infirmerie/infirmerie.php');
}

$fiche = $infirmerieService->getFiche($eleveId);
$canEdit = isAdmin() || isPersonnelVS();
$groupes = InfirmerieService::groupesSanguins();

// Informations élève
$stmt = getPDO()->prepare("SELECT e.*, cl.nom AS classe_nom FROM eleves e LEFT JOIN classes cl ON e.classe_id = cl.id WHERE e.id = ?");
$stmt->execute([$eleveId]);
$eleve = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$eleve) { redirect('/infirmerie/infirmerie.php'); }

// Derniers passages
$passages = $infirmerieService->getPassagesEleve($eleveId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit && validateCSRFToken()) {
    $data = [
        'allergies' => trim($_POST['allergies'] ?? ''),
        'traitements' => trim($_POST['traitements'] ?? ''),
        'contacts_urgence' => trim($_POST['contacts_urgence'] ?? ''),
        'pai' => trim($_POST['pai'] ?? ''),
        'groupe_sanguin' => $_POST['groupe_sanguin'] ?? null,
        'remarques' => trim($_POST['remarques'] ?? ''),
    ];
    $infirmerieService->sauvegarderFiche($eleveId, $data);
    $_SESSION['success_message'] = 'Fiche santé mise à jour.';
    header('Location: fiche_sante.php?eleve=' . $eleveId);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-notes-medical"></i> Fiche santé — <?= htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']) ?></h1>
        <a href="<?= $canEdit ? 'fiches.php' : 'infirmerie.php' ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="eleve-header">
        <div class="eleve-avatar"><?= strtoupper(substr($eleve['prenom'], 0, 1) . substr($eleve['nom'], 0, 1)) ?></div>
        <div>
            <h2><?= htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']) ?></h2>
            <span class="text-muted"><?= htmlspecialchars($eleve['classe_nom'] ?? '') ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><i class="fas fa-file-medical"></i> Informations médicales</h2></div>
        <div class="card-body">
            <?php if ($canEdit): ?>
            <form method="post">
                <?= csrfField() ?>
                <div class="form-grid-2">
                    <div class="form-group full-width health-alert">
                        <label><i class="fas fa-exclamation-triangle"></i> Allergies</label>
                        <textarea name="allergies" class="form-control" rows="2" placeholder="Pénicilline, arachides, latex…"><?= htmlspecialchars($fiche['allergies'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Traitements en cours</label>
                        <textarea name="traitements" class="form-control" rows="2" placeholder="Médicaments, posologie…"><?= htmlspecialchars($fiche['traitements'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Contacts d'urgence</label>
                        <textarea name="contacts_urgence" class="form-control" rows="2" placeholder="Nom, lien, téléphone"><?= htmlspecialchars($fiche['contacts_urgence'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Groupe sanguin</label>
                        <select name="groupe_sanguin" class="form-control">
                            <option value="">— Non renseigné —</option>
                            <?php foreach ($groupes as $g): ?><option value="<?= $g ?>" <?= ($fiche['groupe_sanguin'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>PAI (Projet d'Accueil Individualisé)</label>
                        <textarea name="pai" class="form-control" rows="2"><?= htmlspecialchars($fiche['pai'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Remarques</label>
                        <textarea name="remarques" class="form-control" rows="2"><?= htmlspecialchars($fiche['remarques'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
            </form>
            <?php else: ?>
            <!-- Lecture seule pour parent/élève -->
            <div class="fiche-readonly">
                <?php if ($fiche): ?>
                <div class="detail-grid">
                    <div class="detail-item"><label>Allergies</label><p><?= nl2br(htmlspecialchars($fiche['allergies'] ?: 'Aucune')) ?></p></div>
                    <div class="detail-item"><label>Traitements</label><p><?= nl2br(htmlspecialchars($fiche['traitements'] ?: 'Aucun')) ?></p></div>
                    <div class="detail-item"><label>Contacts urgence</label><p><?= nl2br(htmlspecialchars($fiche['contacts_urgence'] ?: 'Non renseigné')) ?></p></div>
                    <div class="detail-item"><label>Groupe sanguin</label><p><?= htmlspecialchars($fiche['groupe_sanguin'] ?: 'Non renseigné') ?></p></div>
                    <div class="detail-item"><label>PAI</label><p><?= nl2br(htmlspecialchars($fiche['pai'] ?: 'Aucun')) ?></p></div>
                    <?php if ($fiche['remarques']): ?><div class="detail-item"><label>Remarques</label><p><?= nl2br(htmlspecialchars($fiche['remarques'])) ?></p></div><?php endif; ?>
                </div>
                <?php else: ?><p class="text-muted">Aucune fiche santé renseignée.</p><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historique passages -->
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-history"></i> Passages à l'infirmerie (<?= count($passages) ?>)</h2></div>
        <div class="card-body">
            <?php if (empty($passages)): ?><p class="text-muted">Aucun passage enregistré.</p>
            <?php else: ?>
            <div class="passages-list">
                <?php foreach ($passages as $p): ?>
                <div class="passage-item orientation-<?= $p['orientation'] ?>">
                    <div class="passage-date"><?= formatDateTime($p['date_passage']) ?></div>
                    <div class="passage-motif"><?= htmlspecialchars($p['motif']) ?></div>
                    <?php if ($p['soins']): ?><small class="text-muted"><i class="fas fa-first-aid"></i> <?= htmlspecialchars($p['soins']) ?></small><?php endif; ?>
                    <?= InfirmerieService::orientationBadge($p['orientation']) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
