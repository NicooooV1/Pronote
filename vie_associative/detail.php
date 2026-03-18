<?php
$activePage = 'associations';
require_once __DIR__ . '/includes/header.php';

$user = $_SESSION['user'];
$role  = $user['type'] ?? 'eleve';
$id   = (int) ($_GET['id'] ?? 0);
$asso = $vieAssoService->getAssociation($id);
if (!$asso) { echo '<div class="container mt-4"><div class="alert alert-danger">Association introuvable.</div></div>'; require_once __DIR__.'/includes/footer.php'; exit; }

$membres   = $vieAssoService->getMembres($id);
$activites = $vieAssoService->getActivites($id);
$tresorerie = $vieAssoService->getTresorerie($id);
$solde     = $vieAssoService->getSolde($id);
$isAdmin   = ($role === 'admin');
$isRef     = ($role === 'professeur' && (int)($user['id'] ?? 0) === (int)$asso['referent_adulte_id']);

/* POST actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($isAdmin || $isRef)) {
    $act = $_POST['action'] ?? '';
    if ($act === 'inscrire') {
        $vieAssoService->inscrireMembre($id, (int)$_POST['eleve_id'], $_POST['role_membre'] ?? 'membre');
    } elseif ($act === 'retirer') {
        $vieAssoService->retirerMembre((int)$_POST['membre_id']);
    } elseif ($act === 'activite') {
        $vieAssoService->ajouterActivite($id, ['titre' => $_POST['act_titre'], 'description' => $_POST['act_desc'] ?? '', 'date_activite' => $_POST['act_date'], 'lieu' => $_POST['act_lieu'] ?? '', 'budget_prevu' => $_POST['act_budget'] ?: null]);
    } elseif ($act === 'operation') {
        $vieAssoService->ajouterOperation($id, ['type_operation' => $_POST['op_type'], 'montant' => (float)$_POST['op_montant'], 'description' => $_POST['op_desc'] ?? '', 'date_operation' => $_POST['op_date'] ?: date('Y-m-d')]);
    }
    header("Location: detail.php?id=$id"); exit;
}
$types = VieAssociativeService::typesLabels();
?>
<div class="container mt-4">
    <a href="associations.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1"></i>Retour</a>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center" style="border-top:4px solid <?= VieAssociativeService::typeColor($asso['type']) ?>">
            <h4 class="mb-0"><?= htmlspecialchars($asso['nom']) ?></h4>
            <span class="asso-type-badge" style="background:<?= VieAssociativeService::typeColor($asso['type']) ?>"><?= $types[$asso['type']] ?? $asso['type'] ?></span>
        </div>
        <div class="card-body">
            <p><?= nl2br(htmlspecialchars($asso['description'] ?? '')) ?></p>
            <div class="row small text-muted">
                <div class="col-md-4"><i class="fas fa-crown text-warning me-1"></i>Président : <?= htmlspecialchars($asso['president_nom'] ?? '—') ?></div>
                <div class="col-md-4"><i class="fas fa-chalkboard-teacher me-1"></i>Référent : <?= htmlspecialchars($asso['referent_nom'] ?? '—') ?></div>
                <?php if ($asso['budget_annuel']): ?><div class="col-md-4"><i class="fas fa-euro-sign me-1"></i>Budget : <?= number_format((float)$asso['budget_annuel'], 2, ',', ' ') ?> €</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Membres -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-users me-2"></i>Membres (<?= count($membres) ?>)</h5></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($membres as $m): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small <?= $m['statut'] !== 'actif' ? 'text-muted' : '' ?>">
                            <span>
                                <?= htmlspecialchars($m['nom_complet'] ?? '#'.$m['eleve_id']) ?>
                                <span class="badge bg-light text-dark"><?= htmlspecialchars($m['role']) ?></span>
                                <?php if ($m['classe']): ?><span class="text-muted">(<?= htmlspecialchars($m['classe']) ?>)</span><?php endif; ?>
                            </span>
                            <?php if (($isAdmin || $isRef) && $m['statut'] === 'actif'): ?>
                                <form method="post" class="d-inline"><input type="hidden" name="action" value="retirer"><input type="hidden" name="membre_id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" title="Retirer"><i class="fas fa-times"></i></button></form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($membres)): ?><li class="list-group-item text-muted small">Aucun membre</li><?php endif; ?>
                </ul>
                <?php if ($isAdmin || $isRef): ?>
                <div class="card-body border-top">
                    <form method="post" class="row g-1">
                        <input type="hidden" name="action" value="inscrire">
                        <div class="col-5"><input name="eleve_id" type="number" class="form-control form-control-sm" placeholder="ID élève" required></div>
                        <div class="col-4"><select name="role_membre" class="form-select form-select-sm"><option value="membre">Membre</option><option value="bureau">Bureau</option><option value="president">Président</option></select></div>
                        <div class="col-3"><button class="btn btn-sm btn-primary w-100">+</button></div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activités -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Activités</h5></div>
                <div class="card-body" style="max-height:300px;overflow-y:auto">
                    <?php if (empty($activites)): ?>
                        <p class="text-muted small">Aucune activité planifiée.</p>
                    <?php endif; ?>
                    <?php foreach ($activites as $act): ?>
                        <div class="mb-2 pb-2 border-bottom small">
                            <strong><?= htmlspecialchars($act['titre']) ?></strong>
                            <span class="text-muted ms-1"><?= date('d/m/Y', strtotime($act['date_activite'])) ?></span>
                            <?php if ($act['lieu']): ?><br><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($act['lieu']) ?><?php endif; ?>
                            <?php if ($act['description']): ?><br><span class="text-muted"><?= htmlspecialchars($act['description']) ?></span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($isAdmin || $isRef): ?>
                <div class="card-body border-top">
                    <form method="post" class="row g-1">
                        <input type="hidden" name="action" value="activite">
                        <div class="col-md-5"><input name="act_titre" class="form-control form-control-sm" placeholder="Titre" required></div>
                        <div class="col-md-3"><input name="act_date" type="date" class="form-control form-control-sm" required></div>
                        <div class="col-md-2"><input name="act_lieu" class="form-control form-control-sm" placeholder="Lieu"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">+</button></div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Trésorerie -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Trésorerie</h5>
            <span class="badge bg-<?= $solde >= 0 ? 'success' : 'danger' ?> fs-6">Solde : <?= number_format($solde, 2, ',', ' ') ?> €</span>
        </div>
        <div class="card-body">
            <?php if (empty($tresorerie)): ?>
                <p class="text-muted small">Aucune opération enregistrée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Description</th><th class="text-end">Montant</th></tr></thead>
                        <tbody>
                        <?php foreach ($tresorerie as $op): ?>
                            <tr>
                                <td class="small"><?= date('d/m/Y', strtotime($op['date_operation'])) ?></td>
                                <td><span class="badge bg-<?= $op['type_operation'] === 'recette' ? 'success' : 'danger' ?>"><?= ucfirst($op['type_operation']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($op['description'] ?? '') ?></td>
                                <td class="text-end fw-bold <?= $op['type_operation'] === 'recette' ? 'text-success' : 'text-danger' ?>">
                                    <?= $op['type_operation'] === 'recette' ? '+' : '-' ?><?= number_format((float)$op['montant'], 2, ',', ' ') ?> €
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($isAdmin || $isRef): ?>
            <hr>
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="operation">
                <div class="col-md-2"><select name="op_type" class="form-select form-select-sm"><option value="recette">Recette</option><option value="depense">Dépense</option></select></div>
                <div class="col-md-2"><input name="op_montant" type="number" step="0.01" class="form-control form-control-sm" placeholder="Montant" required></div>
                <div class="col-md-4"><input name="op_desc" class="form-control form-control-sm" placeholder="Description"></div>
                <div class="col-md-2"><input name="op_date" type="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Ajouter</button></div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
