<?php
$activePage = 'associations';
require_once __DIR__ . '/includes/header.php';

$user = $_SESSION['user'];
$role  = $user['type'] ?? 'eleve';
$typeFiltre = $_GET['type'] ?? '';
$associations = $vieAssoService->getAssociations($typeFiltre ?: null);
$types = VieAssociativeService::typesLabels();
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-hands-helping me-2"></i>Vie associative</h2>
        <?php if (in_array($role, ['admin'])): ?>
            <a href="creer.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nouvelle association</a>
        <?php endif; ?>
    </div>

    <form method="get" class="row g-2 mb-4 align-items-end">
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm">
                <option value="">Tous les types</option>
                <?php foreach ($types as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $typeFiltre === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
    </form>

    <div class="asso-grid">
        <?php if (empty($associations)): ?>
            <div class="alert alert-info">Aucune association enregistrée.</div>
        <?php endif; ?>
        <?php foreach ($associations as $a): ?>
            <div class="asso-card" style="border-top:4px solid <?= VieAssociativeService::typeColor($a['type']) ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="asso-type-badge" style="background:<?= VieAssociativeService::typeColor($a['type']) ?>"><?= $types[$a['type']] ?? $a['type'] ?></span>
                    <span class="badge bg-<?= $a['statut'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($a['statut']) ?></span>
                </div>
                <h5><?= htmlspecialchars($a['nom']) ?></h5>
                <p class="small text-muted mb-1"><?= htmlspecialchars(mb_strimwidth($a['description'] ?? '', 0, 100, '…')) ?></p>
                <div class="small mb-2">
                    <i class="fas fa-users me-1"></i><?= (int)$a['nb_membres'] ?> membre(s)
                    <?php if ($a['president_nom']): ?> · <i class="fas fa-crown me-1 text-warning"></i><?= htmlspecialchars($a['president_nom']) ?><?php endif; ?>
                </div>
                <?php if ($a['budget_annuel']): ?>
                    <div class="small mb-2"><i class="fas fa-euro-sign me-1"></i>Budget : <?= number_format((float)$a['budget_annuel'], 2, ',', ' ') ?> €</div>
                <?php endif; ?>
                <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary w-100 mt-auto">Détails</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
