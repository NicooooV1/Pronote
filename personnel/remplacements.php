<?php
/**
 * M39 – Remplacements
 */
$pageTitle = 'Remplacements';
$activePage = 'remplacements';
require_once __DIR__ . '/includes/header.php';

$profs = $personnelService->getPersonnel();
$matieres = $personnelService->getMatieres();
$classes = $personnelService->getClasses();
$filtreStatut = $_GET['statut'] ?? '';
$filters = [];
if ($filtreStatut) $filters['statut'] = $filtreStatut;
$remplacements = $personnelService->getRemplacements($filters);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'creer') {
        $personnelService->creerRemplacement([
            'absence_id' => $_POST['absence_id'] ?: null,
            'professeur_absent_id' => (int)$_POST['professeur_absent_id'],
            'professeur_remplacant_id' => $_POST['professeur_remplacant_id'] ?: null,
            'matiere_id' => $_POST['matiere_id'] ?: null,
            'classe_id' => $_POST['classe_id'] ?: null,
            'date_debut' => $_POST['date_debut'],
            'date_fin' => $_POST['date_fin'],
        ]);
    } elseif ($action === 'attribuer') {
        $personnelService->attribuerRemplacant((int)$_POST['remplacement_id'], (int)$_POST['remplacant_id']);
    }
    header('Location: remplacements.php');
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-exchange-alt"></i> Remplacements</h1></div>

    <div class="filter-bar">
        <a href="remplacements.php" class="btn <?= !$filtreStatut ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <?php foreach (PersonnelService::statutsRemplacement() as $k => $v): ?>
        <a href="remplacements.php?statut=<?= $k ?>" class="btn <?= $filtreStatut === $k ? 'btn-primary' : 'btn-outline' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><h2>Planifier un remplacement</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="creer">
                <div class="form-grid-3">
                    <div class="form-group"><label>Prof absent *</label><select name="professeur_absent_id" class="form-control" required><option value="">—</option><?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Remplaçant</label><select name="professeur_remplacant_id" class="form-control"><option value="">— à déterminer —</option><?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Matière</label><select name="matiere_id" class="form-control"><option value="">—</option><?php foreach ($matieres as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Classe</label><select name="classe_id" class="form-control"><option value="">—</option><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Début *</label><input type="datetime-local" name="date_debut" class="form-control" required></div>
                    <div class="form-group"><label>Fin *</label><input type="datetime-local" name="date_fin" class="form-control" required></div>
                    <input type="hidden" name="absence_id" value="">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:.5rem;"><i class="fas fa-plus"></i> Planifier</button>
            </form>
        </div>
    </div>

    <?php if (empty($remplacements)): ?>
        <div class="empty-state"><i class="fas fa-exchange-alt"></i><p>Aucun remplacement.</p></div>
    <?php else: ?>
    <div class="remplacements-list">
        <?php foreach ($remplacements as $r): ?>
        <div class="remplacement-card">
            <div class="remplacement-header">
                <?= PersonnelService::badgeStatut($r['statut']) ?>
                <?php if ($r['matiere_nom']): ?><span class="badge badge-secondary"><?= htmlspecialchars($r['matiere_nom']) ?></span><?php endif; ?>
            </div>
            <div class="remplacement-body">
                <div class="remplacement-profs">
                    <span class="absent"><i class="fas fa-user-times"></i> <?= htmlspecialchars($r['absent_nom']) ?></span>
                    <i class="fas fa-arrow-right"></i>
                    <span class="remplacant"><i class="fas fa-user-check"></i> <?= $r['remplacant_nom'] ? htmlspecialchars($r['remplacant_nom']) : '<em>À déterminer</em>' ?></span>
                </div>
                <div class="remplacement-meta">
                    <span><i class="fas fa-calendar"></i> <?= formatDateTime($r['date_debut']) ?> → <?= formatDateTime($r['date_fin']) ?></span>
                    <?php if ($r['classe_nom']): ?><span><i class="fas fa-users"></i> <?= htmlspecialchars($r['classe_nom']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php if ($r['statut'] === 'propose' && !$r['professeur_remplacant_id']): ?>
            <form method="post" class="form-inline" style="margin-top:.5rem;">
                <?= csrfField() ?><input type="hidden" name="action" value="attribuer"><input type="hidden" name="remplacement_id" value="<?= $r['id'] ?>">
                <select name="remplacant_id" class="form-control" required><option value="">— Remplaçant —</option><?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?></select>
                <button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Attribuer</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
