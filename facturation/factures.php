<?php
/**
 * M33 – Factures — Liste
 */
$pageTitle = 'Facturation';
require_once __DIR__ . '/includes/header.php';

$isGestionnaire = isAdmin() || isPersonnelVS();
$filtreStatut = $_GET['statut'] ?? '';
$filters = [];
if ($filtreStatut) $filters['statut'] = $filtreStatut;

// Auto-détection des retards & rappels
if ($isGestionnaire) {
    $factService->detecterRetards();
}

if (isParent()) {
    $factures = $factService->getMesFactures(getUserId());
} else {
    $factures = $factService->getFactures($filters);
}

$types = FacturationService::typesFacture();
$stats = $isGestionnaire ? $factService->getStats() : null;
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Facturation</h1>
        <?php if ($isGestionnaire): ?><a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle facture</a><?php endif; ?>
    </div>

    <?php if ($stats): ?>
    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= number_format($stats['total_facture'], 0, ',', ' ') ?> €</div><div class="stat-label">Total facturé</div></div>
        <div class="stat-card"><div class="stat-value"><?= number_format($stats['total_paye'], 0, ',', ' ') ?> €</div><div class="stat-label">Total payé</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['impayees'] ?></div><div class="stat-label">Impayées</div></div>
    </div>
    <?php endif; ?>

    <?php if ($isGestionnaire): ?>
    <div class="filter-bar">
        <a href="factures.php" class="btn <?= !$filtreStatut ? 'btn-primary' : 'btn-outline' ?>">Toutes</a>
        <a href="factures.php?statut=en_attente" class="btn <?= $filtreStatut === 'en_attente' ? 'btn-primary' : 'btn-outline' ?>">En attente</a>
        <a href="factures.php?statut=payee" class="btn <?= $filtreStatut === 'payee' ? 'btn-primary' : 'btn-outline' ?>">Payées</a>
        <a href="factures.php?statut=en_retard" class="btn <?= $filtreStatut === 'en_retard' ? 'btn-primary' : 'btn-outline' ?>">En retard</a>
        <div class="filter-spacer"></div>
        <a href="export.php?format=csv&statut=<?= urlencode($filtreStatut) ?>" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        <a href="export.php?format=pdf&statut=<?= urlencode($filtreStatut) ?>" class="btn btn-outline btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
    </div>
    <?php endif; ?>

    <?php if (empty($factures)): ?>
        <div class="empty-state"><i class="fas fa-file-invoice-dollar"></i><p>Aucune facture.</p></div>
    <?php else: ?>
    <div class="factures-list">
        <?php foreach ($factures as $f):
            $reste = $f['montant_ttc'] - ($f['montant_paye'] ?? 0);
        ?>
        <div class="facture-card">
            <div class="facture-header">
                <strong><?= htmlspecialchars($f['numero']) ?></strong>
                <?= FacturationService::badgeStatut($f['statut']) ?>
                <span class="badge badge-secondary"><?= $types[$f['type']] ?? $f['type'] ?></span>
            </div>
            <div class="facture-body">
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($f['parent_nom']) ?></span>
                <span><i class="fas fa-calendar"></i> Éch. <?= formatDate($f['date_echeance']) ?></span>
            </div>
            <div class="facture-amounts">
                <span>TTC: <strong><?= number_format($f['montant_ttc'], 2, ',', ' ') ?> €</strong></span>
                <span>Payé: <?= number_format($f['montant_paye'] ?? 0, 2, ',', ' ') ?> €</span>
                <?php if ($reste > 0): ?><span class="reste">Reste: <?= number_format($reste, 2, ',', ' ') ?> €</span><?php endif; ?>
            </div>
            <a href="detail.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline">Détails</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
