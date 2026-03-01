<?php
/**
 * M23 – Journal d'audit (admin)
 */
$pageTitle = 'Journal d\'audit';
$activePage = 'audit';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin()) { redirect('/accueil/accueil.php'); }

$filtres = [
    'action' => $_GET['action'] ?? '',
    'date_debut' => $_GET['from'] ?? '',
    'date_fin' => $_GET['to'] ?? '',
];
$logs = $rgpdService->getAuditLogs(array_filter($filtres));
$stats = $rgpdService->getAuditStatsProper();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-history"></i> Journal d'audit</h1>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><i class="fas fa-database"></i><div class="stat-value"><?= number_format($stats['total']) ?></div><div class="stat-label">Total entrées</div></div>
        <div class="stat-card"><i class="fas fa-calendar-day"></i><div class="stat-value"><?= $stats['today'] ?></div><div class="stat-label">Aujourd'hui</div></div>
        <div class="stat-card"><i class="fas fa-calendar-alt"></i><div class="stat-value"><?= $stats['month'] ?></div><div class="stat-label">Ce mois</div></div>
    </div>

    <form class="filter-bar" method="get">
        <input type="text" name="action" class="form-control" placeholder="Rechercher action…" value="<?= htmlspecialchars($filtres['action']) ?>">
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filtres['date_debut']) ?>">
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filtres['date_fin']) ?>">
        <button class="btn btn-primary"><i class="fas fa-filter"></i></button>
        <a href="audit.php" class="btn btn-outline">Reset</a>
    </form>

    <?php if (empty($logs)): ?>
        <div class="empty-state"><i class="fas fa-history"></i><p>Aucune entrée d'audit trouvée.</p></div>
    <?php else: ?>
    <div class="audit-table-wrapper">
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détails</th><th>IP</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="nowrap"><?= htmlspecialchars($log['created_at'] ?? $log['date'] ?? '') ?></td>
                    <td><?= htmlspecialchars(($log['user_type'] ?? '') . ' #' . ($log['user_id'] ?? '')) ?></td>
                    <td><span class="action-tag"><?= htmlspecialchars($log['action'] ?? '') ?></span></td>
                    <td class="details-cell"><?= htmlspecialchars(substr($log['details'] ?? $log['description'] ?? '', 0, 120)) ?></td>
                    <td class="nowrap"><?= htmlspecialchars($log['ip_address'] ?? $log['ip'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
