<?php
$activePage = 'projets';
require_once __DIR__ . '/includes/header.php';

$user = $_SESSION['user'];
$role  = $user['type'] ?? 'eleve';
$id    = (int) ($_GET['id'] ?? 0);
$projet = $projetService->getProjet($id);
if (!$projet) { echo '<div class="container mt-4"><div class="alert alert-danger">Projet introuvable.</div></div>'; require_once __DIR__ . '/includes/footer.php'; exit; }

$participants = $projetService->getParticipants($id);
$etapes       = $projetService->getEtapes($id);
$types        = ProjetPedagogiqueService::typesLabels();
$isResponsable = ($role === 'admin' || (int)($user['id'] ?? 0) === (int)$projet['responsable_id']);

/* Actions POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isResponsable) {
    $action = $_POST['action'] ?? '';
    if ($action === 'statut') {
        $projetService->changerStatut($id, $_POST['statut']);
        header("Location: detail.php?id=$id"); exit;
    }
    if ($action === 'ajouter_etape') {
        $projetService->ajouterEtape($id, ['titre' => $_POST['titre_etape'], 'description' => $_POST['desc_etape'] ?? null, 'date_echeance' => $_POST['date_etape'] ?: null, 'ordre' => count($etapes) + 1]);
        header("Location: detail.php?id=$id"); exit;
    }
    if ($action === 'statut_etape') {
        $projetService->changerStatutEtape((int)$_POST['etape_id'], $_POST['etape_statut']);
        header("Location: detail.php?id=$id"); exit;
    }
    if ($action === 'ajouter_participant') {
        $projetService->ajouterParticipant($id, (int)$_POST['p_user_id'], $_POST['p_user_type'], $_POST['p_role'] ?? 'participant');
        header("Location: detail.php?id=$id"); exit;
    }
    if ($action === 'retirer_participant') {
        $projetService->retirerParticipant((int)$_POST['participant_id']);
        header("Location: detail.php?id=$id"); exit;
    }
    if ($action === 'bilan') {
        $projetService->modifierProjet($id, array_merge($projet, ['bilan' => $_POST['bilan']]));
        header("Location: detail.php?id=$id"); exit;
    }
}
$etapesActualisees = $projetService->getEtapes($id);
?>
<div class="container mt-4">
    <a href="projets.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1"></i>Retour</a>

    <div class="row g-4">
        <!-- Colonne principale -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?= htmlspecialchars($projet['titre']) ?></h4>
                    <?= ProjetPedagogiqueService::statutBadge($projet['statut']) ?>
                </div>
                <div class="card-body">
                    <span class="badge-type-<?= $projet['type'] ?> mb-2 d-inline-block"><?= $types[$projet['type']] ?? $projet['type'] ?></span>
                    <p class="mt-2"><?= nl2br(htmlspecialchars($projet['description'] ?? '')) ?></p>
                    <?php if ($projet['objectifs']): ?>
                        <h6>Objectifs</h6>
                        <p><?= nl2br(htmlspecialchars($projet['objectifs'])) ?></p>
                    <?php endif; ?>
                    <div class="row small text-muted">
                        <div class="col-md-4"><i class="fas fa-user me-1"></i>Responsable : <?= htmlspecialchars($projet['responsable_nom'] ?? '—') ?></div>
                        <div class="col-md-4"><i class="fas fa-calendar me-1"></i><?= date('d/m/Y', strtotime($projet['date_debut'])) ?><?php if ($projet['date_fin']): ?> → <?= date('d/m/Y', strtotime($projet['date_fin'])) ?><?php endif; ?></div>
                        <?php if ($projet['budget']): ?><div class="col-md-4"><i class="fas fa-euro-sign me-1"></i><?= number_format((float)$projet['budget'], 2, ',', ' ') ?> €</div><?php endif; ?>
                    </div>
                    <?php if ($projet['classes']): ?><p class="small mt-2"><strong>Classes :</strong> <?= htmlspecialchars($projet['classes']) ?></p><?php endif; ?>
                    <?php if ($projet['matieres']): ?><p class="small"><strong>Matières :</strong> <?= htmlspecialchars($projet['matieres']) ?></p><?php endif; ?>
                </div>
            </div>

            <!-- Étapes -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Étapes du projet</h5></div>
                <div class="card-body">
                    <?php if (empty($etapesActualisees)): ?>
                        <p class="text-muted">Aucune étape définie.</p>
                    <?php else: ?>
                        <div class="etapes-timeline">
                            <?php foreach ($etapesActualisees as $et): ?>
                                <div class="etape-item etape-<?= $et['statut'] ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= htmlspecialchars($et['titre']) ?></strong>
                                            <?php if ($et['date_echeance']): ?><span class="small text-muted ms-2"><?= date('d/m/Y', strtotime($et['date_echeance'])) ?></span><?php endif; ?>
                                            <?php if ($et['description']): ?><p class="small mb-0 mt-1"><?= htmlspecialchars($et['description']) ?></p><?php endif; ?>
                                        </div>
                                        <?php if ($isResponsable): ?>
                                            <form method="post" class="ms-2">
                                                <input type="hidden" name="action" value="statut_etape">
                                                <input type="hidden" name="etape_id" value="<?= $et['id'] ?>">
                                                <select name="etape_statut" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto">
                                                    <?php foreach (['a_faire' => 'À faire', 'en_cours' => 'En cours', 'termine' => 'Terminé'] as $k => $v): ?>
                                                        <option value="<?= $k ?>" <?= $et['statut'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isResponsable): ?>
                        <hr>
                        <form method="post" class="row g-2 align-items-end">
                            <input type="hidden" name="action" value="ajouter_etape">
                            <div class="col-md-4"><input name="titre_etape" class="form-control form-control-sm" placeholder="Titre de l'étape" required></div>
                            <div class="col-md-3"><input name="desc_etape" class="form-control form-control-sm" placeholder="Description"></div>
                            <div class="col-md-3"><input name="date_etape" type="date" class="form-control form-control-sm"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Ajouter</button></div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bilan -->
            <?php if ($projet['statut'] === 'termine' || $projet['bilan']): ?>
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Bilan</h5></div>
                <div class="card-body">
                    <?php if ($projet['bilan']): ?>
                        <p><?= nl2br(htmlspecialchars($projet['bilan'])) ?></p>
                    <?php endif; ?>
                    <?php if ($isResponsable): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="bilan">
                            <textarea name="bilan" class="form-control mb-2" rows="4"><?= htmlspecialchars($projet['bilan'] ?? '') ?></textarea>
                            <button class="btn btn-sm btn-success">Enregistrer le bilan</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar droite -->
        <div class="col-lg-4">
            <?php if ($isResponsable): ?>
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">Actions</h6></div>
                <div class="card-body">
                    <form method="post" class="mb-2">
                        <input type="hidden" name="action" value="statut">
                        <label class="form-label small">Changer le statut</label>
                        <div class="input-group input-group-sm">
                            <select name="statut" class="form-select">
                                <?php foreach (ProjetPedagogiqueService::statutLabels() as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $projet['statut'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-primary">OK</button>
                        </div>
                    </form>
                    <a href="creer.php?id=<?= $id ?>" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-edit me-1"></i>Modifier</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Participants -->
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-users me-1"></i>Participants (<?= count($participants) ?>)</h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($participants as $part): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <span>
                                <i class="fas fa-<?= $part['user_type'] === 'professeur' ? 'chalkboard-teacher' : 'user-graduate' ?> me-1 text-muted"></i>
                                <?= htmlspecialchars($part['nom_complet'] ?? 'ID#'.$part['user_id']) ?>
                                <span class="badge bg-light text-dark"><?= htmlspecialchars($part['role_projet']) ?></span>
                            </span>
                            <?php if ($isResponsable): ?>
                                <form method="post" class="d-inline"><input type="hidden" name="action" value="retirer_participant"><input type="hidden" name="participant_id" value="<?= $part['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="fas fa-times"></i></button></form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($participants)): ?><li class="list-group-item text-muted small">Aucun participant</li><?php endif; ?>
                </ul>
                <?php if ($isResponsable): ?>
                <div class="card-body border-top">
                    <form method="post" class="row g-1">
                        <input type="hidden" name="action" value="ajouter_participant">
                        <div class="col-5"><input name="p_user_id" type="number" class="form-control form-control-sm" placeholder="ID" required></div>
                        <div class="col-4">
                            <select name="p_user_type" class="form-select form-select-sm">
                                <option value="professeur">Prof</option>
                                <option value="eleve">Élève</option>
                            </select>
                        </div>
                        <div class="col-3"><button class="btn btn-sm btn-primary w-100">+</button></div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
