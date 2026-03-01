<?php
/**
 * M31 – Infirmerie — Dashboard / passages
 */
$pageTitle = 'Infirmerie';
require_once __DIR__ . '/includes/header.php';

$isGestionnaire = isAdmin() || isPersonnelVS();
$orientations = InfirmerieService::orientations();

if ($isGestionnaire) {
    $stats = $infirmerieService->getStatsPassages();
    $filtres = [
        'date_debut' => $_GET['from'] ?? '',
        'date_fin' => $_GET['to'] ?? '',
        'orientation' => $_GET['orientation'] ?? '',
    ];
    $passages = $infirmerieService->getPassages(array_filter($filtres));
} elseif (isParent()) {
    $enfants = $infirmerieService->getEnfantsParent(getUserId());
    $passagesParEnfant = [];
    foreach ($enfants as $e) {
        $passagesParEnfant[$e['id']] = [
            'eleve' => $e,
            'passages' => $infirmerieService->getPassagesEleve($e['id']),
        ];
    }
} elseif (isEleve()) {
    $passages = $infirmerieService->getPassagesEleve(getUserId());
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-heartbeat"></i> Infirmerie</h1>
        <?php if ($isGestionnaire): ?>
        <a href="passage.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau passage</a>
        <?php endif; ?>
    </div>

    <?php if ($isGestionnaire): ?>
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card"><i class="fas fa-calendar-day"></i><div class="stat-value"><?= $stats['jour'] ?></div><div class="stat-label">Aujourd'hui</div></div>
        <div class="stat-card"><i class="fas fa-calendar-alt"></i><div class="stat-value"><?= $stats['mois'] ?></div><div class="stat-label">Ce mois</div></div>
        <div class="stat-card stat-warning"><i class="fas fa-home"></i><div class="stat-value"><?= $stats['renvoyes'] ?></div><div class="stat-label">Renvoyés</div></div>
        <div class="stat-card stat-danger"><i class="fas fa-ambulance"></i><div class="stat-value"><?= $stats['urgences'] ?></div><div class="stat-label">Urgences</div></div>
    </div>

    <!-- Filtres -->
    <form class="filter-bar" method="get">
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filtres['date_debut']) ?>">
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filtres['date_fin']) ?>">
        <select name="orientation" class="form-control">
            <option value="">Toutes orientations</option>
            <?php foreach ($orientations as $k => $v): ?><option value="<?= $k ?>" <?= ($filtres['orientation'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary"><i class="fas fa-filter"></i></button>
        <a href="infirmerie.php" class="btn btn-outline">Reset</a>
    </form>
    <?php endif; ?>

    <?php if (isParent()): ?>
    <!-- Parent : passages par enfant -->
    <?php foreach ($passagesParEnfant as $entry): $eleve = $entry['eleve']; $pass = $entry['passages']; ?>
    <div class="card">
        <div class="card-header"><h2><?= htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']) ?> <small>(<?= htmlspecialchars($eleve['classe_nom'] ?? '') ?>)</small></h2></div>
        <div class="card-body">
            <?php if (empty($pass)): ?><p class="text-muted">Aucun passage.</p>
            <?php else: foreach ($pass as $p): ?>
            <div class="passage-item orientation-<?= $p['orientation'] ?>">
                <div class="passage-date"><i class="fas fa-clock"></i> <?= formatDateTime($p['date_passage']) ?></div>
                <div class="passage-motif"><?= htmlspecialchars($p['motif']) ?></div>
                <?= InfirmerieService::orientationBadge($p['orientation']) ?>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>
    <!-- Data table for gestionnaire & eleve -->
    <?php if (empty($passages)): ?>
        <div class="empty-state"><i class="fas fa-heartbeat"></i><p>Aucun passage enregistré.</p></div>
    <?php else: ?>
    <div class="passages-list">
        <?php foreach ($passages as $p): ?>
        <div class="passage-item orientation-<?= $p['orientation'] ?>">
            <div class="passage-left">
                <div class="passage-date"><?= formatDateTime($p['date_passage']) ?></div>
                <?php if ($isGestionnaire): ?>
                <strong><?= htmlspecialchars($p['prenom'] . ' ' . $p['eleve_nom']) ?></strong>
                <span class="text-muted"><?= htmlspecialchars($p['classe_nom'] ?? '') ?></span>
                <?php endif; ?>
            </div>
            <div class="passage-center">
                <div class="passage-motif"><?= htmlspecialchars($p['motif']) ?></div>
                <?php if ($p['soins']): ?><small class="text-muted"><i class="fas fa-first-aid"></i> <?= htmlspecialchars($p['soins']) ?></small><?php endif; ?>
            </div>
            <div class="passage-right">
                <?= InfirmerieService::orientationBadge($p['orientation']) ?>
                <?php if ($p['notifier_parents']): ?><span class="badge badge-info"><i class="fas fa-bell"></i> Parents notifiés</span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
