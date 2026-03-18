<?php
/**
 * details_absence.php — Détails d'une absence.
 * CSS externalisé dans absences.css, utilise AbsenceRepository + AbsenceHelper.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

$pdo = getPDO();
requireAuth();

$user      = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$repo = new AbsenceRepository($pdo);

// Récupérer l'ID
$id_absence = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_absence) {
    $_SESSION['error_message'] = "Identifiant d'absence invalide";
    header('Location: absences.php');
    exit;
}

// Vérifier l'accès
if (!$repo->canUserAccessAbsence($id_absence, $user_role, $user['id'])) {
    $_SESSION['error_message'] = "Vous n'avez pas accès à cette absence.";
    header('Location: absences.php');
    exit;
}

$absence = $repo->getById($id_absence);
if (!$absence) {
    $_SESSION['error_message'] = "Absence non trouvée";
    header('Location: absences.php');
    exit;
}

// Calcul de la durée
$duree = AbsenceHelper::formatDurationBetween($absence['date_debut'], $absence['date_fin']);

$pageTitle   = 'Détails de l\'absence';
$currentPage = 'details';
$showBackButton = true;
$backLink = 'absences.php';

include 'includes/header.php';
?>

<div class="content-container">
    <div class="content-header">
        <h2>Détails de l'absence #<?= $id_absence ?></h2>
        <div class="content-actions">
            <?php if (canManageAbsences()): ?>
            <a href="modifier_absence.php?id=<?= $id_absence ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Modifier
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-body">
        <!-- Statut justification -->
        <div class="alert <?= $absence['justifie'] ? 'alert-success' : 'alert-warning' ?>">
            <i class="fas <?= $absence['justifie'] ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
            <span>
                <?= $absence['justifie'] ? 'Absence justifiée' : 'Absence non justifiée' ?>
            </span>
        </div>

        <!-- Statut validation workflow -->
        <?php
        $statut = $absence['statut'] ?? 'signalee';
        $statutLabels = [
            'signalee'   => ['En attente de validation', 'warning', 'fa-clock'],
            'en_attente' => ['En attente de validation', 'warning', 'fa-clock'],
            'validee'    => ['Validée', 'success', 'fa-check-circle'],
            'refusee'    => ['Refusée', 'danger', 'fa-times-circle'],
        ];
        $sl = $statutLabels[$statut] ?? $statutLabels['signalee'];
        ?>
        <div class="alert alert-<?= $sl[1] ?>" style="margin-top: 0.5rem;">
            <i class="fas <?= $sl[2] ?>"></i>
            <span>
                Statut : <strong><?= $sl[0] ?></strong>
                <?php if (!empty($absence['validated_at'])): ?>
                    — le <?= date('d/m/Y à H:i', strtotime($absence['validated_at'])) ?>
                <?php endif; ?>
                <?php if (!empty($absence['validation_comment'])): ?>
                    <br><em><?= htmlspecialchars($absence['validation_comment']) ?></em>
                <?php endif; ?>
            </span>
        </div>

        <!-- Informations sur l'élève -->
        <div class="details-section">
            <h3>Informations sur l'élève</h3>
            <div class="details-grid">
                <div class="details-row">
                    <span class="details-label">Nom</span>
                    <span class="details-value"><?= htmlspecialchars($absence['nom']) ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Prénom</span>
                    <span class="details-value"><?= htmlspecialchars($absence['prenom']) ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Classe</span>
                    <span class="details-value"><?= htmlspecialchars($absence['classe']) ?></span>
                </div>
            </div>
        </div>

        <!-- Détails de l'absence -->
        <div class="details-section">
            <h3>Détails de l'absence</h3>
            <div class="details-grid">
                <div class="details-row">
                    <span class="details-label">Date de début</span>
                    <span class="details-value"><?= date('d/m/Y à H:i', strtotime($absence['date_debut'])) ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Date de fin</span>
                    <span class="details-value"><?= date('d/m/Y à H:i', strtotime($absence['date_fin'])) ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Durée</span>
                    <span class="details-value"><?= $duree ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Type</span>
                    <span class="details-value">
                        <span class="badge badge-<?= htmlspecialchars($absence['type_absence']) ?>">
                            <?= AbsenceHelper::typeLabel($absence['type_absence']) ?>
                        </span>
                    </span>
                </div>
                <div class="details-row">
                    <span class="details-label">Motif</span>
                    <span class="details-value"><?= AbsenceHelper::motifLabel($absence['motif'] ?? '') ?></span>
                </div>
                <?php if (!empty($absence['commentaire'])): ?>
                <div class="details-row details-full">
                    <span class="details-label">Commentaire</span>
                    <span class="details-value"><?= nl2br(htmlspecialchars($absence['commentaire'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($absence['signale_par'])): ?>
                <div class="details-row">
                    <span class="details-label">Signalé par</span>
                    <span class="details-value"><?= htmlspecialchars($absence['signale_par']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <a href="absences.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
            <?php if (canManageAbsences()): ?>
            <a href="modifier_absence.php?id=<?= $id_absence ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Modifier cette absence
            </a>
            <?php endif; ?>
            <?php if ((isAdmin() || isVieScolaire()) && in_array($statut, ['signalee', 'en_attente', ''])): ?>
            <form method="POST" action="valider_absence.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= AbsenceHelper::generateCsrf() ?>">
                <input type="hidden" name="absence_id" value="<?= $id_absence ?>">
                <button type="submit" name="action" value="valider" class="btn btn-success" 
                        onclick="return confirm('Valider cette absence ?')">
                    <i class="fas fa-check"></i> Valider
                </button>
                <button type="submit" name="action" value="refuser" class="btn btn-danger" 
                        onclick="return confirm('Refuser cette absence ?')">
                    <i class="fas fa-times"></i> Refuser
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
ob_end_flush();
