<?php
/**
 * M37 – Besoins particuliers — Liste plans
 */
$pageTitle = 'Besoins particuliers';
require_once __DIR__ . '/includes/header.php';

$isGestionnaire = isAdmin() || isPersonnelVS() || isProfesseur();
$filtreType = $_GET['type'] ?? '';
$filtreStatut = $_GET['statut'] ?? '';
$filters = [];
if ($filtreType) $filters['type'] = $filtreType;
if ($filtreStatut) $filters['statut'] = $filtreStatut;

if (isParent()) {
    $plans = $besoinService->getPlansParent(getUserId());
} elseif (isEleve()) {
    $plans = $besoinService->getPlansEleve(getUserId());
} elseif (isProfesseur()) {
    $filters['responsable_id'] = getUserId();
    $plans = $besoinService->getPlans($filters);
} else {
    $plans = $besoinService->getPlans($filters);
}

$typesPlan = BesoinService::typesPlan();
$stats = ($isGestionnaire || isAdmin()) ? $besoinService->getStats() : null;
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-hands-helping"></i> Besoins particuliers</h1>
        <?php if (isAdmin() || isPersonnelVS()): ?><a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau plan</a><?php endif; ?>
    </div>

    <?php if ($stats): ?>
    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['total_actifs'] ?></div><div class="stat-label">Plans actifs</div></div>
        <?php foreach ($typesPlan as $k => $v): ?>
        <div class="stat-card"><div class="stat-value"><?= $stats['par_type'][$k] ?? 0 ?></div><div class="stat-label"><?= $k ?></div></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($isGestionnaire): ?>
    <div class="filter-bar">
        <a href="besoins.php" class="btn <?= !$filtreType ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <?php foreach ($typesPlan as $k => $v): ?>
        <a href="besoins.php?type=<?= $k ?>" class="btn <?= $filtreType === $k ? 'btn-primary' : 'btn-outline' ?>"><?= $k ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($plans)): ?>
        <div class="empty-state"><i class="fas fa-hands-helping"></i><p>Aucun plan d'accompagnement.</p></div>
    <?php else: ?>
    <div class="plans-list">
        <?php foreach ($plans as $p): ?>
        <div class="plan-card type-<?= strtolower($p['type']) ?>">
            <div class="plan-header">
                <?= BesoinService::badgeType($p['type']) ?>
                <span class="badge badge-<?= $p['statut'] === 'actif' ? 'success' : ($p['statut'] === 'suspendu' ? 'warning' : 'secondary') ?>"><?= ucfirst($p['statut']) ?></span>
            </div>
            <h3><a href="detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['eleve_nom']) ?></a></h3>
            <p class="plan-classe"><?= htmlspecialchars($p['classe_nom'] ?? '') ?></p>
            <div class="plan-meta">
                <span><i class="fas fa-calendar"></i> <?= formatDate($p['date_debut']) ?><?= $p['date_fin'] ? ' → ' . formatDate($p['date_fin']) : '' ?></span>
                <?php if ($p['responsable_nom']): ?><span><i class="fas fa-user"></i> <?= htmlspecialchars($p['responsable_nom']) ?></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
