<?php
/**
 * valider_absence.php — Page de validation des absences
 * Workflow: signalée → en_attente → validée / refusée
 * Accessible uniquement par admin / vie_scolaire
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

requireAuth();

// Seuls admin/vie_scolaire peuvent valider
if (!isAdmin() && !isVieScolaire()) {
    $_SESSION['error_message'] = "Accès réservé à la vie scolaire.";
    header('Location: absences.php');
    exit;
}

$pdo  = getPDO();
$repo = new AbsenceRepository($pdo);
$user = getCurrentUser();

// --- Traitement POST (validation / refus) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AbsenceHelper::verifyCsrf()) {
        $_SESSION['error_message'] = "Erreur de sécurité. Veuillez réessayer.";
        header('Location: valider_absence.php');
        exit;
    }

    $action    = $_POST['action'] ?? '';
    $comment   = AbsenceHelper::sanitize($_POST['commentaire'] ?? '');
    $absenceId = filter_input(INPUT_POST, 'absence_id', FILTER_VALIDATE_INT);
    $bulkIds   = array_filter(array_map('intval', $_POST['selected_ids'] ?? []));

    if ($action === 'valider' && $absenceId) {
        if ($repo->validateAbsence($absenceId, $user['id'], $comment)) {
            $_SESSION['success_message'] = "Absence #$absenceId validée.";
        } else {
            $_SESSION['error_message'] = "Impossible de valider l'absence #$absenceId.";
        }
    } elseif ($action === 'refuser' && $absenceId) {
        if ($repo->rejectAbsence($absenceId, $user['id'], $comment)) {
            $_SESSION['success_message'] = "Absence #$absenceId refusée.";
        } else {
            $_SESSION['error_message'] = "Impossible de refuser l'absence #$absenceId.";
        }
    } elseif ($action === 'bulk_valider' && !empty($bulkIds)) {
        $count = $repo->bulkValidate($bulkIds, $user['id'], $comment);
        $_SESSION['success_message'] = "$count absence(s) validée(s).";
    } elseif ($action === 'bulk_refuser' && !empty($bulkIds)) {
        $count = $repo->bulkReject($bulkIds, $user['id'], $comment);
        $_SESSION['success_message'] = "$count absence(s) refusée(s).";
    }

    header('Location: valider_absence.php');
    exit;
}

// --- Filtres ---
$filters  = AbsenceHelper::getFilters();
$classe   = $filters['classe'];
$classes  = AbsenceHelper::getClassesList();
$pending  = $repo->getPendingValidation(['classe' => $classe]);
$counts   = $repo->countByStatus();
$csrf     = AbsenceHelper::generateCsrf();

// --- Config page ---
$pageTitle      = 'Validation des absences';
$currentPage    = 'validation';
$showBackButton = true;
$backLink       = 'absences.php';

include 'includes/header.php';
?>

<?php if (!empty($_SESSION['success_message'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?></div>
<?php unset($_SESSION['success_message']); endif; ?>
<?php if (!empty($_SESSION['error_message'])): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?></div>
<?php unset($_SESSION['error_message']); endif; ?>

<!-- Résumé des statuts -->
<div class="ds-stat-grid" style="margin-bottom: 1.5rem;">
    <div class="ds-stat-card">
        <div class="ds-stat-icon" style="color: var(--ds-warning)"><i class="fas fa-clock"></i></div>
        <div class="ds-stat-value"><?= ($counts['signalee'] ?? 0) + ($counts['en_attente'] ?? 0) ?></div>
        <div class="ds-stat-label">En attente</div>
    </div>
    <div class="ds-stat-card">
        <div class="ds-stat-icon" style="color: var(--ds-success)"><i class="fas fa-check"></i></div>
        <div class="ds-stat-value"><?= $counts['validee'] ?? 0 ?></div>
        <div class="ds-stat-label">Validées</div>
    </div>
    <div class="ds-stat-card">
        <div class="ds-stat-icon" style="color: var(--ds-danger)"><i class="fas fa-times"></i></div>
        <div class="ds-stat-value"><?= $counts['refusee'] ?? 0 ?></div>
        <div class="ds-stat-label">Refusées</div>
    </div>
</div>

<!-- Filtre classe -->
<div class="ds-card" style="margin-bottom: 1.5rem;">
    <form method="GET" class="filter-form" style="display:flex;gap:1rem;align-items:end;padding:1rem;">
        <div class="ds-form-group" style="margin:0;flex:1;">
            <label class="ds-form-label" for="classe">Classe</label>
            <select name="classe" id="classe" class="ds-form-control">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $classe === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="ds-btn ds-btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
        <a href="valider_absence.php" class="ds-btn ds-btn-outline">Réinitialiser</a>
    </form>
</div>

<?php if (empty($pending)): ?>
<div class="ds-empty">
    <i class="fas fa-check-circle"></i>
    <p>Aucune absence en attente de validation.</p>
</div>
<?php else: ?>

<!-- Actions en lot -->
<form method="POST" id="bulk-form">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="commentaire" id="bulk-comment" value="">

    <div style="display:flex;gap:0.5rem;margin-bottom:1rem;">
        <button type="submit" name="action" value="bulk_valider" class="ds-btn ds-btn-success ds-btn-sm" onclick="return confirmBulk('valider')">
            <i class="fas fa-check"></i> Valider la sélection
        </button>
        <button type="submit" name="action" value="bulk_refuser" class="ds-btn ds-btn-danger ds-btn-sm" onclick="return confirmBulk('refuser')">
            <i class="fas fa-times"></i> Refuser la sélection
        </button>
        <span id="selection-count" style="margin-left:auto;color:var(--ds-text-muted);font-size:0.85rem;align-self:center;">0 sélectionnée(s)</span>
    </div>

    <div class="ds-card">
        <table class="ds-table">
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="select-all" title="Tout sélectionner"></th>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Type</th>
                    <th>Motif</th>
                    <th>Justifié</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $abs): ?>
                <tr>
                    <td><input type="checkbox" name="selected_ids[]" value="<?= $abs['id'] ?>" class="select-checkbox"></td>
                    <td>
                        <a href="details_absence.php?id=<?= $abs['id'] ?>" class="link-primary">
                            <?= htmlspecialchars($abs['prenom'] . ' ' . $abs['nom']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($abs['classe']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($abs['date_debut'])) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($abs['date_fin'])) ?></td>
                    <td><span class="badge badge-type"><?= htmlspecialchars(AbsenceHelper::typeLabel($abs['type_absence'] ?? '')) ?></span></td>
                    <td><?= htmlspecialchars(AbsenceHelper::motifLabel($abs['motif'] ?? '')) ?></td>
                    <td>
                        <?php if (!empty($abs['justifie'])): ?>
                            <span class="badge-status badge-success">Oui</span>
                        <?php else: ?>
                            <span class="badge-status badge-danger">Non</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="ds-btn ds-btn-success ds-btn-sm ds-btn-icon" 
                                onclick="validateSingle(<?= $abs['id'] ?>, 'valider')" title="Valider">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="ds-btn ds-btn-danger ds-btn-sm ds-btn-icon" 
                                onclick="validateSingle(<?= $abs['id'] ?>, 'refuser')" title="Refuser">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- Modal validation individuelle -->
<div id="modal-validate" class="modal-overlay" style="display:none;">
    <div class="modal-content ds-card" style="max-width:450px;">
        <h3 id="modal-title">Confirmer</h3>
        <form method="POST" id="single-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="absence_id" id="modal-absence-id" value="">
            <input type="hidden" name="action" id="modal-action" value="">
            <div class="ds-form-group">
                <label class="ds-form-label" for="modal-comment">Commentaire (optionnel)</label>
                <textarea name="commentaire" id="modal-comment" class="ds-form-control" rows="3" placeholder="Motif du refus ou observation..."></textarea>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="ds-btn ds-btn-outline" onclick="closeModal()">Annuler</button>
                <button type="submit" id="modal-submit" class="ds-btn">Confirmer</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 1000;
    display: flex; align-items: center; justify-content: center;
}
.modal-content { padding: 1.5rem; }
.link-primary { color: var(--ds-primary); text-decoration: none; font-weight: 500; }
.link-primary:hover { text-decoration: underline; }
</style>

<script>
// Select all
document.getElementById('select-all').addEventListener('change', function() {
    document.querySelectorAll('.select-checkbox').forEach(cb => cb.checked = this.checked);
    updateCount();
});
document.querySelectorAll('.select-checkbox').forEach(cb => cb.addEventListener('change', updateCount));

function updateCount() {
    const n = document.querySelectorAll('.select-checkbox:checked').length;
    document.getElementById('selection-count').textContent = n + ' sélectionnée(s)';
}

function validateSingle(id, action) {
    document.getElementById('modal-absence-id').value = id;
    document.getElementById('modal-action').value = action;
    document.getElementById('modal-title').textContent = action === 'valider' ? 'Valider l\'absence #' + id : 'Refuser l\'absence #' + id;
    const btn = document.getElementById('modal-submit');
    btn.className = action === 'valider' ? 'ds-btn ds-btn-success' : 'ds-btn ds-btn-danger';
    btn.textContent = action === 'valider' ? 'Valider' : 'Refuser';
    document.getElementById('modal-validate').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modal-validate').style.display = 'none';
}

function confirmBulk(action) {
    const checked = document.querySelectorAll('.select-checkbox:checked');
    if (checked.length === 0) { alert('Veuillez sélectionner au moins une absence.'); return false; }
    const comment = prompt('Commentaire (optionnel) :', '');
    if (comment === null) return false;
    document.getElementById('bulk-comment').value = comment;
    return true;
}

// Fermer modal au clic extérieur
document.getElementById('modal-validate')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
