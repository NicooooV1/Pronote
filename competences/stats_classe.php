<?php
/**
 * M38 – Compétences — Statistiques classe
 */
$pageTitle = 'Statistiques compétences par classe';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isTeacher() && !isVieScolaire()) {
    redirect('../accueil/accueil.php');
}

$classes = $compService->getClasses();
$periodes = $compService->getPeriodes();
$classeId = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : ($classes[0]['id'] ?? 0);
$periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

$stats = $classeId ? $compService->getStatsClasse($classeId, $periodeId ?: null) : [];
$niveaux = CompetenceService::niveauxLabels();
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-bar"></i> Statistiques compétences</h1>
    </div>

    <div class="comp-selectors">
        <form method="get" class="comp-selector-form">
            <div class="form-group">
                <label>Classe</label>
                <select name="classe_id" onchange="this.form.submit()" class="form-select">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $classeId ? 'selected' : '' ?>><?= htmlspecialchars($c['niveau'].' – '.$c['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Période</label>
                <select name="periode_id" onchange="this.form.submit()" class="form-select">
                    <option value="0">Toutes</option>
                    <?php foreach ($periodes as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $periodeId ? 'selected' : '' ?>><?= htmlspecialchars($p['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if (empty($stats)): ?>
        <div class="empty-state"><p>Aucune évaluation enregistrée pour cette classe.</p></div>
    <?php else: ?>
        <div class="stats-comp-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Compétence</th>
                        <th>Domaine</th>
                        <th class="text-center">Distribution</th>
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $s): ?>
                        <tr>
                            <td><span class="comp-code-sm"><?= htmlspecialchars($s['code']) ?></span></td>
                            <td><?= htmlspecialchars($s['nom']) ?></td>
                            <td><small><?= htmlspecialchars($s['domaine']) ?></small></td>
                            <td>
                                <div class="comp-distrib">
                                    <?php 
                                    $colors = ['non_acquis' => '#ef4444', 'en_cours' => '#f59e0b', 'acquis' => '#10b981', 'depasse' => '#3b82f6'];
                                    foreach ($colors as $niv => $col):
                                        $pct = $s['total'] > 0 ? ($s['distribution'][$niv] / $s['total'] * 100) : 0;
                                    ?>
                                        <div class="comp-distrib-bar" style="width:<?= $pct ?>%;background:<?= $col ?>" title="<?= $niveaux[$niv] ?? $niv ?>: <?= $s['distribution'][$niv] ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="text-center"><?= $s['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Légende -->
        <div class="comp-legend mt-1">
            <?php foreach ($colors as $niv => $col): ?>
                <span class="comp-dot" style="background:<?= $col ?>"></span> <span><?= $niveaux[$niv] ?? $niv ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
