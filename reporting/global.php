<?php
/**
 * M22 – Reporting — Vue globale établissement
 * Dashboard avec KPI, comparaisons inter-classes, tendances mensuelles
 */
$pageTitle = 'Vue globale';
require_once __DIR__ . '/includes/header.php';

$statsGlobales   = $reportService->getStatsGlobales();
$moyParClasse    = $reportService->getMoyennesParClasse();
$evolution       = $reportService->getEvolutionMensuelle();
$repartitionNotes = $reportService->getRepartitionNotes();
$absParClasse    = $reportService->getAbsencesParClasse();
$tauxReussite    = $reportService->getTauxReussiteParClasse();
$topIncidents    = $reportService->getTopIncidents();
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-globe"></i> Vue globale de l'établissement</h1>
        <a href="reporting.php" class="btn btn-secondary"><i class="fas fa-chart-line"></i> Par classe</a>
    </div>

    <!-- KPI globaux -->
    <div class="report-stats-grid">
        <div class="report-stat-card">
            <i class="fas fa-user-graduate"></i>
            <div class="report-stat-value"><?= $statsGlobales['total_eleves'] ?></div>
            <div class="report-stat-label">Élèves actifs</div>
        </div>
        <div class="report-stat-card">
            <i class="fas fa-chalkboard-teacher"></i>
            <div class="report-stat-value"><?= $statsGlobales['total_profs'] ?></div>
            <div class="report-stat-label">Professeurs</div>
        </div>
        <div class="report-stat-card report-stat-primary">
            <i class="fas fa-chart-bar"></i>
            <div class="report-stat-value"><?= $statsGlobales['moyenne_globale'] ?>/20</div>
            <div class="report-stat-label">Moyenne établissement</div>
        </div>
        <div class="report-stat-card report-stat-warning">
            <i class="fas fa-calendar-times"></i>
            <div class="report-stat-value"><?= $statsGlobales['absences_mois'] ?></div>
            <div class="report-stat-label">Absences ce mois</div>
        </div>
        <div class="report-stat-card report-stat-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="report-stat-value"><?= $statsGlobales['incidents_ouverts'] ?></div>
            <div class="report-stat-label">Incidents ouverts</div>
        </div>
        <div class="report-stat-card report-stat-info">
            <i class="fas fa-file-invoice-dollar"></i>
            <div class="report-stat-value"><?= $statsGlobales['taux_recouvrement'] ?>%</div>
            <div class="report-stat-label">Recouvrement</div>
        </div>
    </div>

    <!-- Row 1 : Moyennes par classe + Évolution mensuelle -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Moyennes par classe</h5></div>
                <div class="card-body"><canvas id="chartMoyClasse" height="300"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Évolution mensuelle</h5></div>
                <div class="card-body"><canvas id="chartEvolution" height="300"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Row 2 : Répartition des notes + Taux de réussite -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Répartition des notes</h5></div>
                <div class="card-body"><canvas id="chartRepartition" height="280"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Taux de réussite par classe</h5></div>
                <div class="card-body"><canvas id="chartReussite" height="280"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Row 3 : Absences par classe (table) + Top incidents -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-calendar-times me-2"></i>Absences par classe</h5></div>
                <div class="card-body" style="max-height:400px;overflow-y:auto">
                    <table class="ds-table">
                        <thead><tr><th>Classe</th><th>Total</th><th>Justifiées</th><th>Élèves</th></tr></thead>
                        <tbody>
                        <?php foreach ($absParClasse as $ac): ?>
                            <tr>
                                <td><?= htmlspecialchars($ac['classe']) ?></td>
                                <td><strong><?= $ac['total_absences'] ?></strong></td>
                                <td><?= $ac['justifiees'] ?></td>
                                <td><?= $ac['eleves_concernes'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-gavel me-2"></i>Types d'incidents</h5></div>
                <div class="card-body" style="max-height:400px;overflow-y:auto">
                    <?php if (empty($topIncidents)): ?>
                        <p class="text-muted">Aucun incident enregistré.</p>
                    <?php else: ?>
                    <table class="ds-table">
                        <thead><tr><th>Type</th><th>Total</th><th>Résolus</th><th>En cours</th></tr></thead>
                        <tbody>
                        <?php foreach ($topIncidents as $inc): ?>
                            <tr>
                                <td><?= htmlspecialchars($inc['type']) ?></td>
                                <td><strong><?= $inc['total'] ?></strong></td>
                                <td class="text-success"><?= $inc['resolus'] ?></td>
                                <td class="text-warning"><?= $inc['total'] - $inc['resolus'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// ------ Data prep ------
<?php
    // Moyennes par classe
    $clLabels = array_column($moyParClasse, 'classe');
    $clValues = array_map(fn($r) => (float)$r['moyenne'], $moyParClasse);
    $clColors = array_map(fn($v) => $v >= 12 ? 'rgba(34,197,94,.7)' : ($v >= 10 ? 'rgba(234,179,8,.7)' : 'rgba(239,68,68,.7)'), $clValues);

    // Évolution mensuelle
    $evoLabels = array_column($evolution, 'mois');
    $evoAbs = array_column($evolution, 'absences');
    $evoRet = array_column($evolution, 'retards');

    // Répartition notes
    $repLabels = array_column($repartitionNotes, 'tranche');
    $repValues = array_column($repartitionNotes, 'count');

    // Taux réussite
    $trLabels = array_column($tauxReussite, 'classe');
    $trValues = array_map(fn($r) => (float)$r['taux'], $tauxReussite);
?>

// 1. Moyennes par classe (horizontal bar)
new Chart(document.getElementById('chartMoyClasse'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($clLabels) ?>,
        datasets: [{
            label: 'Moyenne /20',
            data: <?= json_encode($clValues) ?>,
            backgroundColor: <?= json_encode($clColors) ?>,
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        scales: { x: { beginAtZero: true, max: 20 } },
        plugins: { legend: { display: false } }
    }
});

// 2. Évolution mensuelle (line)
new Chart(document.getElementById('chartEvolution'), {
    type: 'line',
    data: {
        labels: <?= json_encode($evoLabels) ?>,
        datasets: [
            {
                label: 'Absences',
                data: <?= json_encode($evoAbs) ?>,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,.1)',
                fill: true, tension: .3
            },
            {
                label: 'Retards',
                data: <?= json_encode($evoRet) ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,.1)',
                fill: true, tension: .3
            }
        ]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { position: 'bottom' } }
    }
});

// 3. Répartition des notes (histogram-style bar)
new Chart(document.getElementById('chartRepartition'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($repLabels) ?>,
        datasets: [{
            label: 'Nombre de notes',
            data: <?= json_encode($repValues) ?>,
            backgroundColor: [
                '#ef4444','#f97316','#f59e0b','#eab308',
                '#84cc16','#22c55e','#10b981','#059669'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { display: false } }
    }
});

// 4. Taux de réussite (horizontal bar)
new Chart(document.getElementById('chartReussite'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($trLabels) ?>,
        datasets: [{
            label: '% réussite (≥10/20)',
            data: <?= json_encode($trValues) ?>,
            backgroundColor: <?= json_encode(array_map(fn($v) => $v >= 75 ? 'rgba(34,197,94,.7)' : ($v >= 50 ? 'rgba(234,179,8,.7)' : 'rgba(239,68,68,.7)'), $trValues)) ?>,
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        scales: { x: { beginAtZero: true, max: 100 } },
        plugins: { legend: { display: false } }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
