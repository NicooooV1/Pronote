<?php
/**
 * M29 – Bibliothèque — Catalogue
 */
$pageTitle = 'Catalogue';
require_once __DIR__ . '/includes/header.php';

$recherche = trim($_GET['q'] ?? '');
$categorie = $_GET['cat'] ?? '';
$filters = [];
if ($recherche) $filters['recherche'] = $recherche;
if ($categorie) $filters['categorie'] = $categorie;
$livres = $biblioService->getLivres($filters);
$cats = BibliothequeService::categories();
$stats = $biblioService->getStats();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-book"></i> Catalogue</h1>
        <?php if (isAdmin() || isPersonnelVS()): ?>
        <a href="ajouter.php" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</a>
        <?php endif; ?>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Ouvrages</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['exemplaires'] ?></div><div class="stat-label">Exemplaires</div></div>
        <div class="stat-card stat-info"><div class="stat-value"><?= $stats['actifs'] ?></div><div class="stat-label">Empruntés</div></div>
        <div class="stat-card stat-danger"><div class="stat-value"><?= $stats['retards'] ?></div><div class="stat-label">En retard</div></div>
    </div>

    <div class="search-filter">
        <form method="get" class="search-bar">
            <input type="text" name="q" class="form-control" placeholder="Rechercher un livre..." value="<?= htmlspecialchars($recherche) ?>">
            <select name="cat" class="form-control" onchange="this.form.submit()">
                <option value="">Toutes catégories</option>
                <?php foreach ($cats as $k => $v): ?>
                <option value="<?= $k ?>" <?= $categorie === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($livres)): ?>
        <div class="empty-state"><i class="fas fa-book-open"></i><p>Aucun livre trouvé.</p></div>
    <?php else: ?>
    <div class="livres-grid">
        <?php foreach ($livres as $l): ?>
        <div class="livre-card">
            <div class="livre-cover"><i class="fas fa-book"></i></div>
            <div class="livre-info">
                <h3><?= htmlspecialchars($l['titre']) ?></h3>
                <?php if ($l['auteur']): ?><p class="livre-auteur"><?= htmlspecialchars($l['auteur']) ?></p><?php endif; ?>
                <span class="livre-cat"><?= $cats[$l['categorie']] ?? $l['categorie'] ?></span>
                <div class="livre-dispo">
                    <?php if ($l['exemplaires_disponibles'] > 0): ?>
                    <span class="dispo-ok"><i class="fas fa-check-circle"></i> <?= $l['exemplaires_disponibles'] ?> disponible(s)</span>
                    <?php else: ?>
                    <span class="dispo-ko"><i class="fas fa-times-circle"></i> Indisponible</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="livre-actions">
                <a href="livre.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline">Détails</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
