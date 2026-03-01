<?php
/**
 * M28 – Orientation — Liste des fiches
 */
$pageTitle = 'Orientation';
require_once __DIR__ . '/includes/header.php';

$classes = $orientationService->getClasses();
$filtreClasse = $_GET['classe'] ?? '';
$filtreStatut = $_GET['statut'] ?? '';

if (isEleve()) {
    // Élève: redirige vers sa fiche
    header('Location: fiche.php');
    exit;
} elseif (isParent()) {
    $enfants = $orientationService->getEnfantsParent(getUserId());
} else {
    // Prof / Admin / VS
    $filters = [];
    if ($filtreClasse) $filters['classe_id'] = $filtreClasse;
    if ($filtreStatut) $filters['statut'] = $filtreStatut;
    $fiches = $orientationService->getFiches($filters);
    $stats = $orientationService->getStats();
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-compass"></i> Orientation</h1>
    </div>

    <?php if (isParent()): ?>
    <!-- Vue parent -->
    <div class="enfants-grid">
        <?php foreach ($enfants as $enf): ?>
        <?php $fiche = $orientationService->getFicheEleve($enf['id']); ?>
        <div class="enfant-card">
            <div class="enfant-avatar"><?= strtoupper(substr($enf['prenom'], 0, 1) . substr($enf['nom'], 0, 1)) ?></div>
            <h3><?= htmlspecialchars($enf['prenom'] . ' ' . $enf['nom']) ?></h3>
            <p><?= htmlspecialchars($enf['classe_nom'] ?? '') ?></p>
            <?php if ($fiche): ?>
                <?= OrientationService::statutBadge($fiche['statut']) ?>
                <a href="voir.php?id=<?= $fiche['id'] ?>" class="btn btn-sm btn-primary">Voir la fiche</a>
            <?php else: ?>
                <span class="text-muted">Pas de fiche</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- Vue prof/admin -->
    <?php if (isset($stats)): ?>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Fiches</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['brouillons'] ?></div><div class="stat-label">Brouillons</div></div>
        <div class="stat-card stat-info"><div class="stat-value"><?= $stats['soumises'] ?></div><div class="stat-label">Soumises</div></div>
        <div class="stat-card stat-success"><div class="stat-value"><?= $stats['validees'] ?></div><div class="stat-label">Validées</div></div>
    </div>
    <?php endif; ?>

    <div class="filter-row">
        <form method="get" class="filter-form">
            <select name="classe" class="form-control" onchange="this.form.submit()">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filtreClasse == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="statut" class="form-control" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                <option value="brouillon" <?= $filtreStatut === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                <option value="soumise" <?= $filtreStatut === 'soumise' ? 'selected' : '' ?>>Soumise</option>
                <option value="validee" <?= $filtreStatut === 'validee' ? 'selected' : '' ?>>Validée</option>
            </select>
        </form>
    </div>

    <?php if (empty($fiches)): ?>
        <div class="empty-state"><i class="fas fa-compass"></i><p>Aucune fiche d'orientation.</p></div>
    <?php else: ?>
    <div class="fiche-list">
        <?php foreach ($fiches as $f): ?>
        <div class="fiche-item">
            <div class="fiche-avatar"><?= strtoupper(substr($f['prenom'], 0, 1) . substr($f['eleve_nom'], 0, 1)) ?></div>
            <div class="fiche-info">
                <h3><?= htmlspecialchars($f['prenom'] . ' ' . $f['eleve_nom']) ?></h3>
                <div class="fiche-meta">
                    <?= OrientationService::statutBadge($f['statut']) ?>
                    <span><?= htmlspecialchars($f['classe_nom'] ?? '') ?></span>
                    <span><?= $f['annee_scolaire'] ?></span>
                </div>
            </div>
            <a href="voir.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
