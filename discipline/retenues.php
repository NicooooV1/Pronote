<?php
/**
 * M06 – Discipline : Gestion des retenues
 */

require_once __DIR__ . '/includes/DisciplineService.php';

$pageTitle = 'Retenues';
$currentPage = 'retenues';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire()) {
    echo '<div class="alert alert-danger">Accès non autorisé.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new DisciplineService($pdo);
$user = getCurrentUser();

$success = '';
$error = '';

// ─── Créer une retenue ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer_retenue') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée.';
    } else {
        try {
            $sigType = isAdmin() ? 'administrateur' : 'vie_scolaire';
            $retenueId = $service->createRetenue([
                'date_retenue'    => $_POST['date_retenue'],
                'heure_debut'     => $_POST['heure_debut'],
                'heure_fin'       => $_POST['heure_fin'],
                'lieu'            => $_POST['lieu'] ?? null,
                'surveillant_id'  => $user['id'],
                'surveillant_type'=> $sigType,
                'capacite_max'    => (int)($_POST['capacite_max'] ?? 30),
                'commentaire'     => $_POST['commentaire'] ?? null,
            ]);
            $success = "Retenue #$retenueId créée. Vous pouvez maintenant y affecter des élèves.";
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }
}

// ─── Affecter un élève à une retenue ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'affecter_eleve') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée.';
    } else {
        try {
            $service->affecterEleveRetenue(
                (int)$_POST['retenue_id'],
                (int)$_POST['eleve_id'],
                (int)($_POST['sanction_id'] ?? 0) ?: null
            );
            $success = "Élève affecté à la retenue.";
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }
}

$retenues = $service->getRetenues();
?>

<h1 class="page-title"><i class="fas fa-user-clock"></i> Retenues</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Formulaire de création -->
<div class="card form-card">
    <h2><i class="fas fa-plus"></i> Planifier une retenue</h2>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="creer_retenue">

        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="date_retenue">Date *</label>
                <input type="date" name="date_retenue" id="date_retenue" class="form-control" required>
            </div>
            <div class="form-group col-md-2">
                <label for="heure_debut">Début *</label>
                <input type="time" name="heure_debut" id="heure_debut" class="form-control" value="16:00" required>
            </div>
            <div class="form-group col-md-2">
                <label for="heure_fin">Fin *</label>
                <input type="time" name="heure_fin" id="heure_fin" class="form-control" value="18:00" required>
            </div>
            <div class="form-group col-md-3">
                <label for="lieu">Lieu</label>
                <input type="text" name="lieu" id="lieu" class="form-control" placeholder="Salle de retenue">
            </div>
            <div class="form-group col-md-2">
                <label for="capacite_max">Capacité</label>
                <input type="number" name="capacite_max" id="capacite_max" class="form-control" value="30" min="1">
            </div>
        </div>

        <div class="form-group">
            <label for="commentaire">Commentaire</label>
            <input type="text" name="commentaire" id="commentaire" class="form-control" placeholder="Informations supplémentaires">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Créer la retenue</button>
        </div>
    </form>
</div>

<!-- Liste des retenues -->
<?php if (empty($retenues)): ?>
    <div class="empty-state">
        <i class="fas fa-calendar-check"></i>
        <p>Aucune retenue planifiée.</p>
    </div>
<?php else: ?>
<div class="retenues-grid">
    <?php foreach ($retenues as $ret): ?>
    <div class="card retenue-card <?= strtotime($ret['date_retenue']) < time() ? 'retenue-passee' : '' ?>">
        <div class="retenue-header">
            <div class="retenue-date">
                <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($ret['date_retenue'])) ?>
            </div>
            <div class="retenue-horaire">
                <?= substr($ret['heure_debut'], 0, 5) ?> – <?= substr($ret['heure_fin'], 0, 5) ?>
            </div>
        </div>
        <div class="retenue-body">
            <?php if ($ret['lieu']): ?>
            <div class="retenue-info"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ret['lieu']) ?></div>
            <?php endif; ?>
            <div class="retenue-info"><i class="fas fa-users"></i> <?= $ret['nb_eleves'] ?> / <?= $ret['capacite_max'] ?> élèves</div>
            <?php if ($ret['commentaire']): ?>
            <div class="retenue-info"><i class="fas fa-comment"></i> <?= htmlspecialchars($ret['commentaire']) ?></div>
            <?php endif; ?>
        </div>
        <div class="retenue-footer">
            <a href="detail_retenue.php?id=<?= $ret['id'] ?>" class="btn btn-sm btn-outline">
                <i class="fas fa-eye"></i> Détails
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
