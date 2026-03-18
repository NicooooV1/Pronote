<?php
$activePage = 'associations';
require_once __DIR__ . '/includes/header.php';

$user = $_SESSION['user'];
$role  = $user['type'] ?? 'eleve';
if ($role !== 'admin') { header('Location: associations.php'); exit; }

$types   = VieAssociativeService::typesLabels();
$editId  = (int) ($_GET['id'] ?? 0);
$asso    = $editId ? $vieAssoService->getAssociation($editId) : null;
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom'                 => trim($_POST['nom'] ?? ''),
        'type'                => $_POST['type'] ?? 'association',
        'description'         => trim($_POST['description'] ?? ''),
        'president_eleve_id'  => (int) ($_POST['president_eleve_id'] ?? 0) ?: null,
        'referent_adulte_id'  => (int) ($_POST['referent_adulte_id'] ?? 0) ?: null,
        'budget_annuel'       => $_POST['budget_annuel'] ?: null,
        'statut'              => $_POST['statut'] ?? 'active',
    ];
    if (!$data['nom']) $erreur = 'Le nom est obligatoire.';
    if (!$erreur) {
        if ($editId) { $vieAssoService->modifierAssociation($editId, $data); }
        else { $editId = $vieAssoService->creerAssociation($data); }
        header("Location: detail.php?id=$editId"); exit;
    }
}
$a = $asso ?? ['nom' => '', 'type' => 'association', 'description' => '', 'president_eleve_id' => '', 'referent_adulte_id' => '', 'budget_annuel' => '', 'statut' => 'active'];
?>
<div class="container mt-4">
    <h2><i class="fas fa-<?= $editId ? 'edit' : 'plus' ?> me-2"></i><?= $editId ? 'Modifier l\'association' : 'Nouvelle association' ?></h2>
    <?php if ($erreur): ?><div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>
    <form method="post" class="card mt-3">
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">Nom *</label><input name="nom" class="form-control" value="<?= htmlspecialchars($a['nom']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Type</label><select name="type" class="form-select"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= ($a['type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Statut</label><select name="statut" class="form-select"><option value="active" <?= $a['statut']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $a['statut']==='inactive'?'selected':'' ?>>Inactive</option></select></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($a['description'] ?? '') ?></textarea></div>
            <div class="col-md-4"><label class="form-label">ID Président (élève)</label><input name="president_eleve_id" type="number" class="form-control" value="<?= (int)$a['president_eleve_id'] ?>"></div>
            <div class="col-md-4"><label class="form-label">ID Référent (prof)</label><input name="referent_adulte_id" type="number" class="form-control" value="<?= (int)$a['referent_adulte_id'] ?>"></div>
            <div class="col-md-4"><label class="form-label">Budget annuel (€)</label><input name="budget_annuel" type="number" step="0.01" class="form-control" value="<?= htmlspecialchars($a['budget_annuel'] ?? '') ?>"></div>
        </div>
        <div class="card-footer text-end">
            <a href="associations.php" class="btn btn-secondary me-2">Annuler</a>
            <button class="btn btn-primary"><i class="fas fa-save me-1"></i><?= $editId ? 'Enregistrer' : 'Créer' ?></button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
