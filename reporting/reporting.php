<?php
/**
 * M22 – Reporting — Tableau de bord rapports
 */
$pageTitle = 'Reporting & Statistiques';
require_once __DIR__ . '/includes/header.php';

$classes = $reportService->getClasses();
$classeId = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : ($classes[0]['id'] ?? 0);

$rapport = $classeId ? $reportService->getRapportClasse($classeId) : null;
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Reporting & Statistiques</h1>
        <a href="exporter.php" class="btn btn-primary"><i class="fas fa-file-csv"></i> Exporter</a>
    </div>

    <div class="report-filter">
        <form method="get">
            <label>Classe :</label>
            <select name="classe_id" onchange="this.form.submit()" class="form-select">
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $classeId ? 'selected' : '' ?>><?= htmlspecialchars($c['niveau'].' – '.$c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
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

        <!-- Liens d'export rapide -->
        <div class="report-export-links">
            <h3>Exports rapides</h3>
            <div class="export-cards">
                <a href="exporter.php?type=absences&classe_id=<?= $classeId ?>&go=1" class="export-card">
                    <i class="fas fa-calendar-times"></i>
                    <span>Absences CSV</span>
                </a>
                <a href="exporter.php?type=notes&classe_id=<?= $classeId ?>&go=1" class="export-card">
                    <i class="fas fa-chart-bar"></i>
                    <span>Notes CSV</span>
                </a>
                <a href="exporter.php?type=moyennes&classe_id=<?= $classeId ?>&go=1" class="export-card">
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
