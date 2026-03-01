<?php
/**
 * M30 – Clubs — Mes clubs (élève)
 */
$pageTitle = 'Mes clubs';
$activePage = 'mes_clubs';
require_once __DIR__ . '/includes/header.php';

if (!isEleve()) { redirect('/clubs/clubs.php'); }

$inscriptions = $clubService->getInscriptionsEleve(getUserId());
$cats = ClubService::categories();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-id-card"></i> Mes clubs</h1>
        <a href="clubs.php" class="btn btn-primary"><i class="fas fa-search"></i> Découvrir</a>
    </div>

    <?php if (empty($inscriptions)): ?>
        <div class="empty-state"><i class="fas fa-users"></i><p>Vous n'êtes inscrit à aucun club.</p><a href="clubs.php" class="btn btn-primary">Découvrir les clubs</a></div>
    <?php else: ?>
    <div class="mes-clubs-list">
        <?php foreach ($inscriptions as $i): ?>
        <div class="club-item">
            <div class="club-info">
                <h3><?= htmlspecialchars($i['club_nom']) ?></h3>
                <div class="club-meta">
                    <?php if ($i['statut'] === 'en_attente'): ?>
                    <span class="badge badge-warning">En attente</span>
                    <?php elseif ($i['statut'] === 'accepte'): ?>
                    <span class="badge badge-success">Membre</span>
                    <?php endif; ?>
                    <?php if ($i['horaires']): ?><span><i class="fas fa-clock"></i> <?= htmlspecialchars($i['horaires']) ?></span><?php endif; ?>
                    <?php if ($i['lieu']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($i['lieu']) ?></span><?php endif; ?>
                </div>
            </div>
            <a href="detail.php?id=<?= $i['club_id'] ?>" class="btn btn-sm btn-outline">Voir</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
