<?php
/**
 * M36 – Détail ressource
 */
$pageTitle = 'Ressource';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$res = $resService->getRessource($id);
if (!$res) { redirect('/ressources/ressources.php'); }

$isAuteur = ($res['auteur_id'] == getUserId());
$isGestionnaire = isAdmin() || (isProfesseur() && $isAuteur);
$types = RessourceService::types();
$niveaux = RessourceService::niveaux();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire) {
    if (isset($_POST['supprimer'])) {
        $resService->supprimerRessource($id);
        header('Location: ressources.php'); exit;
    }
    $resService->modifierRessource($id, [
        'titre' => trim($_POST['titre']),
        'type' => $_POST['type'],
        'matiere_id' => $_POST['matiere_id'] ?: null,
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
        <h1><i class="fas fa-<?= RessourceService::iconeType($res['type']) ?>"></i> <?= htmlspecialchars($res['titre']) ?></h1>
        <a href="ressources.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="info-grid">
        <div class="info-item"><span class="badge badge-primary"><?= $types[$res['type']] ?? $res['type'] ?></span></div>
        <?php if ($res['matiere_nom']): ?><div class="info-item"><i class="fas fa-book"></i> <?= htmlspecialchars($res['matiere_nom']) ?></div><?php endif; ?>
        <?php if ($res['niveau']): ?><div class="info-item"><i class="fas fa-graduation-cap"></i> <?= $niveaux[$res['niveau']] ?? $res['niveau'] ?></div><?php endif; ?>
        <div class="info-item"><i class="fas fa-user"></i> <?= htmlspecialchars($res['auteur_nom'] ?? 'Inconnu') ?></div>
    </div>

    <?php if ($res['contenu']): ?>
    <div class="card"><div class="card-header"><h2>Contenu</h2></div><div class="card-body contenu-body"><div class="contenu-text"><?= nl2br(htmlspecialchars($res['contenu'])) ?></div></div></div>
    <?php endif; ?>

    <?php if ($res['tags']): ?>
    <div class="res-tags" style="margin:1rem 0;"><?php foreach (explode(',', $res['tags']) as $t): ?><span class="tag"><?= htmlspecialchars(trim($t)) ?></span><?php endforeach; ?></div>
    <?php endif; ?>

    <?php if ($isGestionnaire): ?>
    <div class="card" style="margin-top:1.5rem;"><div class="card-header"><h2>Modifier</h2></div><div class="card-body">
        <form method="post">
            <?= csrfField() ?>
            <div class="form-grid-2">
                <div class="form-group"><label>Titre *</label><input type="text" name="titre" class="form-control" value="<?= htmlspecialchars($res['titre']) ?>" required></div>
                <div class="form-group"><label>Type</label><select name="type" class="form-control"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $res['type'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Matière</label><select name="matiere_id" class="form-control"><option value="">—</option><?php foreach ($resService->getMatieres() as $m): ?><option value="<?= $m['id'] ?>" <?= $res['matiere_id'] == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Niveau</label><select name="niveau" class="form-control"><option value="">—</option><?php foreach ($niveaux as $k => $v): ?><option value="<?= $k ?>" <?= ($res['niveau'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group full-width"><label>Tags (virgule)</label><input type="text" name="tags" class="form-control" value="<?= htmlspecialchars($res['tags'] ?? '') ?>"></div>
                <div class="form-group full-width"><label>Contenu</label><textarea name="contenu" class="form-control" rows="6"><?= htmlspecialchars($res['contenu'] ?? '') ?></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="publie" <?= $res['publie'] ? 'checked' : '' ?>> Publié</label></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <button type="submit" name="supprimer" value="1" class="btn btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></button>
            </div>
        </form>
    </div></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
