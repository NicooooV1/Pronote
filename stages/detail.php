<?php
/**
 * M17 – Détail stage
 */
$pageTitle = 'Détail stage';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stage = $stageService->getStage($id);
if (!$stage) { header('Location: stages.php'); exit; }

$types = StageService::typesStage();
$statuts = StageService::statutsStage();
$isGestionnaire = isAdmin() || isPersonnelVS();
$canEdit = $isGestionnaire || (isProfesseur() && $stage['prof_referent_id'] == getUserId());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $canEdit) {
    $stageService->modifierStage($id, [
        'type' => $_POST['type'], 'entreprise_nom' => trim($_POST['entreprise_nom']),
        'entreprise_adresse' => trim($_POST['entreprise_adresse'] ?? ''),
        'entreprise_tel' => trim($_POST['entreprise_tel'] ?? ''),
        'tuteur_nom' => trim($_POST['tuteur_nom'] ?? ''),
        'tuteur_email' => trim($_POST['tuteur_email'] ?? ''),
        'prof_referent_id' => $_POST['prof_referent_id'] ?: null,
        'date_debut' => $_POST['date_debut'], 'date_fin' => $_POST['date_fin'],
        'statut' => $_POST['statut'], 'description' => trim($_POST['description'] ?? ''),
        'evaluation_entreprise' => $_POST['evaluation_entreprise'] ?? null,
        'evaluation_prof' => $_POST['evaluation_prof'] ?? null,
        'rapport_path' => $stage['rapport_path'],
    ]);
    $_SESSION['success_message'] = 'Stage mis à jour.';
    header('Location: detail.php?id=' . $id); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-briefcase"></i> <?= htmlspecialchars($stage['entreprise_nom']) ?></h1>
        <a href="stages.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?><div class="alert alert-success"><?= $_SESSION['success_message'] ?></div><?php unset($_SESSION['success_message']); endif; ?>

    <div class="info-grid">
        <div class="info-item"><i class="fas fa-user-graduate"></i><span><?= htmlspecialchars($stage['eleve_nom']) ?></span></div>
        <div class="info-item"><span class="badge badge-secondary"><?= $types[$stage['type']] ?? $stage['type'] ?></span></div>
        <div class="info-item"><?= StageService::badgeStatut($stage['statut']) ?></div>
        <div class="info-item"><i class="fas fa-calendar"></i><span><?= formatDate($stage['date_debut']) ?> → <?= formatDate($stage['date_fin']) ?></span></div>
        <?php if ($stage['tuteur_nom']): ?><div class="info-item"><i class="fas fa-user"></i><span>Tuteur : <?= htmlspecialchars($stage['tuteur_nom']) ?></span></div><?php endif; ?>
        <?php if ($stage['prof_nom']): ?><div class="info-item"><i class="fas fa-user-tie"></i><span>Réf. : <?= htmlspecialchars($stage['prof_nom']) ?></span></div><?php endif; ?>
    </div>

    <?php if ($canEdit): ?>
    <div class="card">
        <div class="card-header"><h2>Modifier</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <?php $profs = $stageService->getProfesseurs(); ?>
                <div class="form-grid-3">
                    <div class="form-group"><label>Type</label><select name="type" class="form-control"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $stage['type'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Statut</label><select name="statut" class="form-control"><?php foreach ($statuts as $k => $v): ?><option value="<?= $k ?>" <?= $stage['statut'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Entreprise *</label><input type="text" name="entreprise_nom" class="form-control" value="<?= htmlspecialchars($stage['entreprise_nom']) ?>" required></div>
                    <div class="form-group"><label>Adresse</label><input type="text" name="entreprise_adresse" class="form-control" value="<?= htmlspecialchars($stage['entreprise_adresse'] ?? '') ?>"></div>
                    <div class="form-group"><label>Tél.</label><input type="text" name="entreprise_tel" class="form-control" value="<?= htmlspecialchars($stage['entreprise_tel'] ?? '') ?>"></div>
                    <div class="form-group"><label>Tuteur</label><input type="text" name="tuteur_nom" class="form-control" value="<?= htmlspecialchars($stage['tuteur_nom'] ?? '') ?>"></div>
                    <div class="form-group"><label>Email tuteur</label><input type="email" name="tuteur_email" class="form-control" value="<?= htmlspecialchars($stage['tuteur_email'] ?? '') ?>"></div>
                    <div class="form-group"><label>Référent</label><select name="prof_referent_id" class="form-control"><option value="">—</option><?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>" <?= $stage['prof_referent_id'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Début</label><input type="date" name="date_debut" class="form-control" value="<?= $stage['date_debut'] ?>"></div>
                    <div class="form-group"><label>Fin</label><input type="date" name="date_fin" class="form-control" value="<?= $stage['date_fin'] ?>"></div>
                    <div class="form-group full-width"><label>Description</label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($stage['description'] ?? '') ?></textarea></div>
                    <div class="form-group full-width"><label>Évaluation entreprise</label><textarea name="evaluation_entreprise" class="form-control" rows="2"><?= htmlspecialchars($stage['evaluation_entreprise'] ?? '') ?></textarea></div>
                    <div class="form-group full-width"><label>Évaluation prof</label><textarea name="evaluation_prof" class="form-control" rows="2"><?= htmlspecialchars($stage['evaluation_prof'] ?? '') ?></textarea></div>
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
