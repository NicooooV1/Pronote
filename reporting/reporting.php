<?php
/**
 * M22 – Reporting — Tableau de bord par classe
 */
$pageTitle = 'Reporting & Statistiques';
require_once __DIR__ . '/includes/header.php';

$classes = $reportService->getClasses();
$periodes = $reportService->getPeriodes();
$classeId = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : ($classes[0]['id'] ?? 0);
$periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

$rapport = $classeId ? $reportService->getRapportClasse($classeId, $periodeId ?: null) : null;
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Reporting & Statistiques</h1>
        <div>
            <a href="global.php" class="btn btn-secondary"><i class="fas fa-globe"></i> Vue globale</a>
            <a href="exporter.php" class="btn btn-primary"><i class="fas fa-file-csv"></i> Exporter</a>
        </div>
    </div>

    <div class="report-filter">
        <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
            <div>
                <label>Classe :</label>
                <select name="classe_id" onchange="this.form.submit()" class="form-select">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $classeId ? 'selected' : '' ?>><?= htmlspecialchars($c['niveau'].' – '.$c['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Période :</label>
                <select name="periode_id" onchange="this.form.submit()" class="form-select">
                    <option value="0">Toutes les périodes</option>
                    <?php foreach ($periodes as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($p['id'] ?? 0) == $periodeId ? 'selected' : '' ?>><?= htmlspecialchars($p['nom'] ?? $p['libelle'] ?? 'Période '.$p['id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($rapport): ?>
        <h2 class="report-class-title">
            Rapport : <?= htmlspecialchars(($rapport['classe']['niveau'] ?? '').' – '.($rapport['classe']['nom'] ?? '')) ?>
        </h2>

        <div class="report-stats-grid">
            <div class="report-stat-card">
                <i class="fas fa-users"></i>
                <div class="report-stat-value"><?= $rapport['effectif'] ?></div>
                <div class="report-stat-label">Élèves</div>
            </div>
            <div class="report-stat-card report-stat-primary">
                <i class="fas fa-chart-bar"></i>
                <div class="report-stat-value"><?= $rapport['moyenne_classe'] ?>/20</div>
                <div class="report-stat-label">Moyenne classe</div>
            </div>
            <div class="report-stat-card report-stat-warning">
                <i class="fas fa-calendar-times"></i>
                <div class="report-stat-value"><?= $rapport['total_absences'] ?></div>
                <div class="report-stat-label">Absences</div>
            </div>
            <div class="report-stat-card report-stat-info">
                <i class="fas fa-clock"></i>
                <div class="report-stat-value"><?= $rapport['total_retards'] ?></div>
                <div class="report-stat-label">Retards</div>
            </div>
            <div class="report-stat-card report-stat-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="report-stat-value"><?= $rapport['nb_incidents'] ?></div>
                <div class="report-stat-label">Incidents</div>
            </div>
        </div>

        <!-- ===== Graphiques Chart.js ===== -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Moyennes par matière</h5></div>
                    <div class="card-body"><canvas id="chartMoyennes" height="260"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-doughnut me-2"></i>Répartition absences / retards / incidents</h5></div>
                    <div class="card-body"><canvas id="chartRepartition" height="260"></canvas></div>
                </div>
            </div>
        </div>

        <!-- ===== Classement élèves ===== -->
        <?php if (!empty($rapport['classement'])): ?>
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Classement des élèves</h5></div>
            <div class="card-body">
                <table class="ds-table">
                    <thead>
                        <tr><th>#</th><th>Nom</th><th>Prénom</th><th>Moyenne</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rapport['classement'] as $i => $el): ?>
                        <tr>
                            <td><strong><?= $i + 1 ?></strong></td>
                            <td><?= htmlspecialchars($el['nom']) ?></td>
                            <td><?= htmlspecialchars($el['prenom']) ?></td>
                            <td>
                                <strong class="<?= ($el['moyenne'] ?? 0) >= 10 ? 'text-success' : 'text-danger' ?>">
                                    <?= $el['moyenne'] ?? '-' ?>/20
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
        <?php
            $moyMat = $rapport['moyennes_matieres'] ?? [];
            $matLabels = []; $matValues = [];
            foreach ($moyMat as $m) {
                $matLabels[] = $m['matiere'] ?? $m['nom'] ?? '?';
                $matValues[] = round((float)($m['moyenne'] ?? 0), 2);
            }
        ?>
        // Bar chart — Moyennes par matière
        new Chart(document.getElementById('chartMoyennes'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($matLabels) ?>,
                datasets: [{
                    label: 'Moyenne /20',
                    data: <?= json_encode($matValues) ?>,
                    backgroundColor: 'rgba(99,102,241,.6)',
                    borderColor: 'rgba(99,102,241,1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: 20 } },
                plugins: { legend: { display: false } }
            }
        });

        // Doughnut — Absences / Retards / Incidents
        new Chart(document.getElementById('chartRepartition'), {
            type: 'doughnut',
            data: {
                labels: ['Absences', 'Retards', 'Incidents'],
                datasets: [{
                    data: [<?= (int)$rapport['total_absences'] ?>, <?= (int)$rapport['total_retards'] ?>, <?= (int)$rapport['nb_incidents'] ?>],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#ef4444']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
        </script>

        <div class="report-export-links">
            <h3>Exports rapides</h3>
            <div class="export-cards">
                <a href="exporter.php?type=absences&classe_id=<?= $classeId ?>&go=1" class="export-card">
                    <i class="fas fa-calendar-times"></i>
                    <span>Absences CSV</span>
                </a>
                <a href="exporter.php?type=notes&classe_id=<?= $classeId ?>&periode_id=<?= $periodeId ?>&go=1" class="export-card">
                    <i class="fas fa-chart-bar"></i>
                    <span>Notes CSV</span>
                </a>
                <a href="exporter.php?type=moyennes&classe_id=<?= $classeId ?>&periode_id=<?= $periodeId ?>&go=1" class="export-card">
                    <i class="fas fa-calculator"></i>
                    <span>Moyennes CSV</span>
                </a>
                <a href="exporter.php?type=incidents&classe_id=<?= $classeId ?>&go=1" class="export-card">
                    <i class="fas fa-gavel"></i>
                    <span>Incidents CSV</span>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state"><p>Sélectionnez une classe pour voir le rapport.</p></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
