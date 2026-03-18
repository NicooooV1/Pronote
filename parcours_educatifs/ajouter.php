<?php
$activePage = 'ajouter';
require_once __DIR__ . '/includes/header.php';

$user = $_SESSION['user'];
$role  = $user['type'] ?? 'eleve';
if (!in_array($role, ['admin', 'professeur'])) { header('Location: parcours.php'); exit; }

$types    = ParcoursEducatifService::typesLabels();
$modeles  = $parcoursService->getModeles();
$erreur   = '';
$editId   = (int) ($_GET['id'] ?? 0);
$entry    = $editId ? $parcoursService->getEntry($editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'eleve_id'          => (int) $_POST['eleve_id'],
        'type_parcours'     => $_POST['type_parcours'],
        'titre'             => trim($_POST['titre'] ?? ''),
        'description'       => trim($_POST['description'] ?? ''),
        'date_activite'     => $_POST['date_activite'] ?: date('Y-m-d'),
        'competences_visees' => trim($_POST['competences_visees'] ?? ''),
        'validation'        => isset($_POST['validation']) ? 1 : 0,
        'annee_scolaire'    => $_POST['annee_scolaire'] ?? '',
    ];
    if (!$data['titre'] || !$data['eleve_id'] || !$data['type_parcours']) {
        $erreur = 'Champs obligatoires manquants (élève, type, titre).';
    }
    if (!$erreur) {
        if ($editId) {
            $parcoursService->modifier($editId, $data);
        } else {
            $editId = $parcoursService->ajouter($data);
        }
        header('Location: parcours.php');
        exit;
    }
}
$p = $entry ?? ['eleve_id' => '', 'type_parcours' => 'avenir', 'titre' => '', 'description' => '', 'date_activite' => date('Y-m-d'), 'competences_visees' => '', 'validation' => 0, 'annee_scolaire' => ''];
?>
<div class="container mt-4">
    <h2><i class="fas fa-<?= $editId ? 'edit' : 'plus' ?> me-2"></i><?= $editId ? 'Modifier l\'activité' : 'Ajouter une activité au parcours' ?></h2>
    <?php if ($erreur): ?><div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <form method="post" class="card mt-3">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">ID Élève *</label>
                <input name="eleve_id" type="number" class="form-control" value="<?= (int)$p['eleve_id'] ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Type de parcours *</label>
                <select name="type_parcours" class="form-select">
                    <?php foreach ($types as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['type_parcours'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input name="date_activite" type="date" class="form-control" value="<?= htmlspecialchars($p['date_activite']) ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label">Titre *</label>
                <input name="titre" class="form-control" value="<?= htmlspecialchars($p['titre']) ?>" required>
                <?php if (!empty($modeles)): ?>
                    <div class="form-text">Modèles : 
                        <?php foreach ($modeles as $m): ?>
                            <a href="#" class="modele-link small" data-titre="<?= htmlspecialchars($m['titre']) ?>" data-desc="<?= htmlspecialchars($m['description_modele'] ?? '') ?>" data-comp="<?= htmlspecialchars($m['competences_modele'] ?? '') ?>" data-type="<?= htmlspecialchars($m['type_parcours']) ?>"><?= htmlspecialchars($m['titre']) ?></a> · 
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Année scolaire</label>
                <input name="annee_scolaire" class="form-control" placeholder="2024/2025" value="<?= htmlspecialchars($p['annee_scolaire']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Compétences visées</label>
                <textarea name="competences_visees" class="form-control" rows="2"><?= htmlspecialchars($p['competences_visees'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="validation" id="valid" <?= $p['validation'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="valid">Validé</label>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="parcours.php" class="btn btn-secondary me-2">Annuler</a>
            <button class="btn btn-primary"><i class="fas fa-save me-1"></i><?= $editId ? 'Enregistrer' : 'Ajouter' ?></button>
        </div>
    </form>
</div>
<script>
document.querySelectorAll('.modele-link').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        document.querySelector('[name=titre]').value = a.dataset.titre;
        document.querySelector('[name=description]').value = a.dataset.desc;
        document.querySelector('[name=competences_visees]').value = a.dataset.comp;
        document.querySelector('[name=type_parcours]').value = a.dataset.type;
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
