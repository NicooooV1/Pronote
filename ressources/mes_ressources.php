<?php
/**
 * M36 – Mes ressources (prof/admin)
 */
$pageTitle = 'Mes ressources';
$activePage = 'mes_ressources';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isProfesseur()) { redirect('/ressources/ressources.php'); }

$ressources = $resService->getRessources(['auteur_id' => getUserId()]);
$types = RessourceService::types();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-folder"></i> Mes ressources</h1>
        <a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle</a>
    </div>

    <?php if (empty($ressources)): ?>
        <div class="empty-state"><i class="fas fa-folder-open"></i><p>Aucune ressource créée.</p></div>
    <?php else: ?>
    <div class="ressources-grid">
        <?php foreach ($ressources as $r): ?>
        <a href="detail.php?id=<?= $r['id'] ?>" class="ressource-card type-<?= $r['type'] ?>">
            <div class="res-icon"><i class="fas fa-<?= RessourceService::iconeType($r['type']) ?>"></i></div>
            <div class="res-info">
                <h3><?= htmlspecialchars($r['titre']) ?></h3>
                <div class="res-meta">
                    <span class="badge badge-primary"><?= $types[$r['type']] ?? $r['type'] ?></span>
                    <?php if (!$r['publie']): ?><span class="badge badge-secondary">Brouillon</span><?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
