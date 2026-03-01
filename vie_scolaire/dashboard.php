<?php
/**
 * Vie scolaire — Tableau de bord consolidé
 */
require_once __DIR__ . '/includes/VieScolaireService.php';
$currentPage = 'dashboard';
$pageTitle = 'Vie scolaire — Tableau de bord';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire()) {
    header('Location: ../accueil/accueil.php');
    exit;
}

$pdo = getPDO();
$service = new VieScolaireService($pdo);
$stats = $service->getStatsJour();
$elevesASurveiller = $service->getElevesASurveiller();
$timeline = $service->getTimeline(20);
?>

<div class="page-header">
    <h1><i class="fas fa-shield-alt"></i> Vie scolaire — <?= date('d/m/Y') ?></h1>
</div>

<!-- Indicateurs du jour -->
<div class="vs-stats-grid">
    <div class="vs-stat-card vs-stat-danger">
        <div class="vs-stat-icon"><i class="fas fa-user-times"></i></div>
        <div class="vs-stat-body">
            <span class="vs-stat-value"><?= $stats['absences_jour'] ?></span>
            <span class="vs-stat-label">Absences aujourd'hui</span>
        </div>
    </div>
    <div class="vs-stat-card vs-stat-warning">
        <div class="vs-stat-icon"><i class="fas fa-clock"></i></div>
        <div class="vs-stat-body">
            <span class="vs-stat-value"><?= $stats['retards_jour'] ?></span>
            <span class="vs-stat-label">Retards aujourd'hui</span>
        </div>
    </div>
    <div class="vs-stat-card vs-stat-primary">
        <div class="vs-stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="vs-stat-body">
            <span class="vs-stat-value"><?= $stats['incidents_ouverts'] ?></span>
            <span class="vs-stat-label">Incidents ouverts</span>
        </div>
    </div>
    <div class="vs-stat-card vs-stat-info">
        <div class="vs-stat-icon"><i class="fas fa-file-alt"></i></div>
        <div class="vs-stat-body">
            <span class="vs-stat-value"><?= $stats['justificatifs_attente'] ?></span>
            <span class="vs-stat-label">Justificatifs en attente</span>
        </div>
    </div>
    <?php if (isset($stats['appels_en_cours'])): ?>
    <div class="vs-stat-card vs-stat-secondary">
        <div class="vs-stat-icon"><i class="fas fa-clipboard-check"></i></div>
        <div class="vs-stat-body">
            <span class="vs-stat-value"><?= $stats['appels_en_cours'] ?></span>
            <span class="vs-stat-label">Appels en cours</span>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($stats['retenues_planifiees'])): ?>
    <div class="vs-stat-card vs-stat-secondary">
        <div class="vs-stat-icon"><i class="fas fa-door-closed"></i></div>
        <div class="vs-stat-body">
            <span class="vs-stat-value"><?= $stats['retenues_planifiees'] ?></span>
            <span class="vs-stat-label">Retenues planifiées</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="vs-dashboard-grid">
    <!-- Élèves à surveiller -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-eye"></i> Élèves à surveiller</h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($elevesASurveiller)): ?>
                <p class="text-muted p-1">Aucun élève en alerte.</p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Élève</th><th>Classe</th><th class="text-center">Abs. inj.</th><th class="text-center">Retards</th><th class="text-center">Incidents</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($elevesASurveiller as $e): ?>
                    <tr>
                        <td class="fw-500"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></td>
                        <td><?= htmlspecialchars($e['classe']) ?></td>
                        <td class="text-center"><span class="badge badge-danger"><?= $e['abs_injustifiees'] ?></span></td>
                        <td class="text-center"><span class="badge badge-warning"><?= $e['nb_retards'] ?></span></td>
                        <td class="text-center"><span class="badge badge-primary"><?= $e['nb_incidents'] ?></span></td>
                        <td><a href="suivi_eleve.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-arrow-right"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Timeline activité -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-stream"></i> Activité récente</h3></div>
        <div class="card-body">
            <div class="vs-timeline">
                <?php foreach ($timeline as $ev):
                    $icons = ['absence' => 'fa-user-times text-danger', 'retard' => 'fa-clock text-warning', 'incident' => 'fa-exclamation-triangle text-primary'];
                    $typeLabels = ['absence' => 'Absence', 'retard' => 'Retard', 'incident' => 'Incident'];
                ?>
                <div class="vs-timeline-item">
                    <div class="vs-timeline-icon"><i class="fas <?= $icons[$ev['type']] ?? 'fa-circle' ?>"></i></div>
                    <div class="vs-timeline-content">
                        <span class="vs-timeline-type"><?= $typeLabels[$ev['type']] ?? $ev['type'] ?></span>
                        <strong><?= htmlspecialchars($ev['eleve']) ?></strong>
                        <span class="text-muted">(<?= htmlspecialchars($ev['classe']) ?>)</span>
                        — <?= htmlspecialchars($ev['detail'] ?? '') ?>
                        <span class="vs-timeline-date"><?= formatDateTime($ev['date_event']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($timeline)): ?>
                <p class="text-muted">Aucune activité récente.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Accès rapides -->
<div class="vs-quick-links">
    <a href="../absences/absences.php" class="vs-quick-link"><i class="fas fa-calendar-times"></i> Absences</a>
    <a href="../absences/justificatifs.php" class="vs-quick-link"><i class="fas fa-file-medical"></i> Justificatifs</a>
    <a href="../discipline/incidents.php" class="vs-quick-link"><i class="fas fa-gavel"></i> Incidents</a>
    <a href="../discipline/retenues.php" class="vs-quick-link"><i class="fas fa-door-closed"></i> Retenues</a>
    <a href="../appel/historique.php" class="vs-quick-link"><i class="fas fa-clipboard-list"></i> Historique appel</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
