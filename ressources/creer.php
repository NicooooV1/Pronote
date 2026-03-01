<?php
/**
 * M36 – Créer ressource
 */
$pageTitle = 'Créer une ressource';
$activePage = 'creer';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isProfesseur()) { redirect('/ressources/ressources.php'); }

$types = RessourceService::types();
$niveaux = RessourceService::niveaux();
$matieres = $resService->getMatieres();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $id = $resService->creerRessource([
        'titre' => trim($_POST['titre']),
        'type' => $_POST['type'],
        'matiere_id' => $_POST['matiere_id'] ?: null,
        'auteur_id' => getUserId(),
        'contenu' => trim($_POST['contenu'] ?? ''),
        'niveau' => $_POST['niveau'] ?? null,
        'tags' => trim($_POST['tags'] ?? ''),
        'publie' => isset($_POST['publie']) ? 1 : 0,
    ]);
    header('Location: detail.php?id=' . $id); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Créer une ressource</h1>
        <a href="ressources.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card"><div class="card-body">
        <form method="post">
            <?= csrfField() ?>
            <div class="form-grid-2">
                <div class="form-group"><label>Titre *</label><input type="text" name="titre" class="form-control" required></div>
                <div class="form-group"><label>Type</label><select name="type" class="form-control"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Matière</label><select name="matiere_id" class="form-control"><option value="">—</option><?php foreach ($matieres as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Niveau</label><select name="niveau" class="form-control"><option value="">—</option><?php foreach ($niveaux as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group full-width"><label>Tags (séparés par ,)</label><input type="text" name="tags" class="form-control" placeholder="ex: maths, algèbre, calcul"></div>
                <div class="form-group full-width"><label>Contenu</label><textarea name="contenu" class="form-control" rows="8"></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="publie" checked> Publier immédiatement</label></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button>
                <a href="ressources.php" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </div></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
