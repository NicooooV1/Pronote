<?php
/**
 * M27 – Épreuve — Gestion (convocations, surveillants, notes)
 */
$pageTitle = 'Gestion épreuve';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$epreuve = $examenService->getEpreuve($id);
if (!$epreuve) { header('Location: examens.php'); exit; }

$examen = $examenService->getExamen($epreuve['examen_id']);
$convocations = $examenService->getConvocations($id);
$surveillants = $examenService->getSurveillants($id);
$isGestionnaire = isAdmin() || isPersonnelVS();
$classes = $examenService->getClasses();
$profs = $examenService->getProfesseurs();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire) {
    $action = $_POST['action'] ?? '';
    if ($action === 'convoquer_classe') {
        $count = $examenService->convoquerClasse($id, (int)$_POST['classe_id']);
        $_SESSION['success_message'] = "$count élève(s) convoqué(s).";
    } elseif ($action === 'ajouter_surveillant') {
        $examenService->ajouterSurveillant($id, (int)$_POST['professeur_id'], $_POST['role'] ?? 'surveillant');
    } elseif ($action === 'saisir_notes') {
        foreach ($_POST['convocation'] as $cId => $data) {
            $present = isset($data['present']) ? true : (isset($data['absent']) ? false : null);
            $note = $data['note'] !== '' ? (float)$data['note'] : null;
            $examenService->saisirPresenceNote((int)$cId, $present, $note);
        }
        $_SESSION['success_message'] = 'Notes enregistrées.';
    }
    header('Location: epreuve.php?id=' . $id);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-file-alt"></i> <?= htmlspecialchars($epreuve['intitule']) ?></h1>
        <a href="detail.php?id=<?= $examen['id'] ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> <?= htmlspecialchars($examen['nom']) ?></a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?><div class="alert alert-success"><?= $_SESSION['success_message'] ?></div><?php unset($_SESSION['success_message']); endif; ?>

    <div class="info-grid">
        <div class="info-item"><i class="fas fa-calendar"></i><span><?= formatDateTime($epreuve['date_epreuve']) ?></span></div>
        <div class="info-item"><i class="fas fa-clock"></i><span><?= $epreuve['duree_minutes'] ?> min</span></div>
        <?php if ($epreuve['salle_nom']): ?><div class="info-item"><i class="fas fa-door-open"></i><span><?= htmlspecialchars($epreuve['salle_nom']) ?></span></div><?php endif; ?>
        <?php if ($epreuve['matiere_nom']): ?><div class="info-item"><i class="fas fa-book"></i><span><?= htmlspecialchars($epreuve['matiere_nom']) ?></span></div><?php endif; ?>
    </div>

    <!-- Surveillants -->
    <div class="card">
        <div class="card-header"><h2>Surveillants (<?= count($surveillants) ?>)</h2></div>
        <div class="card-body">
            <?php foreach ($surveillants as $s): ?>
            <div class="member-item"><strong><?= htmlspecialchars($s['prof_nom']) ?></strong> <span class="badge badge-info"><?= $s['role'] ?></span></div>
            <?php endforeach; ?>
            <?php if ($isGestionnaire): ?>
            <form method="post" class="form-inline" style="margin-top:.5rem;">
                <?= csrfField() ?><input type="hidden" name="action" value="ajouter_surveillant">
                <select name="professeur_id" class="form-control" required><option value="">— Prof —</option><?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?></select>
                <select name="role" class="form-control"><option value="surveillant">Surveillant</option><option value="responsable">Responsable</option></select>
                <button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i></button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Convocations -->
    <div class="card">
        <div class="card-header"><h2>Candidats (<?= count($convocations) ?>)</h2></div>
        <div class="card-body">
            <?php if ($isGestionnaire): ?>
            <form method="post" class="form-inline" style="margin-bottom:1rem;">
                <?= csrfField() ?><input type="hidden" name="action" value="convoquer_classe">
                <select name="classe_id" class="form-control" required><option value="">— Classe —</option><?php foreach ($classes as $cl): ?><option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nom']) ?></option><?php endforeach; ?></select>
                <button class="btn btn-primary"><i class="fas fa-user-plus"></i> Convoquer</button>
            </form>

            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="saisir_notes">
            <?php endif; ?>

            <?php if (empty($convocations)): ?><p class="text-muted">Aucun candidat.</p>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Place</th><th>Élève</th><th>Classe</th><th>Présent</th><th>Note</th></tr></thead>
                <tbody>
                    <?php foreach ($convocations as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['place'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['prenom'] . ' ' . $c['eleve_nom']) ?></td>
                        <td><?= htmlspecialchars($c['classe_nom'] ?? '') ?></td>
                        <td>
                            <?php if ($isGestionnaire): ?>
                            <input type="checkbox" name="convocation[<?= $c['id'] ?>][present]" value="1" <?= $c['present'] ? 'checked' : '' ?>>
                            <?php else: echo $c['present'] === null ? '-' : ($c['present'] ? '✓' : '✗'); endif; ?>
                        </td>
                        <td>
                            <?php if ($isGestionnaire): ?>
                            <input type="number" name="convocation[<?= $c['id'] ?>][note]" class="form-control form-control-sm" style="width:80px;" step="0.25" min="0" max="20" value="<?= $c['note'] ?? '' ?>">
                            <?php else: echo $c['note'] !== null ? $c['note'] : '-'; endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if ($isGestionnaire && !empty($convocations)): ?>
                <button type="submit" class="btn btn-primary" style="margin-top:.5rem;"><i class="fas fa-save"></i> Enregistrer</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
