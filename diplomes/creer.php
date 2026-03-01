<?php
/**
 * M44 – Créer diplôme
 */
$pageTitle = 'Nouveau diplôme';
$activePage = 'creer';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/diplomes/diplomes.php'); }

$types = DiplomeService::typesDiplome();
$mentions = DiplomeService::mentions();
$eleves = $diplService->getEleves();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $fichier = null;
    if (!empty($_FILES['fichier']['name'])) {
        $dir = __DIR__ . '/uploads/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $fichier = 'dipl_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
        move_uploaded_file($_FILES['fichier']['tmp_name'], $dir . $fichier);
    }
    $id = $diplService->creerDiplome([
        'eleve_id' => (int)$_POST['eleve_id'],
        'intitule' => trim($_POST['intitule']),
        'type' => $_POST['type'],
        'mention' => $_POST['mention'] ?: null,
        'date_obtention' => $_POST['date_obtention'],
        'fichier_path' => $fichier,
        'description' => trim($_POST['description'] ?? ''),
    ]);
    header('Location: diplomes.php'); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Nouveau diplôme</h1>
        <a href="diplomes.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card"><div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="form-grid-2">
                <div class="form-group"><label>Élève *</label><select name="eleve_id" class="form-control" required><option value="">—</option><?php foreach ($eleves as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?> <?= $e['classe_nom'] ? '(' . htmlspecialchars($e['classe_nom']) . ')' : '' ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Intitulé *</label><input type="text" name="intitule" class="form-control" required></div>
                <div class="form-group"><label>Type *</label><select name="type" class="form-control" required><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Mention</label><select name="mention" class="form-control"><option value="">—</option><?php foreach ($mentions as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Date d'obtention *</label><input type="date" name="date_obtention" class="form-control" required></div>
                <div class="form-group"><label>Fichier (PDF)</label><input type="file" name="fichier" class="form-control" accept=".pdf,.jpg,.png"></div>
                <div class="form-group full-width"><label>Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button>
                <a href="diplomes.php" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </div></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
