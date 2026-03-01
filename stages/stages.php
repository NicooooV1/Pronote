<?php
/**
 * M17 – Stages — Liste
 */
$pageTitle = 'Stages & Alternance';
require_once __DIR__ . '/includes/header.php';

$filtreType = $_GET['type'] ?? '';
$filtreStatut = $_GET['statut'] ?? '';
$filters = [];
if ($filtreType) $filters['type'] = $filtreType;
if ($filtreStatut) $filters['statut'] = $filtreStatut;

if (isParent()) {
    $stages = $stageService->getStagesParent(getUserId());
} elseif (isEleve()) {
    $stages = $stageService->getStagesEleve(getUserId());
} elseif (isProfesseur()) {
    $filters['prof_referent_id'] = getUserId();
    $stages = $stageService->getStages($filters);
} else {
    $stages = $stageService->getStages($filters);
}

$types = StageService::typesStage();
$statuts = StageService::statutsStage();
$isGestionnaire = isAdmin() || isPersonnelVS();
$stats = $isGestionnaire ? $stageService->getStats() : null;
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-briefcase"></i> Stages & Alternance</h1>
        <?php if ($isGestionnaire): ?><a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau</a><?php endif; ?>
    </div>

    <?php if ($stats): ?>
    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['en_cours'] ?></div><div class="stat-label">En cours</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['en_recherche'] ?></div><div class="stat-label">En recherche</div></div>
    </div>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="stages.php" class="btn <?= !$filtreStatut ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <?php foreach ($statuts as $k => $v): ?>
        <a href="stages.php?statut=<?= $k ?>" class="btn <?= $filtreStatut === $k ? 'btn-primary' : 'btn-outline' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($stages)): ?>
        <div class="empty-state"><i class="fas fa-briefcase"></i><p>Aucun stage.</p></div>
    <?php else: ?>
    <div class="stages-list">
        <?php foreach ($stages as $s): ?>
        <div class="stage-card">
            <div class="stage-header">
                <span class="badge badge-secondary"><?= $types[$s['type']] ?? $s['type'] ?></span>
                <?= StageService::badgeStatut($s['statut']) ?>
            </div>
            <h3><a href="detail.php?id=<?= $s['id'] ?>"><?= htmlspecialchars($s['entreprise_nom']) ?></a></h3>
            <p class="stage-eleve"><?= htmlspecialchars($s['eleve_nom']) ?> — <?= htmlspecialchars($s['classe_nom'] ?? '') ?></p>
            <div class="stage-meta">
                <span><i class="fas fa-calendar"></i> <?= formatDate($s['date_debut']) ?> → <?= formatDate($s['date_fin']) ?></span>
                <?php if ($s['prof_nom']): ?><span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($s['prof_nom']) ?></span><?php endif; ?>
                <?php if ($s['tuteur_nom']): ?><span><i class="fas fa-user"></i> <?= htmlspecialchars($s['tuteur_nom']) ?></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
