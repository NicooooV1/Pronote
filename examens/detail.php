<?php
/**
 * M27 – Examens — Détail + épreuves
 */
$pageTitle = 'Détail examen';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$examen = $examenService->getExamen($id);
if (!$examen) { header('Location: examens.php'); exit; }

$epreuves = $examenService->getEpreuves($id);
$isGestionnaire = isAdmin() || isPersonnelVS();
$types = ExamenService::typesExamen();
$typesEpreuve = ExamenService::typesEpreuve();
$matieres = $examenService->getMatieres();
$salles = $examenService->getSalles();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire) {
    $action = $_POST['action'] ?? '';
    if ($action === 'ajouter_epreuve') {
        $examenService->creerEpreuve([
            'examen_id' => $id, 'matiere_id' => $_POST['matiere_id'] ?: null,
            'intitule' => trim($_POST['intitule']), 'date_epreuve' => $_POST['date_epreuve'],
            'duree_minutes' => (int)$_POST['duree_minutes'], 'salle_id' => $_POST['salle_id'] ?: null,
            'coefficient' => $_POST['coefficient'] ?? 1, 'type' => $_POST['type_epreuve'],
            'consignes' => trim($_POST['consignes'] ?? ''),
        ]);
    } elseif ($action === 'supprimer_epreuve') {
        $examenService->supprimerEpreuve((int)$_POST['epreuve_id']);
    } elseif ($action === 'modifier_statut') {
        $examenService->modifierExamen($id, array_merge($examen, ['statut' => $_POST['statut']]));
    }
    header('Location: detail.php?id=' . $id);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($examen['nom']) ?></h1>
        <a href="examens.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="info-grid">
        <div class="info-item"><i class="fas fa-tag"></i><span><?= $types[$examen['type']] ?? $examen['type'] ?></span></div>
        <div class="info-item"><i class="fas fa-calendar"></i><span><?= formatDate($examen['date_debut']) ?><?= $examen['date_fin'] ? ' → ' . formatDate($examen['date_fin']) : '' ?></span></div>
        <div class="info-item"><?= ExamenService::statutBadge($examen['statut']) ?></div>
        <?php if ($isGestionnaire): ?>
        <div class="info-item">
            <form method="post" style="display:flex;gap:.3rem;">
                <?= csrfField() ?><input type="hidden" name="action" value="modifier_statut">
                <select name="statut" class="form-control" onchange="this.form.submit()">
                    <option value="planifie" <?= $examen['statut'] === 'planifie' ? 'selected' : '' ?>>Planifié</option>
                    <option value="en_cours" <?= $examen['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="termine" <?= $examen['statut'] === 'termine' ? 'selected' : '' ?>>Terminé</option>
                    <option value="annule" <?= $examen['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
                </select>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($examen['description']): ?><p class="exam-desc"><?= nl2br(htmlspecialchars($examen['description'])) ?></p><?php endif; ?>

    <!-- Épreuves -->
    <div class="card">
        <div class="card-header"><h2>Épreuves (<?= count($epreuves) ?>)</h2></div>
        <div class="card-body">
            <?php foreach ($epreuves as $ep): ?>
            <div class="epreuve-item">
                <div class="epreuve-left">
                    <strong><?= htmlspecialchars($ep['intitule']) ?></strong>
                    <div class="epreuve-meta">
                        <span class="badge badge-secondary"><?= $typesEpreuve[$ep['type']] ?? $ep['type'] ?></span>
                        <?php if ($ep['matiere_nom']): ?><span><?= htmlspecialchars($ep['matiere_nom']) ?></span><?php endif; ?>
                        <span><i class="fas fa-clock"></i> <?= $ep['duree_minutes'] ?> min</span>
                        <span>Coef. <?= $ep['coefficient'] ?></span>
                    </div>
                </div>
                <div class="epreuve-right">
                    <span><i class="fas fa-calendar"></i> <?= formatDateTime($ep['date_epreuve']) ?></span>
                    <?php if ($ep['salle_nom']): ?><span><i class="fas fa-door-open"></i> <?= htmlspecialchars($ep['salle_nom']) ?></span><?php endif; ?>
                    <span><i class="fas fa-users"></i> <?= $ep['nb_convocations'] ?> élèves</span>
                    <a href="epreuve.php?id=<?= $ep['id'] ?>" class="btn btn-sm btn-primary">Gérer</a>
                    <?php if ($isGestionnaire): ?>
                    <form method="post" style="display:inline;"><?= csrfField() ?><input type="hidden" name="epreuve_id" value="<?= $ep['id'] ?>"><button name="action" value="supprimer_epreuve" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></button></form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($isGestionnaire): ?>
            <hr>
            <h3>Ajouter une épreuve</h3>
            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="ajouter_epreuve">
                <div class="form-grid-3">
                    <div class="form-group"><label>Intitulé *</label><input type="text" name="intitule" class="form-control" required></div>
                    <div class="form-group"><label>Matière</label><select name="matiere_id" class="form-control"><option value="">—</option><?php foreach ($matieres as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Type</label><select name="type_epreuve" class="form-control"><?php foreach ($typesEpreuve as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Date & heure *</label><input type="datetime-local" name="date_epreuve" class="form-control" required></div>
                    <div class="form-group"><label>Durée (min)</label><input type="number" name="duree_minutes" class="form-control" value="120"></div>
                    <div class="form-group"><label>Salle</label><select name="salle_id" class="form-control"><option value="">—</option><?php foreach ($salles as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nom']) ?> (<?= $s['capacite'] ?>p)</option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Coefficient</label><input type="number" name="coefficient" class="form-control" value="1" step="0.5" min="0.5"></div>
                    <div class="form-group full-width"><label>Consignes</label><textarea name="consignes" class="form-control" rows="2"></textarea></div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
