<?php
/**
 * M18 – Cantine — Statistiques
 */
$activePage = 'statistiques';
$pageTitle = 'Cantine — Statistiques';
require_once __DIR__ . '/includes/header.php';

if (!$isGestionnaire) { header('Location: menus.php'); exit; }

$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');
$stats = $cantineService->getStats($dateDebut, $dateFin);
$regimes = $cantineService->getStatsParRegime(date('Y-m-d'));
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> Statistiques cantine</h1>
    </div>

    <form method="get" class="filter-form">
        <div class="form-row">
            <div class="form-group"><label>Début</label><input type="date" name="date_debut" value="<?= $dateDebut ?>" class="form-control"></div>
            <div class="form-group"><label>Fin</label><input type="date" name="date_fin" value="<?= $dateFin ?>" class="form-control"></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
        </div>
    </form>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['total_reservations'] ?? 0 ?></div><div class="stat-label">Réservations</div></div>
        <div class="stat-card stat-success"><div class="stat-value"><?= $stats['consommes'] ?? 0 ?></div><div class="stat-label">Repas servis</div></div>
        <div class="stat-card stat-danger"><div class="stat-value"><?= $stats['annules'] ?? 0 ?></div><div class="stat-label">Annulations</div></div>
        <div class="stat-card stat-info"><div class="stat-value"><?= $stats['nb_eleves'] ?? 0 ?></div><div class="stat-label">Élèves différents</div></div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Répartition par régime (aujourd'hui)</h3></div>
        <div class="card-body">
            <?php if (!empty($regimes)): ?>
            <table class="table">
                <thead><tr><th>Régime</th><th>Nombre</th></tr></thead>
                <tbody>
                <?php foreach ($regimes as $r): ?>
                    <tr><td><?= htmlspecialchars(ucfirst($r['regime'])) ?></td><td><strong><?= $r['nb'] ?></strong></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p class="text-muted">Aucune réservation aujourd'hui.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
