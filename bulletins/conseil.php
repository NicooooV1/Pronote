<?php
/**
 * Bulletins — Conseil de classe (vue d'ensemble pour saisie rapide)
 */
require_once __DIR__ . '/includes/BulletinService.php';
$currentPage = 'conseil';
$pageTitle = 'Conseil de classe';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    header('Location: bulletins.php');
    exit;
}

$pdo = getPDO();
$service = new BulletinService($pdo);
$classes = $service->getClasses();
$periodes = $service->getPeriodes();

$selectedClasse = (int)($_GET['classe'] ?? ($classes[0]['id'] ?? 0));
$selectedPeriode = (int)($_GET['periode'] ?? 0);
if (!$selectedPeriode && !empty($periodes)) {
    $courante = $service->getPeriodeCourante();
    $selectedPeriode = $courante ? $courante['id'] : $periodes[0]['id'];
}

$bulletins = $selectedClasse ? $service->getBulletinsClasse($selectedClasse, $selectedPeriode) : [];
$stats = $selectedClasse ? $service->getStatsClasse($selectedClasse, $selectedPeriode) : [];
$avisLabels = BulletinService::avisLabels();

// POST: Saisie rapide des avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $avisData = $_POST['avis'] ?? [];
    foreach ($avisData as $bulletinId => $avis) {
        $service->sauvegarderAvisConseil((int)$bulletinId, $avis);
    }
    $appreciations = $_POST['appreciation'] ?? [];
    foreach ($appreciations as $bulletinId => $text) {
        $service->sauvegarderAppreciation((int)$bulletinId, $text);
    }
    header("Location: conseil.php?classe={$selectedClasse}&periode={$selectedPeriode}&saved=1");
    exit;
}
?>

<div class="page-header">
    <h1><i class="fas fa-user-tie"></i> Conseil de classe</h1>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> Avis et appréciations enregistrés.</div>
<?php endif; ?>

<div class="filter-bar">
    <form method="GET" class="filter-form">
        <select name="classe" class="form-select" onchange="this.form.submit()">
            <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $selectedClasse == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="periode" class="form-select" onchange="this.form.submit()">
            <?php foreach ($periodes as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $selectedPeriode == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nom']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!empty($stats) && $stats['total'] > 0): ?>
<div class="stats-row">
    <div class="stat-card"><span class="stat-value"><?= $stats['total'] ?></span><span class="stat-label">Élèves</span></div>
    <div class="stat-card primary"><span class="stat-value"><?= $stats['moy_classe'] ? number_format($stats['moy_classe'], 2) : '-' ?></span><span class="stat-label">Moyenne</span></div>
    <div class="stat-card success"><span class="stat-value"><?= $stats['moy_max'] ? number_format($stats['moy_max'], 2) : '-' ?></span><span class="stat-label">Max</span></div>
    <div class="stat-card danger"><span class="stat-value"><?= $stats['moy_min'] ? number_format($stats['moy_min'], 2) : '-' ?></span><span class="stat-label">Min</span></div>
</div>
<?php endif; ?>

<?php if (!empty($bulletins)): ?>
<form method="POST" class="conseil-form">
    <?= csrfField() ?>
    <div class="data-table-container">
        <table class="data-table conseil-table">
            <thead>
                <tr>
                    <th>Élève</th>
                    <th class="text-center">Moy.</th>
                    <th class="text-center">Rang</th>
                    <th>Avis du conseil</th>
                    <th>Appréciation rapide</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bulletins as $b): ?>
                <tr>
                    <td class="fw-500"><?= htmlspecialchars($b['eleve_prenom'] . ' ' . $b['eleve_nom']) ?></td>
                    <td class="text-center fw-bold <?= ($b['moyenne_generale'] ?? 0) < 10 ? 'text-danger' : '' ?>"><?= $b['moyenne_generale'] !== null ? number_format($b['moyenne_generale'], 2) : '-' ?></td>
                    <td class="text-center"><?= $b['rang'] ?? '-' ?></td>
                    <td>
                        <select name="avis[<?= $b['id'] ?>]" class="form-select form-select-sm">
                            <?php foreach ($avisLabels as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($b['avis_conseil'] ?? 'aucun') === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <textarea name="appreciation[<?= $b['id'] ?>]" rows="1" class="form-control form-control-sm" placeholder="Appréciation..."><?= htmlspecialchars($b['appreciation_generale'] ?? '') ?></textarea>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer tout</button>
    </div>
</form>
<?php else: ?>
<div class="empty-state">
    <i class="fas fa-clipboard-list"></i>
    <p>Aucun bulletin généré pour cette classe et période. <a href="generer.php?classe=<?= $selectedClasse ?>&periode=<?= $selectedPeriode ?>">Générer les bulletins d'abord.</a></p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
