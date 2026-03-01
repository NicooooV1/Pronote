<?php
/**
 * M16 – Documents — Liste des documents
 */
$pageTitle = 'Documents administratifs';
require_once __DIR__ . '/includes/header.php';

$role = getUserRole();
$categorie = $_GET['categorie'] ?? '';
$search = trim($_GET['q'] ?? '');

$documents = $docService->getDocuments($role, $categorie ?: null, $search ?: null);
$categories = DocumentService::categories();
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-folder-open"></i> Documents administratifs</h1>
        <?php if (isAdmin() || isTeacher() || isVieScolaire()): ?>
            <a href="ajouter.php" class="btn btn-primary"><i class="fas fa-upload"></i> Ajouter</a>
        <?php endif; ?>
    </div>

    <!-- Filtres -->
    <div class="doc-filters">
        <form method="get" class="doc-filter-form">
            <select name="categorie" onchange="this.form.submit()" class="form-select">
                <option value="">Toutes les catégories</option>
                <?php foreach ($categories as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $categorie === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher..." class="form-control">
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($documents)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open fa-3x"></i>
            <p>Aucun document disponible.</p>
        </div>
    <?php else: ?>
        <div class="doc-grid">
            <?php foreach ($documents as $doc): ?>
                <div class="doc-card">
                    <div class="doc-icon">
                        <i class="fas <?= DocumentService::fileIcon($doc['fichier_type'] ?? '') ?>"></i>
                    </div>
                    <div class="doc-body">
                        <h3 class="doc-title"><?= htmlspecialchars($doc['titre']) ?></h3>
                        <?php if (!empty($doc['description'])): ?>
                            <p class="doc-desc"><?= htmlspecialchars(mb_strimwidth($doc['description'], 0, 120, '...')) ?></p>
                        <?php endif; ?>
                        <div class="doc-meta">
                            <span class="badge"><?= $categories[$doc['categorie']] ?? $doc['categorie'] ?></span>
                            <span><i class="fas fa-hdd"></i> <?= DocumentService::formatSize((int)($doc['fichier_taille'] ?? 0)) ?></span>
                            <span><i class="fas fa-download"></i> <?= (int)($doc['telechargements'] ?? 0) ?></span>
                        </div>
                        <div class="doc-footer">
                            <span class="doc-author"><i class="fas fa-user"></i> <?= htmlspecialchars($doc['auteur_nom'] ?? 'Système') ?></span>
                            <span class="doc-date"><?= formatDate($doc['date_creation'] ?? '') ?></span>
                        </div>
                    </div>
                    <div class="doc-actions">
                        <a href="telecharger.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-primary" title="Télécharger"><i class="fas fa-download"></i></a>
                        <?php if (isAdmin()): ?>
                            <a href="supprimer.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"
                               onclick="return confirm('Supprimer ce document ?')"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
