<?php
/**
 * M38 – Compétences — Évaluer (professeur & admin)
 */
$pageTitle = 'Évaluer les compétences';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isTeacher()) {
    redirect('../accueil/accueil.php');
}

$classes = $compService->getClasses();
$periodes = $compService->getPeriodes();
$domaines = $compService->getDomaines();
$niveaux = CompetenceService::niveauxLabels();

$classeId = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : ($classes[0]['id'] ?? 0);
$domaineId = isset($_GET['domaine_id']) ? (int)$_GET['domaine_id'] : ($domaines[0]['id'] ?? 0);
$periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

$eleves = $classeId ? $compService->getElevesClasse($classeId) : [];
$sousComp = $domaineId ? $compService->getSousCompetences($domaineId) : [];

$success = '';
$error = '';

// Traitement évaluation en lot
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Jeton de sécurité invalide. Veuillez recharger la page.';
    } else {
    $compId = (int)$_POST['competence_id'];
    $evals = $_POST['niveaux'] ?? [];
    $profId = getUserId();
    $matiereId = null;

    // Récupérer la matière du professeur si possible
    if (isTeacher()) {
        $stmt = getPDO()->prepare("SELECT matiere_id FROM professeurs WHERE id = ?");
        $stmt->execute([$profId]);
        $matiereId = (int)$stmt->fetchColumn() ?: null;
    }

    $count = $compService->evaluerLot($compId, $profId, $matiereId, $periodeId ?: null, $evals);
    $success = "$count évaluation(s) enregistrée(s).";
    }
}
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-check-double"></i> Évaluer les compétences</h1>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Sélecteurs -->
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
                <label>Domaine</label>
                <select name="domaine_id" onchange="this.form.submit()" class="form-select">
                    <?php foreach ($domaines as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $d['id'] == $domaineId ? 'selected' : '' ?>><?= htmlspecialchars($d['code'].' – '.$d['nom']) ?></option>
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

    <?php if (!empty($sousComp) && !empty($eleves)): ?>
        <?php foreach ($sousComp as $comp): ?>
            <div class="card mb-1">
                <div class="card-header">
                    <h3><span class="comp-code-sm"><?= htmlspecialchars($comp['code']) ?></span> <?= htmlspecialchars($comp['nom']) ?></h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="competence_id" value="<?= $comp['id'] ?>">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <?php foreach ($niveaux as $k => $v): ?>
                                        <?php if ($k === 'non_evalue') continue; ?>
                                        <th class="text-center"><?= CompetenceService::niveauDot($k) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eleves as $e): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($e['prenom'].' '.$e['nom']) ?></td>
                                        <?php foreach ($niveaux as $k => $v): ?>
                                            <?php if ($k === 'non_evalue') continue; ?>
                                            <td class="text-center">
                                                <input type="radio" name="niveaux[<?= $e['id'] ?>]" value="<?= $k ?>">
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php elseif (empty($eleves)): ?>
        <div class="empty-state"><p>Aucun élève dans cette classe.</p></div>
    <?php else: ?>
        <div class="empty-state"><p>Aucune sous-compétence pour ce domaine.</p></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
