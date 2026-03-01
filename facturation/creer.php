<?php
/**
 * M33 – Créer facture
 */
$pageTitle = 'Nouvelle facture';
$activePage = 'creer';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/facturation/factures.php'); }

$parents = $factService->getParents();
$types = FacturationService::typesFacture();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $ttc = (float)$_POST['montant_ttc'];
    $ht = round($ttc / 1.20, 2);
    $tva = $ttc - $ht;
    $id = $factService->creerFacture([
        'parent_id' => (int)$_POST['parent_id'], 'montant_ht' => $ht,
        'montant_tva' => $tva, 'montant_ttc' => $ttc,
        'date_echeance' => $_POST['date_echeance'], 'type' => $_POST['type'],
        'description' => trim($_POST['description'] ?? ''),
    ]);
    header('Location: detail.php?id=' . $id); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Nouvelle facture</h1>
        <a href="factures.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card"><div class="card-body">
        <form method="post">
            <?= csrfField() ?>
            <div class="form-grid-2">
                <div class="form-group"><label>Parent *</label><select name="parent_id" class="form-control" required><option value="">—</option><?php foreach ($parents as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Type</label><select name="type" class="form-control"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Montant TTC *</label><input type="number" name="montant_ttc" class="form-control" step="0.01" min="0.01" required></div>
                <div class="form-group"><label>Échéance *</label><input type="date" name="date_echeance" class="form-control" required></div>
                <div class="form-group full-width"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button><a href="factures.php" class="btn btn-outline">Annuler</a></div>
        </form>
    </div></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
