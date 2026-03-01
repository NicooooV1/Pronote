<?php
/**
 * M36 – Ressources pédagogiques — Liste publique
 */
$pageTitle = 'Ressources pédagogiques';
require_once __DIR__ . '/includes/header.php';

$types = RessourceService::types();
$niveaux = RessourceService::niveaux();
$matieres = $resService->getMatieres();

$filtreType = $_GET['type'] ?? '';
$filtreMatiere = $_GET['matiere'] ?? '';
$search = $_GET['q'] ?? '';

$filters = ['publie' => 1];
if ($filtreType) $filters['type'] = $filtreType;
if ($filtreMatiere) $filters['matiere_id'] = $filtreMatiere;
if ($search) $filters['search'] = $search;

$ressources = $resService->getRessources($filters);
$stats = $resService->getStats();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-book-open"></i> Ressources pédagogiques</h1>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['publiees'] ?></div><div class="stat-label">Publiées</div></div>
        <div class="stat-card"><div class="stat-value"><?= count(array_unique(array_column($ressources, 'matiere_id'))) ?></div><div class="stat-label">Matières</div></div>
    </div>

    <div class="filter-bar">
        <form method="get" class="filter-form">
            <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
            <select name="type" class="form-control"><option value="">Tous types</option><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $filtreType === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
            <select name="matiere" class="form-control"><option value="">Toutes matières</option><?php foreach ($matieres as $m): ?><option value="<?= $m['id'] ?>" <?= $filtreMatiere == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nom']) ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($ressources)): ?>
        <div class="empty-state"><i class="fas fa-book-open"></i><p>Aucune ressource trouvée.</p></div>
    <?php else: ?>
    <div class="ressources-grid">
        <?php foreach ($ressources as $r): ?>
        <a href="detail.php?id=<?= $r['id'] ?>" class="ressource-card type-<?= $r['type'] ?>">
            <div class="res-icon"><i class="fas fa-<?= RessourceService::iconeType($r['type']) ?>"></i></div>
            <div class="res-info">
                <h3><?= htmlspecialchars($r['titre']) ?></h3>
                <div class="res-meta">
                    <span class="badge badge-primary"><?= $types[$r['type']] ?? $r['type'] ?></span>
                    <?php if ($r['matiere_nom']): ?><span><?= htmlspecialchars($r['matiere_nom']) ?></span><?php endif; ?>
                    <?php if ($r['niveau']): ?><span><?= $niveaux[$r['niveau']] ?? $r['niveau'] ?></span><?php endif; ?>
                </div>
                <div class="res-author"><i class="fas fa-user"></i> <?= htmlspecialchars($r['auteur_nom'] ?? 'Inconnu') ?></div>
                <?php if ($r['tags']): ?><div class="res-tags"><?php foreach (explode(',', $r['tags']) as $t): ?><span class="tag"><?= htmlspecialchars(trim($t)) ?></span><?php endforeach; ?></div><?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
