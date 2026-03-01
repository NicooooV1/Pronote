<?php
/**
 * M31 – Infirmerie — Nouveau passage
 */
$pageTitle = 'Nouveau passage';
$activePage = 'passage';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/infirmerie/infirmerie.php'); }

$orientations = InfirmerieService::orientations();
$eleves = $infirmerieService->getEleves();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $data = [
        'eleve_id' => (int)$_POST['eleve_id'],
        'date_passage' => $_POST['date_passage'] ?: date('Y-m-d H:i:s'),
        'motif' => trim($_POST['motif'] ?? ''),
        'symptomes' => trim($_POST['symptomes'] ?? ''),
        'soins' => trim($_POST['soins'] ?? ''),
        'orientation' => $_POST['orientation'] ?? 'retour_classe',
        'notifier_parents' => isset($_POST['notifier_parents']) ? 1 : 0,
        'remarques' => trim($_POST['remarques'] ?? ''),
    ];
    if (empty($data['eleve_id']) || empty($data['motif'])) {
        $error = 'L\'élève et le motif sont obligatoires.';
    } else {
        $id = $infirmerieService->creerPassage($data);
        $_SESSION['success_message'] = 'Passage enregistré.';
        header('Location: infirmerie.php');
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Nouveau passage</h1>
        <a href="infirmerie.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Élève *</label>
                        <select name="eleve_id" class="form-control" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($eleves as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?> (<?= htmlspecialchars($e['classe_nom'] ?? 'N/A') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date & heure</label>
                        <input type="datetime-local" name="date_passage" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Motif *</label>
                        <input type="text" name="motif" class="form-control" required placeholder="ex: Maux de tête, chute en récréation…">
                    </div>
                    <div class="form-group full-width">
                        <label>Symptômes</label>
                        <textarea name="symptomes" class="form-control" rows="2" placeholder="Description détaillée des symptômes"></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Soins prodigués</label>
                        <textarea name="soins" class="form-control" rows="2" placeholder="Glace, pansement, repos…"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Orientation</label>
                        <select name="orientation" class="form-control">
                            <?php foreach ($orientations as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:center; gap:.5rem; padding-top:1.5rem;">
                        <input type="checkbox" name="notifier_parents" id="notif_parents" value="1">
                        <label for="notif_parents" style="margin:0;">Notifier les parents</label>
                    </div>
                    <div class="form-group full-width">
                        <label>Remarques</label>
                        <textarea name="remarques" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    <a href="infirmerie.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
