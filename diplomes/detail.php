<?php
/**
 * M44 – Détail/Modifier diplôme
 */
$pageTitle = 'Détail diplôme';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/diplomes/diplomes.php'); }

$id = (int)($_GET['id'] ?? 0);
$diplome = $diplService->getDiplome($id);
if (!$diplome) { redirect('/diplomes/diplomes.php'); }

$types = DiplomeService::typesDiplome();
$mentions = DiplomeService::mentions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    if (isset($_POST['supprimer'])) {
        $diplService->supprimerDiplome($id);
        header('Location: diplomes.php'); exit;
    }
    $diplService->modifierDiplome($id, [
        'intitule' => trim($_POST['intitule']),
        'type' => $_POST['type'],
        'mention' => $_POST['mention'] ?: null,
        'date_obtention' => $_POST['date_obtention'],
        'description' => trim($_POST['description'] ?? ''),
    ]);
    header('Location: detail.php?id=' . $id); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-award"></i> <?= htmlspecialchars($diplome['intitule']) ?></h1>
        <a href="diplomes.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="info-grid">
        <div class="info-item"><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($diplome['eleve_nom']) ?></div>
        <div class="info-item"><span class="badge badge-primary"><?= $types[$diplome['type']] ?? $diplome['type'] ?></span></div>
        <div class="info-item"><i class="fas fa-calendar"></i> <?= formatDate($diplome['date_obtention']) ?></div>
        <div class="info-item"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($diplome['numero']) ?></div>
        <?php if ($diplome['mention']): ?><div class="info-item"><?= DiplomeService::badgeMention($diplome['mention']) ?></div><?php endif; ?>
    </div>

    <?php if ($diplome['description']): ?>
    <div class="card" style="margin-bottom:1.5rem;"><div class="card-body"><p><?= nl2br(htmlspecialchars($diplome['description'])) ?></p></div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h2>Modifier</h2></div><div class="card-body">
        <form method="post">
            <?= csrfField() ?>
            <div class="form-grid-2">
                <div class="form-group"><label>Intitulé *</label><input type="text" name="intitule" class="form-control" value="<?= htmlspecialchars($diplome['intitule']) ?>" required></div>
                <div class="form-group"><label>Type</label><select name="type" class="form-control"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $diplome['type'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Mention</label><select name="mention" class="form-control"><option value="">—</option><?php foreach ($mentions as $k => $v): ?><option value="<?= $k ?>" <?= ($diplome['mention'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Date d'obtention *</label><input type="date" name="date_obtention" class="form-control" value="<?= $diplome['date_obtention'] ?>" required></div>
                <div class="form-group full-width"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($diplome['description'] ?? '') ?></textarea></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <button type="submit" name="supprimer" value="1" class="btn btn-danger" onclick="return confirm('Supprimer ce diplôme ?')"><i class="fas fa-trash"></i> Supprimer</button>
            </div>
        </form>
    </div></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
