<?php
/**
 * M39 – Absences du personnel
 */
$pageTitle = 'Absences du personnel';
require_once __DIR__ . '/includes/header.php';

$filtreStatut = $_GET['statut'] ?? '';
$personnel = $personnelService->getPersonnel();
$typesAbsence = PersonnelService::typesAbsence();
$stats = $personnelService->getStats();

$filters = [];
if ($filtreStatut) $filters['statut'] = $filtreStatut;
$absences = $personnelService->getAbsences($filters);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'creer') {
        $personnelService->creerAbsence([
            'personnel_id' => (int)$_POST['personnel_id'],
            'type' => $_POST['type'],
            'date_debut' => $_POST['date_debut'],
            'date_fin' => $_POST['date_fin'],
            'motif' => trim($_POST['motif'] ?? ''),
        ]);
    } elseif ($action === 'valider') {
        $personnelService->modifierStatut((int)$_POST['absence_id'], 'validee');
    } elseif ($action === 'refuser') {
        $personnelService->modifierStatut((int)$_POST['absence_id'], 'refusee');
    }
    header('Location: absences.php');
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-user-clock"></i> Absences du personnel</h1></div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['absences_actives'] ?></div><div class="stat-label">Absences actives</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['remplacements_en_attente'] ?></div><div class="stat-label">Rempl. en attente</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['remplacements_confirmes'] ?></div><div class="stat-label">Rempl. confirmés</div></div>
    </div>

    <div class="filter-bar">
        <a href="absences.php" class="btn <?= !$filtreStatut ? 'btn-primary' : 'btn-outline' ?>">Toutes</a>
        <?php foreach (PersonnelService::statutsAbsence() as $k => $v): ?>
        <a href="absences.php?statut=<?= $k ?>" class="btn <?= $filtreStatut === $k ? 'btn-primary' : 'btn-outline' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Formulaire -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><h2>Déclarer une absence</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="creer">
                <div class="form-grid-3">
                    <div class="form-group"><label>Personnel *</label><select name="personnel_id" class="form-control" required><option value="">—</option><?php foreach ($personnel as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Type</label><select name="type" class="form-control"><?php foreach ($typesAbsence as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Début *</label><input type="date" name="date_debut" class="form-control" required></div>
                    <div class="form-group"><label>Fin *</label><input type="date" name="date_fin" class="form-control" required></div>
                    <div class="form-group"><label>Motif</label><input type="text" name="motif" class="form-control"></div>
                    <div class="form-group" style="align-self:end;"><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Déclarer</button></div>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($absences)): ?>
        <div class="empty-state"><i class="fas fa-user-clock"></i><p>Aucune absence.</p></div>
    <?php else: ?>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Personnel</th><th>Type</th><th>Début</th><th>Fin</th><th>Motif</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($absences as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['personnel_nom']) ?></strong></td>
                    <td><span class="badge badge-secondary"><?= $typesAbsence[$a['type']] ?? $a['type'] ?></span></td>
                    <td><?= formatDate($a['date_debut']) ?></td>
                    <td><?= formatDate($a['date_fin']) ?></td>
                    <td><?= htmlspecialchars($a['motif'] ?? '-') ?></td>
                    <td><?= PersonnelService::badgeStatut($a['statut']) ?></td>
                    <td>
                        <?php if ($a['statut'] === 'en_attente'): ?>
                        <form method="post" style="display:inline;"><?= csrfField() ?><input type="hidden" name="absence_id" value="<?= $a['id'] ?>"><button name="action" value="valider" class="btn btn-sm btn-success">✓</button></form>
                        <form method="post" style="display:inline;"><?= csrfField() ?><input type="hidden" name="absence_id" value="<?= $a['id'] ?>"><button name="action" value="refuser" class="btn btn-sm btn-danger">✗</button></form>
                        <?php else: echo '-'; endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
