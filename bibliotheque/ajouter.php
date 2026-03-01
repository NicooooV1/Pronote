<?php
/**
 * M29 – Bibliothèque — Ajouter/Modifier livre
 */
$pageTitle = 'Ajouter un livre';
$activePage = 'ajouter';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/bibliotheque/catalogue.php'); }

$cats = BibliothequeService::categories();
$editId = (int)($_GET['edit'] ?? 0);
$livre = $editId ? $biblioService->getLivre($editId) : null;
if ($editId) $pageTitle = 'Modifier livre';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $data = [
        'titre' => trim($_POST['titre'] ?? ''),
        'auteur' => trim($_POST['auteur'] ?? ''),
        'isbn' => trim($_POST['isbn'] ?? ''),
        'editeur' => trim($_POST['editeur'] ?? ''),
        'annee_publication' => $_POST['annee_publication'] ?: null,
        'categorie' => $_POST['categorie'] ?? 'general',
        'description' => trim($_POST['description'] ?? ''),
        'exemplaires_total' => max(1, (int)($_POST['exemplaires_total'] ?? 1)),
        'emplacement' => trim($_POST['emplacement'] ?? ''),
    ];

    if (empty($data['titre'])) {
        $error = 'Le titre est obligatoire.';
    } else {
        if ($editId) {
            $biblioService->modifierLivre($editId, $data);
        } else {
            $editId = $biblioService->ajouterLivre($data);
        }
        header('Location: livre.php?id=' . $editId);
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-<?= $livre ? 'edit' : 'plus-circle' ?>"></i> <?= $livre ? 'Modifier' : 'Ajouter' ?> un livre</h1>
        <a href="catalogue.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Catalogue</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-grid-2">
                    <div class="form-group full-width">
                        <label>Titre *</label>
                        <input type="text" name="titre" class="form-control" required value="<?= htmlspecialchars($livre['titre'] ?? $_POST['titre'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Auteur</label>
                        <input type="text" name="auteur" class="form-control" value="<?= htmlspecialchars($livre['auteur'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>ISBN</label>
                        <input type="text" name="isbn" class="form-control" value="<?= htmlspecialchars($livre['isbn'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Éditeur</label>
                        <input type="text" name="editeur" class="form-control" value="<?= htmlspecialchars($livre['editeur'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Année de publication</label>
                        <input type="number" name="annee_publication" class="form-control" min="1900" max="2100" value="<?= $livre['annee_publication'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="categorie" class="form-control">
                            <?php foreach ($cats as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($livre['categorie'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nombre d'exemplaires</label>
                        <input type="number" name="exemplaires_total" class="form-control" min="1" value="<?= $livre['exemplaires_total'] ?? 1 ?>">
                    </div>
                    <div class="form-group">
                        <label>Emplacement</label>
                        <input type="text" name="emplacement" class="form-control" placeholder="ex: Rayon A3" value="<?= htmlspecialchars($livre['emplacement'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($livre['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $livre ? 'Modifier' : 'Ajouter' ?></button>
                    <a href="catalogue.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
