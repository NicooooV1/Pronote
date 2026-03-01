<?php
/**
 * M37 – Détail plan + suivis
 */
$pageTitle = 'Détail plan';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$plan = $besoinService->getPlan($id);
if (!$plan) { header('Location: besoins.php'); exit; }

// Accès : admin/VS = tout, prof responsable, parent enfant, élève soi-même
$canView = isAdmin() || isPersonnelVS();
if (isProfesseur() && $plan['responsable_id'] == getUserId()) $canView = true;
if (isEleve() && $plan['eleve_id'] == getUserId()) $canView = true;
if (isParent()) {
    $enfants = $besoinService->getPlansParent(getUserId());
    foreach ($enfants as $e) { if ($e['id'] == $id) { $canView = true; break; } }
}
if (!$canView) { redirect('/besoins/besoins.php'); }

$suivis = $besoinService->getSuivis($id);
$typesPlan = BesoinService::typesPlan();
$canEdit = isAdmin() || isPersonnelVS() || (isProfesseur() && $plan['responsable_id'] == getUserId());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $canEdit) {
    $action = $_POST['action'] ?? '';
    if ($action === 'ajouter_suivi') {
        $besoinService->ajouterSuivi($id, getUserId(), trim($_POST['observations']), $_POST['progres']);
        $_SESSION['success_message'] = 'Suivi ajouté.';
    }
    header('Location: detail.php?id=' . $id);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-hands-helping"></i> <?= htmlspecialchars($plan['eleve_nom']) ?></h1>
        <a href="besoins.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?><div class="alert alert-success"><?= $_SESSION['success_message'] ?></div><?php unset($_SESSION['success_message']); endif; ?>

    <div class="info-grid">
        <div class="info-item"><?= BesoinService::badgeType($plan['type']) ?> <?= $typesPlan[$plan['type']] ?? '' ?></div>
        <div class="info-item"><i class="fas fa-users"></i><span><?= htmlspecialchars($plan['classe_nom'] ?? '-') ?></span></div>
        <div class="info-item"><i class="fas fa-calendar"></i><span><?= formatDate($plan['date_debut']) ?><?= $plan['date_fin'] ? ' → ' . formatDate($plan['date_fin']) : '' ?></span></div>
        <div class="info-item"><span class="badge badge-<?= $plan['statut'] === 'actif' ? 'success' : ($plan['statut'] === 'suspendu' ? 'warning' : 'secondary') ?>"><?= ucfirst($plan['statut']) ?></span></div>
        <?php if ($plan['responsable_nom']): ?><div class="info-item"><i class="fas fa-user-tie"></i><span>Réf. : <?= htmlspecialchars($plan['responsable_nom']) ?></span></div><?php endif; ?>
    </div>

    <?php if ($plan['amenagements']): ?>
    <div class="card">
        <div class="card-header"><h2>Aménagements</h2></div>
        <div class="card-body"><p><?= nl2br(htmlspecialchars($plan['amenagements'])) ?></p></div>
    </div>
    <?php endif; ?>

    <!-- Suivis -->
    <div class="card">
        <div class="card-header"><h2>Suivis (<?= count($suivis) ?>)</h2></div>
        <div class="card-body">
            <?php if (empty($suivis)): ?><p class="text-muted">Aucun suivi enregistré.</p><?php endif; ?>
            <div class="suivis-timeline">
                <?php foreach ($suivis as $s): ?>
                <div class="suivi-item">
                    <div class="suivi-head">
                        <strong><?= htmlspecialchars($s['auteur_nom']) ?></strong>
                        <?= BesoinService::badgeProgres($s['progres']) ?>
                        <span class="suivi-date"><?= formatDateTime($s['created_at']) ?></span>
                    </div>
                    <p><?= nl2br(htmlspecialchars($s['observations'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($canEdit): ?>
            <hr>
            <h3>Ajouter un suivi</h3>
            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="ajouter_suivi">
                <div class="form-group"><label>Observations *</label><textarea name="observations" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>Progrès</label>
                    <select name="progres" class="form-control">
                        <?php foreach (BesoinService::niveauxProgres() as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter suivi</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
