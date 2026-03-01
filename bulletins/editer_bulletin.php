<?php
/**
 * Bulletins — Édition d'un bulletin (appréciations, avis conseil)
 */
require_once __DIR__ . '/includes/BulletinService.php';
$currentPage = 'editer';
$pageTitle = 'Éditer le bulletin';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    header('Location: bulletins.php');
    exit;
}

$pdo = getPDO();
$service = new BulletinService($pdo);
$id = (int)($_GET['id'] ?? 0);
$bulletin = $service->getBulletin($id);

if (!$bulletin) {
    echo '<div class="alert alert-error">Bulletin introuvable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$lignes = $service->getLignesMatieres($id);
$avisLabels = BulletinService::avisLabels();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'appreciation_generale') {
            $service->sauvegarderAppreciation($id, $_POST['appreciation_generale'] ?? '');
            $message = 'Appréciation générale enregistrée.';
        } elseif ($action === 'avis_conseil') {
            $service->sauvegarderAvisConseil($id, $_POST['avis_conseil'] ?? 'aucun');
            $message = 'Avis du conseil enregistré.';
        } elseif ($action === 'appreciation_matiere') {
            $bmId = (int)$_POST['bm_id'];
            $service->sauvegarderAppreciationMatiere($bmId, $_POST['appreciation'] ?? '');
            $message = 'Appréciation matière enregistrée.';
        } elseif ($action === 'valider' && (isAdmin() || isVieScolaire())) {
            $service->validerBulletin($id, $user['id']);
            $message = 'Bulletin validé.';
        } elseif ($action === 'publier' && (isAdmin() || isVieScolaire())) {
            $service->publierBulletins($bulletin['classe_id'], $bulletin['periode_id']);
            $message = 'Bulletins de la classe publiés.';
        }
        // Refresh
        $bulletin = $service->getBulletin($id);
        $lignes = $service->getLignesMatieres($id);
    } catch (Exception $e) {
        $message = 'Erreur: ' . $e->getMessage();
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-edit"></i> Éditer — <?= htmlspecialchars($bulletin['eleve_prenom'] . ' ' . $bulletin['eleve_nom']) ?></h1>
    <div class="header-actions">
        <?= BulletinService::statutBadge($bulletin['statut']) ?>
        <a href="detail_bulletin.php?id=<?= $id ?>" class="btn btn-outline"><i class="fas fa-eye"></i> Voir</a>
        <a href="bulletins.php?classe=<?= $bulletin['classe_id'] ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="edit-grid">
    <!-- Appréciations matières -->
    <div class="card">
        <div class="card-header"><h3>Appréciations par matière</h3></div>
        <div class="card-body">
            <?php foreach ($lignes as $l): ?>
            <form method="POST" class="appreciation-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="appreciation_matiere">
                <input type="hidden" name="bm_id" value="<?= $l['id'] ?>">
                <div class="appreciation-row">
                    <div class="appreciation-matiere">
                        <span class="matiere-dot" style="background:<?= htmlspecialchars($l['couleur'] ?? '#3498db') ?>"></span>
                        <strong><?= htmlspecialchars($l['matiere_nom']) ?></strong>
                        <span class="text-muted">(<?= $l['moyenne_eleve'] !== null ? number_format($l['moyenne_eleve'], 2) : '-' ?>/20)</span>
                    </div>
                    <div class="appreciation-input">
                        <textarea name="appreciation" rows="2" placeholder="Appréciation du professeur..."><?= htmlspecialchars($l['appreciation'] ?? '') ?></textarea>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save"></i></button>
                    </div>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Appréciation générale + Avis -->
    <div class="card">
        <div class="card-header"><h3>Appréciation générale</h3></div>
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="appreciation_generale">
                <div class="form-group">
                    <textarea name="appreciation_generale" rows="4" class="form-control" placeholder="Appréciation du conseil de classe..."><?= htmlspecialchars($bulletin['appreciation_generale'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Avis du conseil de classe</h3></div>
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="avis_conseil">
                <div class="form-group">
                    <select name="avis_conseil" class="form-select">
                        <?php foreach ($avisLabels as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($bulletin['avis_conseil'] ?? 'aucun') === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </form>
        </div>
    </div>

    <!-- Actions de validation -->
    <?php if (isAdmin() || isVieScolaire()): ?>
    <div class="card">
        <div class="card-header"><h3>Validation</h3></div>
        <div class="card-body">
            <div class="validation-actions">
                <?php if ($bulletin['statut'] === 'brouillon'): ?>
                <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="valider">
                    <button type="submit" class="btn btn-info"><i class="fas fa-check"></i> Valider ce bulletin</button>
                </form>
                <?php endif; ?>
                <?php if ($bulletin['statut'] === 'valide'): ?>
                <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="publier">
                    <button type="submit" class="btn btn-success"><i class="fas fa-globe"></i> Publier tous les bulletins validés de la classe</button>
                </form>
                <?php endif; ?>
                <?php if ($bulletin['statut'] === 'publie'): ?>
                <p class="text-success"><i class="fas fa-check-circle"></i> Ce bulletin est publié et visible par l'élève et ses parents.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
