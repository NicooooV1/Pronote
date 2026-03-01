<?php
/**
 * M44 – Diplômes & Relevés — Liste
 */
$pageTitle = 'Diplômes & Relevés';
require_once __DIR__ . '/includes/header.php';

$isGestionnaire = isAdmin() || isPersonnelVS();
$types = DiplomeService::typesDiplome();
$mentions = DiplomeService::mentions();

$filtreType = $_GET['type'] ?? '';
$filters = [];
if ($filtreType) $filters['type'] = $filtreType;

if (isEleve()) {
    $diplomes = $diplService->getMesDiplomes(getUserId());
} elseif (isParent()) {
    $diplomes = $diplService->getDiplomesParent(getUserId());
} else {
    $diplomes = $diplService->getDiplomes($filters);
}

$stats = $isGestionnaire ? $diplService->getStats() : null;
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-graduation-cap"></i> Diplômes & Relevés</h1>
        <?php if ($isGestionnaire): ?><a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau</a><?php endif; ?>
    </div>

    <?php if ($stats): ?>
    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['annee_courante'] ?></div><div class="stat-label">Cette année</div></div>
    </div>
    <?php endif; ?>

    <?php if ($isGestionnaire): ?>
    <div class="filter-bar">
        <a href="diplomes.php" class="btn <?= !$filtreType ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <?php foreach (array_slice($types, 0, 5) as $k => $v): ?>
        <a href="diplomes.php?type=<?= $k ?>" class="btn <?= $filtreType === $k ? 'btn-primary' : 'btn-outline' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($diplomes)): ?>
        <div class="empty-state"><i class="fas fa-graduation-cap"></i><p>Aucun diplôme enregistré.</p></div>
    <?php else: ?>
    <div class="diplomes-grid">
        <?php foreach ($diplomes as $d): ?>
        <div class="diplome-card">
            <div class="diplome-icon"><i class="fas fa-award"></i></div>
            <div class="diplome-info">
                <h3><?= htmlspecialchars($d['intitule']) ?></h3>
                <div class="diplome-meta">
                    <span class="badge badge-primary"><?= $types[$d['type']] ?? $d['type'] ?></span>
                    <?= DiplomeService::badgeMention($d['mention'] ?? null) ?>
                </div>
                <div class="diplome-details">
                    <span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($d['eleve_nom']) ?></span>
                    <span><i class="fas fa-calendar"></i> <?= formatDate($d['date_obtention']) ?></span>
                    <span><i class="fas fa-hashtag"></i> <?= htmlspecialchars($d['numero']) ?></span>
                </div>
            </div>
            <?php if ($isGestionnaire): ?>
            <a href="detail.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline">Détails</a>
            <?php endif; ?>
            <?php if ($d['fichier_path']): ?>
            <a href="telecharger.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-download"></i></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
