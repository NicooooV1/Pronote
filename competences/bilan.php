<?php
/**
 * M38 – Compétences — Bilan élève
 */
$pageTitle = 'Bilan compétences';
require_once __DIR__ . '/includes/header.php';

$role = getUserRole();
$periodes = $compService->getPeriodes();
$periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

$eleveId = null;
$eleveNom = '';

if ($role === 'eleve') {
    $eleveId = getUserId();
    $eleveNom = getUserFullName();
} elseif ($role === 'parent') {
    // Sélection enfant
    $stmt = getPDO()->prepare("SELECT e.id, e.nom, e.prenom FROM parent_eleve pe JOIN eleves e ON pe.eleve_id = e.id WHERE pe.parent_id = ?");
    $stmt->execute([getUserId()]);
    $enfants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $eleveId = isset($_GET['eleve_id']) ? (int)$_GET['eleve_id'] : ($enfants[0]['id'] ?? 0);
    foreach ($enfants as $enf) {
        if ($enf['id'] == $eleveId) { $eleveNom = $enf['prenom'] . ' ' . $enf['nom']; break; }
    }
} else {
    // Admin/prof/vie_scolaire : recherche ou GET
    $eleveId = isset($_GET['eleve_id']) ? (int)$_GET['eleve_id'] : 0;
    if ($eleveId) {
        $stmt = getPDO()->prepare("SELECT nom, prenom FROM eleves WHERE id = ?");
        $stmt->execute([$eleveId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $eleveNom = $row ? $row['prenom'] . ' ' . $row['nom'] : '';
    }
}

$bilan = $eleveId ? $compService->getBilanEleve($eleveId, $periodeId ?: null) : [];
$niveaux = CompetenceService::niveauxLabels();
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> Bilan compétences</h1>
    </div>

    <?php if ($role === 'parent' && !empty($enfants)): ?>
        <div class="comp-selectors">
            <form method="get" class="comp-selector-form">
                <div class="form-group">
                    <label>Enfant</label>
                    <select name="eleve_id" onchange="this.form.submit()" class="form-select">
                        <?php foreach ($enfants as $enf): ?>
                            <option value="<?= $enf['id'] ?>" <?= $enf['id'] == $eleveId ? 'selected' : '' ?>><?= htmlspecialchars($enf['prenom'].' '.$enf['nom']) ?></option>
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
    <?php elseif (!in_array($role, ['eleve','parent'])): ?>
        <div class="comp-selectors">
            <form method="get" class="comp-selector-form">
                <div class="form-group">
                    <label>Élève (ID)</label>
                    <input type="number" name="eleve_id" value="<?= $eleveId ?>" class="form-control" placeholder="ID de l'élève">
                </div>
                <div class="form-group">
                    <label>Période</label>
                    <select name="periode_id" class="form-select">
                        <option value="0">Toutes</option>
                        <?php foreach ($periodes as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $periodeId ? 'selected' : '' ?>><?= htmlspecialchars($p['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Voir</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($eleveId && $eleveNom): ?>
        <h2 class="bilan-title"><?= htmlspecialchars($eleveNom) ?></h2>
    <?php endif; ?>

    <?php if (empty($bilan)): ?>
        <div class="empty-state"><p>Aucune évaluation trouvée.</p></div>
    <?php else: ?>

        <?php if ($ffRadarGraph && $eleveId): ?>
        <!-- Radar chart -->
        <div class="comp-radar-section" style="margin-bottom:24px;">
            <div style="background:white;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <h3 style="font-size:14px;font-weight:600;color:#2d3748;margin-bottom:12px;">
                    <i class="fas fa-chart-pie" style="margin-right:6px;color:#667eea;"></i> Vue radar des compétences
                </h3>
                <div data-radar-url="includes/ajax_radar.php?type=eleve&eleve_id=<?= $eleveId ?><?= $periodeId ? '&periode_id=' . $periodeId : '' ?>"
                     data-radar-canvas="canvas-radar-eleve"
                     data-radar-overlay-url="<?php
                        // Get classe_id for overlay
                        $stmtCl = getPDO()->prepare("SELECT classe_id FROM eleves WHERE id = ?");
                        $stmtCl->execute([$eleveId]);
                        $clId = $stmtCl->fetchColumn();
                        if ($clId) echo 'includes/ajax_radar.php?type=classe&classe_id=' . $clId . ($periodeId ? '&periode_id=' . $periodeId : '');
                     ?>">
                    <canvas id="canvas-radar-eleve" style="width:100%;height:350px;max-height:400px;"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bilan-grid">
            <?php foreach ($bilan as $domaine => $data): ?>
                <div class="bilan-domain-card">
                    <div class="bilan-domain-header">
                        <span class="bilan-domain-name"><?= htmlspecialchars($domaine) ?></span>
                        <?= CompetenceService::niveauBadge($data['niveau_moyen']) ?>
                    </div>
                    <div class="bilan-evals">
                        <?php foreach ($data['evaluations'] as $eval): ?>
                            <div class="bilan-eval-row">
                                <span class="bilan-comp-code"><?= htmlspecialchars($eval['code']) ?></span>
                                <span class="bilan-comp-name"><?= htmlspecialchars($eval['competence_nom']) ?></span>
                                <?= CompetenceService::niveauDot($eval['niveau_acquis']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
