<?php
/**
 * M40 – Inventaire matériels
 */
$pageTitle = 'Matériels';
$activePage = 'materiels';
require_once __DIR__ . '/includes/header.php';

$categorieFiltre = $_GET['categorie'] ?? '';
$filters = [];
if ($categorieFiltre) $filters['categorie'] = $categorieFiltre;
$materiels = $smService->getMateriels($filters);
$salles = $smService->getSalles();
$stats = $smService->getStatsMateriels();
$categories = SallesMaterielService::categoriesMateriels();
$etats = SallesMaterielService::etatsMateriels();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && (isAdmin() || isPersonnelVS())) {
    $smService->creerMateriel([
        'nom' => trim($_POST['nom']), 'categorie' => $_POST['categorie'],
        'reference' => trim($_POST['reference'] ?? ''), 'etat' => $_POST['etat'] ?? 'bon',
        'salle_id' => $_POST['salle_id'] ?: null, 'quantite' => (int)$_POST['quantite'],
        'valeur' => $_POST['valeur'] ?: null,
    ]);
    header('Location: materiels.php'); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-laptop"></i> Matériels</h1></div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['prets_en_cours'] ?></div><div class="stat-label">Prêts en cours</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['hors_service'] ?></div><div class="stat-label">Hors service</div></div>
    </div>

    <div class="filter-bar">
        <a href="materiels.php" class="btn <?= !$categorieFiltre ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <?php foreach ($categories as $k => $v): ?>
        <a href="materiels.php?categorie=<?= $k ?>" class="btn <?= $categorieFiltre === $k ? 'btn-primary' : 'btn-outline' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (isAdmin() || isPersonnelVS()): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><h2>Ajouter matériel</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-grid-3">
                    <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" required></div>
                    <div class="form-group"><label>Catégorie</label><select name="categorie" class="form-control"><?php foreach ($categories as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Référence</label><input type="text" name="reference" class="form-control"></div>
                    <div class="form-group"><label>État</label><select name="etat" class="form-control"><?php foreach ($etats as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Salle</label><select name="salle_id" class="form-control"><option value="">—</option><?php foreach ($salles as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Quantité</label><input type="number" name="quantite" class="form-control" value="1" min="1"></div>
                    <div class="form-group"><label>Valeur (€)</label><input type="number" name="valeur" class="form-control" step="0.01" min="0"></div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:.5rem;"><i class="fas fa-plus"></i> Ajouter</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($materiels)): ?>
        <div class="empty-state"><i class="fas fa-laptop"></i><p>Aucun matériel.</p></div>
    <?php else: ?>
    <div class="materiels-grid">
        <?php foreach ($materiels as $m): ?>
        <div class="materiel-card">
            <div class="materiel-header">
                <span class="badge badge-secondary"><?= $categories[$m['categorie']] ?? $m['categorie'] ?></span>
                <?= SallesMaterielService::badgeEtat($m['etat']) ?>
            </div>
            <h3><?= htmlspecialchars($m['nom']) ?></h3>
            <div class="materiel-meta">
                <?php if ($m['reference']): ?><span><i class="fas fa-barcode"></i> <?= htmlspecialchars($m['reference']) ?></span><?php endif; ?>
                <span><i class="fas fa-cubes"></i> Qté: <?= $m['quantite'] ?></span>
                <?php if ($m['salle_nom']): ?><span><i class="fas fa-door-open"></i> <?= htmlspecialchars($m['salle_nom']) ?></span><?php endif; ?>
                <?php if ($m['valeur']): ?><span><i class="fas fa-euro-sign"></i> <?= number_format($m['valeur'], 2, ',', ' ') ?></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
