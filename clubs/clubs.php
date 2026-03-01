<?php
/**
 * M30 – Clubs — Liste
 */
$pageTitle = 'Clubs & Activités';
require_once __DIR__ . '/includes/header.php';

$categorie = $_GET['cat'] ?? '';
$clubs = $clubService->getClubs($categorie ?: null);
$cats = ClubService::categories();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-users"></i> Clubs & Activités</h1>
        <?php if (isAdmin() || isPersonnelVS() || isProfesseur()): ?>
        <a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Créer un club</a>
        <?php endif; ?>
    </div>

    <div class="cat-bar">
        <a href="clubs.php" class="cat-chip <?= !$categorie ? 'active' : '' ?>">Tous</a>
        <?php foreach ($cats as $k => $v): ?>
        <a href="clubs.php?cat=<?= $k ?>" class="cat-chip <?= $categorie === $k ? 'active' : '' ?>">
            <i class="fas fa-<?= ClubService::iconeCategorie($k) ?>"></i> <?= $v ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($clubs)): ?>
        <div class="empty-state"><i class="fas fa-users"></i><p>Aucun club pour le moment.</p></div>
    <?php else: ?>
    <div class="clubs-grid">
        <?php foreach ($clubs as $c): ?>
        <div class="club-card">
            <div class="club-icon cat-<?= $c['categorie'] ?>">
                <i class="fas fa-<?= ClubService::iconeCategorie($c['categorie']) ?>"></i>
            </div>
            <h3><?= htmlspecialchars($c['nom']) ?></h3>
            <span class="club-cat"><?= $cats[$c['categorie']] ?? $c['categorie'] ?></span>
            <?php if ($c['responsable_nom']): ?>
            <p class="club-resp"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['responsable_nom']) ?></p>
            <?php endif; ?>
            <div class="club-meta">
                <?php if ($c['horaires']): ?><span><i class="fas fa-clock"></i> <?= htmlspecialchars($c['horaires']) ?></span><?php endif; ?>
                <span><i class="fas fa-users"></i> <?= $c['nb_inscrits'] ?><?= $c['places_max'] ? '/' . $c['places_max'] : '' ?></span>
            </div>
            <a href="detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary btn-block">Voir</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
