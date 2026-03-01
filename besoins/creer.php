<?php
/**
 * M37 – Créer plan d'accompagnement
 */
$pageTitle = 'Nouveau plan';
$activePage = 'creer';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/besoins/besoins.php'); }

$eleves = $besoinService->getEleves();
$profs = $besoinService->getProfesseurs();
$types = BesoinService::typesPlan();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $data = [
        'eleve_id' => (int)$_POST['eleve_id'],
        'type' => $_POST['type'],
        'amenagements' => trim($_POST['amenagements'] ?? ''),
        'responsable_id' => (int)$_POST['responsable_id'] ?: null,
        'date_debut' => $_POST['date_debut'],
        'date_fin' => $_POST['date_fin'] ?: null,
    ];
    if (empty($data['eleve_id']) || empty($data['date_debut'])) {
        $error = 'Élève et date obligatoires.';
    } else {
        $id = $besoinService->creerPlan($data);
        header('Location: detail.php?id=' . $id);
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Nouveau plan</h1>
        <a href="besoins.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card"><div class="card-body">
        <form method="post">
            <?= csrfField() ?>
            <div class="form-grid-2">
                <div class="form-group"><label>Élève *</label><select name="eleve_id" class="form-control" required><option value="">—</option><?php foreach ($eleves as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?> (<?= $e['classe_nom'] ?? '-' ?>)</option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Type *</label><select name="type" class="form-control"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $k ?> — <?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Date début *</label><input type="date" name="date_debut" class="form-control" required></div>
                <div class="form-group"><label>Date fin</label><input type="date" name="date_fin" class="form-control"></div>
                <div class="form-group"><label>Responsable</label><select name="responsable_id" class="form-control"><option value="">—</option><?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group full-width"><label>Aménagements</label><textarea name="amenagements" class="form-control" rows="4" placeholder="Tiers temps, matériel adapté, supports agrandis…"></textarea></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button><a href="besoins.php" class="btn btn-outline">Annuler</a></div>
        </form>
    </div></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
