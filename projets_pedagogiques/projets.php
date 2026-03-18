<?php
$activePage = 'projets';
require_once __DIR__ . '/includes/header.php';

$user     = $_SESSION['user'];
$role     = $user['type'] ?? 'eleve';
$filtres  = ['statut' => $_GET['statut'] ?? '', 'type' => $_GET['type'] ?? ''];
if ($role === 'professeur') $filtres['responsable_id'] = $user['id'] ?? null;
$projets  = $projetService->getProjets($filtres);
$stats    = $projetService->getStats();
$types    = ProjetPedagogiqueService::typesLabels();
$statuts  = ProjetPedagogiqueService::statutLabels();
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-project-diagram me-2"></i>Projets pédagogiques</h2>
        <?php if (in_array($role, ['admin', 'professeur'])): ?>
            <a href="creer.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nouveau projet</a>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat-card bg-primary-light"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div></div>
        <div class="col-md-4"><div class="stat-card bg-warning-light"><div class="stat-value"><?= $stats['en_cours'] ?></div><div class="stat-label">En cours</div></div></div>
        <div class="col-md-4"><div class="stat-card bg-success-light"><div class="stat-value"><?= $stats['termines'] ?></div><div class="stat-label">Terminés</div></div></div>
    </div>

    <!-- Filtres -->
    <form method="get" class="row g-2 mb-4 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Type</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">Tous</option>
                <?php foreach ($types as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($filtres['type'] === $k) ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select form-select-sm">
                <option value="">Tous</option>
                <?php foreach ($statuts as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($filtres['statut'] === $k) ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
    </form>

    <!-- Liste -->
    <div class="projets-grid">
        <?php if (empty($projets)): ?>
            <div class="alert alert-info">Aucun projet trouvé.</div>
        <?php endif; ?>
        <?php foreach ($projets as $p): ?>
            <div class="projet-card">
                <div class="projet-card-header">
                    <span class="projet-type badge-type-<?= htmlspecialchars($p['type']) ?>"><?= $types[$p['type']] ?? $p['type'] ?></span>
                    <?= ProjetPedagogiqueService::statutBadge($p['statut']) ?>
                </div>
                <h5 class="projet-titre"><?= htmlspecialchars($p['titre']) ?></h5>
                <p class="text-muted small mb-1"><i class="fas fa-user me-1"></i><?= htmlspecialchars($p['responsable_nom'] ?? '—') ?></p>
                <p class="text-muted small mb-2">
                    <i class="fas fa-calendar me-1"></i><?= date('d/m/Y', strtotime($p['date_debut'])) ?>
                    <?php if ($p['date_fin']): ?> → <?= date('d/m/Y', strtotime($p['date_fin'])) ?><?php endif; ?>
                </p>
                <?php if ($p['budget']): ?>
                    <p class="small mb-2"><i class="fas fa-euro-sign me-1"></i><?= number_format((float)$p['budget'], 2, ',', ' ') ?> €</p>
                <?php endif; ?>
                <a href="detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary w-100 mt-auto">Voir le projet</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
