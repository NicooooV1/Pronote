<?php
/**
 * M27 – Créer examen
 */
$pageTitle = 'Créer un examen';
$activePage = 'creer';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/examens/examens.php'); }

$types = ExamenService::typesExamen();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $data = [
        'nom' => trim($_POST['nom'] ?? ''),
        'type' => $_POST['type'] ?? 'autre',
        'date_debut' => $_POST['date_debut'] ?? '',
        'date_fin' => $_POST['date_fin'] ?? '',
        'description' => trim($_POST['description'] ?? ''),
        'created_by' => getUserId(),
    ];
    if (empty($data['nom']) || empty($data['date_debut'])) {
        $error = 'Nom et date sont obligatoires.';
    } else {
        $id = $examenService->creerExamen($data);
        header('Location: detail.php?id=' . $id);
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Créer un examen</h1>
        <a href="examens.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card"><div class="card-body">
        <form method="post">
            <?= csrfField() ?>
            <div class="form-grid-2">
                <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" required></div>
                <div class="form-group"><label>Type</label><select name="type" class="form-control"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Date début *</label><input type="date" name="date_debut" class="form-control" required></div>
                <div class="form-group"><label>Date fin</label><input type="date" name="date_fin" class="form-control"></div>
                <div class="form-group full-width"><label>Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button><a href="examens.php" class="btn btn-outline">Annuler</a></div>
        </form>
    </div></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
