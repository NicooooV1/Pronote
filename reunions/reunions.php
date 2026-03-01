<?php
/**
 * M14 – Réunions — Liste des réunions
 */
$pageTitle = 'Réunions & RDV';
require_once __DIR__ . '/includes/header.php';

$filtreType = $_GET['type'] ?? '';
$filtreStatut = $_GET['statut'] ?? '';

$filters = [];
if ($filtreType) $filters['type'] = $filtreType;
if ($filtreStatut) $filters['statut'] = $filtreStatut;

$reunions = $reunionService->getReunions($filters);
$types = ReunionService::typesReunion();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-calendar-alt"></i> Réunions & RDV</h1>
        <?php if (isAdmin() || isTeacher() || isVieScolaire()): ?>
        <a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Planifier une réunion</a>
        <?php endif; ?>
    </div>

    <!-- Filtres -->
    <div class="filter-bar">
        <form method="get" class="filter-form">
            <select name="type" class="form-control">
                <option value="">Tous les types</option>
                <?php foreach ($types as $val => $label): ?>
                <option value="<?= $val ?>" <?= $filtreType === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <select name="statut" class="form-control">
                <option value="">Tous les statuts</option>
                <option value="planifiee" <?= $filtreStatut === 'planifiee' ? 'selected' : '' ?>>Planifiée</option>
                <option value="en_cours" <?= $filtreStatut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                <option value="terminee" <?= $filtreStatut === 'terminee' ? 'selected' : '' ?>>Terminée</option>
            </select>
            <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrer</button>
        </form>
    </div>

    <!-- Liste des réunions -->
    <div class="reunion-grid">
        <?php if (empty($reunions)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>Aucune réunion trouvée.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reunions as $r): ?>
            <div class="reunion-card">
                <div class="reunion-card-header">
                    <span class="reunion-type-badge type-<?= $r['type'] ?>"><?= htmlspecialchars($types[$r['type']] ?? $r['type']) ?></span>
                    <?= ReunionService::statutBadge($r['statut']) ?>
                </div>
                <h3 class="reunion-titre"><?= htmlspecialchars($r['titre']) ?></h3>
                <div class="reunion-meta">
                    <div><i class="fas fa-calendar"></i> <?= formatDateTime($r['date_debut']) ?></div>
                    <?php if ($r['lieu']): ?>
                    <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($r['lieu']) ?></div>
                    <?php endif; ?>
                    <?php if ($r['classe_nom']): ?>
                    <div><i class="fas fa-users"></i> <?= htmlspecialchars($r['classe_nom']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($r['description']): ?>
                <p class="reunion-desc"><?= nl2br(htmlspecialchars(mb_strimwidth($r['description'], 0, 150, '...'))) ?></p>
                <?php endif; ?>
                <div class="reunion-actions">
                    <a href="detail.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Détails</a>
                    <?php if (isParent() && $r['type'] === 'parents_profs' && $r['statut'] === 'planifiee'): ?>
                    <a href="reserver.php?reunion_id=<?= $r['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-bookmark"></i> Réserver</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
