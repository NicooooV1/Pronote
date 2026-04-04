<?php
/**
 * Statistiques générales — graphiques Chart.js
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();

// ── Données : effectifs par profil ──
$eleves = $pdo->query("SELECT COUNT(*) FROM eleves")->fetchColumn();
$profs = $pdo->query("SELECT COUNT(*) FROM professeurs")->fetchColumn();
$parents = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
$vs = $pdo->query("SELECT COUNT(*) FROM vie_scolaire")->fetchColumn();
$admins = $pdo->query("SELECT COUNT(*) FROM administrateurs")->fetchColumn();

// ── Répartition des notes ──
$notesDistrib = $pdo->query("
    SELECT
        SUM(CASE WHEN note < 5 THEN 1 ELSE 0 END) AS n0_5,
        SUM(CASE WHEN note >= 5 AND note < 10 THEN 1 ELSE 0 END) AS n5_10,
        SUM(CASE WHEN note >= 10 AND note < 15 THEN 1 ELSE 0 END) AS n10_15,
        SUM(CASE WHEN note >= 15 THEN 1 ELSE 0 END) AS n15_20,
        COUNT(*) AS total, ROUND(AVG(note),2) AS moyenne
    FROM notes
")->fetch(PDO::FETCH_ASSOC);

// ── Absences sur 30 jours ──
$absencesChart = $pdo->query("
    SELECT DATE(date_debut) AS d, COUNT(*) AS c
    FROM absences
    WHERE date_debut >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(date_debut) ORDER BY d
")->fetchAll(PDO::FETCH_ASSOC);
$absLabels = json_encode(array_map(fn($r) => date('d/m', strtotime($r['d'])), $absencesChart));
$absData = json_encode(array_column($absencesChart, 'c'));

// ── Messages sur 30 jours ──
$msgsChart = $pdo->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS c
    FROM messages
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY d
")->fetchAll(PDO::FETCH_ASSOC);
$msgLabels = json_encode(array_map(fn($r) => date('d/m', strtotime($r['d'])), $msgsChart));
$msgData = json_encode(array_column($msgsChart, 'c'));

// ── Effectifs par classe ──
$classesEff = $pdo->query("
    SELECT c.nom, COUNT(e.id) AS effectif
    FROM classes c LEFT JOIN eleves e ON e.classe = c.nom
    GROUP BY c.id, c.nom ORDER BY c.nom
")->fetchAll(PDO::FETCH_ASSOC);
$classLabels = json_encode(array_column($classesEff, 'nom'));
$classData = json_encode(array_column($classesEff, 'effectif'));

// ── Activité audit 7 derniers jours ──
$auditChart = $pdo->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS c
    FROM audit_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at) ORDER BY d
")->fetchAll(PDO::FETCH_ASSOC);
$auditLabels = json_encode(array_map(fn($r) => date('d/m', strtotime($r['d'])), $auditChart));
$auditData = json_encode(array_column($auditChart, 'c'));

$pageTitle = 'Statistiques';
$currentPage = 'stats';

$extraCss = ['../../assets/css/admin.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="kpis">
    <div class="kpi"><div class="v" style="color:#0f4c81"><?= $eleves ?></div><div class="l">Élèves</div></div>
    <div class="kpi"><div class="v" style="color:#2d7d46"><?= $profs ?></div><div class="l">Professeurs</div></div>
    <div class="kpi"><div class="v" style="color:#b45309"><?= $parents ?></div><div class="l">Parents</div></div>
    <div class="kpi"><div class="v" style="color:#6b21a8"><?= $vs + $admins ?></div><div class="l">Personnel</div></div>
    <div class="kpi"><div class="v" style="color:#333"><?= $notesDistrib['total'] ?? 0 ?></div><div class="l">Notes saisies</div></div>
    <div class="kpi"><div class="v" style="color:#dc2626"><?= $notesDistrib['moyenne'] ?? '-' ?> /20</div><div class="l">Moyenne générale</div></div>
</div>

<div class="stats-grid">
    <!-- Répartition utilisateurs -->
    <div class="chart-card">
        <h3><i class="fas fa-users"></i> Répartition des utilisateurs</h3>
        <canvas id="chartUsers"></canvas>
    </div>

    <!-- Distribution des notes -->
    <div class="chart-card">
        <h3><i class="fas fa-chart-bar"></i> Distribution des notes</h3>
        <canvas id="chartNotes"></canvas>
    </div>

    <!-- Absences 30j -->
    <div class="chart-card">
        <h3><i class="fas fa-calendar-times"></i> Absences (30 jours)</h3>
        <canvas id="chartAbsences"></canvas>
    </div>

    <!-- Messages 30j -->
    <div class="chart-card">
        <h3><i class="fas fa-envelope"></i> Messages (30 jours)</h3>
        <canvas id="chartMessages"></canvas>
    </div>

    <!-- Effectifs par classe -->
    <div class="chart-card">
        <h3><i class="fas fa-school"></i> Effectifs par classe</h3>
        <canvas id="chartClasses"></canvas>
    </div>

    <!-- Activité audit -->
    <div class="chart-card">
        <h3><i class="fas fa-shield-alt"></i> Activité audit (7 jours)</h3>
        <canvas id="chartAudit"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const colors = ['#0f4c81','#2d7d46','#b45309','#6b21a8','#dc2626'];

    // Utilisateurs (doughnut)
    new Chart(document.getElementById('chartUsers'), {
        type: 'doughnut',
        data: {
            labels: ['Élèves','Professeurs','Parents','Vie scolaire','Admins'],
            datasets: [{ data: [<?= $eleves ?>,<?= $profs ?>,<?= $parents ?>,<?= $vs ?>,<?= $admins ?>], backgroundColor: colors }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: {size:11} } } } }
    });

    // Notes (bar)
    new Chart(document.getElementById('chartNotes'), {
        type: 'bar',
        data: {
            labels: ['0-5','5-10','10-15','15-20'],
            datasets: [{ label: 'Élèves', data: [<?= $notesDistrib['n0_5'] ?? 0 ?>,<?= $notesDistrib['n5_10'] ?? 0 ?>,<?= $notesDistrib['n10_15'] ?? 0 ?>,<?= $notesDistrib['n15_20'] ?? 0 ?>],
                         backgroundColor: ['#dc2626','#f59e0b','#3b82f6','#10b981'] }]
        },
        options: { responsive: true, scales: {y:{beginAtZero:true}}, plugins: {legend:{display:false}} }
    });

    // Absences (line)
    new Chart(document.getElementById('chartAbsences'), {
        type: 'line',
        data: { labels: <?= $absLabels ?>, datasets: [{ label: 'Absences', data: <?= $absData ?>, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.08)', fill: true, tension: 0.3 }] },
        options: { responsive: true, scales: {y:{beginAtZero:true}}, plugins: {legend:{display:false}} }
    });

    // Messages (line)
    new Chart(document.getElementById('chartMessages'), {
        type: 'line',
        data: { labels: <?= $msgLabels ?>, datasets: [{ label: 'Messages', data: <?= $msgData ?>, borderColor: '#0f4c81', backgroundColor: 'rgba(15,76,129,0.08)', fill: true, tension: 0.3 }] },
        options: { responsive: true, scales: {y:{beginAtZero:true}}, plugins: {legend:{display:false}} }
    });

    // Classes (bar)
    new Chart(document.getElementById('chartClasses'), {
        type: 'bar',
        data: { labels: <?= $classLabels ?>, datasets: [{ label: 'Élèves', data: <?= $classData ?>, backgroundColor: '#0f4c81' }] },
        options: { responsive: true, scales: {y:{beginAtZero:true}}, plugins: {legend:{display:false}} }
    });

    // Audit (bar)
    new Chart(document.getElementById('chartAudit'), {
        type: 'bar',
        data: { labels: <?= $auditLabels ?>, datasets: [{ label: 'Actions', data: <?= $auditData ?>, backgroundColor: '#6b21a8' }] },
        options: { responsive: true, scales: {y:{beginAtZero:true}}, plugins: {legend:{display:false}} }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
