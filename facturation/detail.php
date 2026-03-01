<?php
/**
 * M33 – Détail facture
 */
$pageTitle = 'Détail facture';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$facture = $factService->getFacture($id);
if (!$facture) { header('Location: factures.php'); exit; }

// Accès parent = seulement ses factures
if (isParent() && $facture['parent_id'] != getUserId()) { redirect('/facturation/factures.php'); }

$lignes = $factService->getLignes($id);
$paiements = $factService->getPaiements($id);
$isGestionnaire = isAdmin() || isPersonnelVS();
$modes = FacturationService::modesPaiement();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'ajouter_ligne' && $isGestionnaire) {
        $factService->ajouterLigne($id, trim($_POST['description']), (int)$_POST['quantite'], (float)$_POST['prix_unitaire']);
    } elseif ($action === 'paiement' && $isGestionnaire) {
        $factService->enregistrerPaiement($id, (float)$_POST['montant'], $_POST['mode_paiement']);
    }
    header('Location: detail.php?id=' . $id); exit;
}

$totalPaye = array_sum(array_column($paiements, 'montant'));
$reste = $facture['montant_ttc'] - $totalPaye;
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> <?= htmlspecialchars($facture['numero']) ?></h1>
        <a href="factures.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="info-grid">
        <div class="info-item"><?= FacturationService::badgeStatut($facture['statut']) ?></div>
        <div class="info-item"><i class="fas fa-user"></i><span><?= htmlspecialchars($facture['parent_nom']) ?></span></div>
        <div class="info-item"><i class="fas fa-calendar"></i><span>Éch. <?= formatDate($facture['date_echeance']) ?></span></div>
        <div class="info-item"><strong><?= number_format($facture['montant_ttc'], 2, ',', ' ') ?> € TTC</strong></div>
    </div>

    <!-- Lignes -->
    <div class="card" style="margin-bottom:1rem;">
        <div class="card-header"><h2>Lignes</h2></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Description</th><th>Qté</th><th>P.U.</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($lignes as $l): ?>
                    <tr><td><?= htmlspecialchars($l['description']) ?></td><td><?= $l['quantite'] ?></td><td><?= number_format($l['prix_unitaire'], 2, ',', ' ') ?> €</td><td><?= number_format($l['quantite'] * $l['prix_unitaire'], 2, ',', ' ') ?> €</td></tr>
                    <?php endforeach; ?>
                    <tr class="total-row"><td colspan="3"><strong>HT</strong></td><td><strong><?= number_format($facture['montant_ht'], 2, ',', ' ') ?> €</strong></td></tr>
                    <tr><td colspan="3">TVA</td><td><?= number_format($facture['montant_tva'], 2, ',', ' ') ?> €</td></tr>
                    <tr class="total-row"><td colspan="3"><strong>TTC</strong></td><td><strong><?= number_format($facture['montant_ttc'], 2, ',', ' ') ?> €</strong></td></tr>
                </tbody>
            </table>
            <?php if ($isGestionnaire): ?>
            <form method="post" class="form-inline" style="margin-top:1rem;">
                <?= csrfField() ?><input type="hidden" name="action" value="ajouter_ligne">
                <input type="text" name="description" class="form-control" placeholder="Description" required>
                <input type="number" name="quantite" class="form-control" value="1" min="1" style="width:70px;">
                <input type="number" name="prix_unitaire" class="form-control" step="0.01" min="0" placeholder="Prix" style="width:100px;" required>
                <button class="btn btn-primary"><i class="fas fa-plus"></i></button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Paiements -->
    <div class="card">
        <div class="card-header"><h2>Paiements (reste: <?= number_format(max($reste, 0), 2, ',', ' ') ?> €)</h2></div>
        <div class="card-body">
            <?php foreach ($paiements as $p): ?>
            <div class="paiement-item">
                <span><?= formatDateTime($p['date_paiement']) ?></span>
                <span class="badge badge-secondary"><?= $modes[$p['mode_paiement']] ?? $p['mode_paiement'] ?></span>
                <strong><?= number_format($p['montant'], 2, ',', ' ') ?> €</strong>
            </div>
            <?php endforeach; ?>
            <?php if ($isGestionnaire && $reste > 0): ?>
            <form method="post" class="form-inline" style="margin-top:1rem;">
                <?= csrfField() ?><input type="hidden" name="action" value="paiement">
                <input type="number" name="montant" class="form-control" step="0.01" min="0.01" max="<?= $reste ?>" value="<?= $reste ?>" style="width:120px;" required>
                <select name="mode_paiement" class="form-control"><?php foreach ($modes as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
                <button class="btn btn-success"><i class="fas fa-check"></i> Enregistrer</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
