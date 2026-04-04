<?php
/**
 * details_justificatif.php — Détails d'un justificatif
 * Refactorisé : AbsenceRepository + AbsenceHelper, support pièces jointes,
 * suppression inline SQL et error_log, suppression functions.php.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

if (!isLoggedIn()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

$pdo  = getPDO();
$repo = new AbsenceRepository($pdo);

$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();

// --- Validation de l'ID ---
$id_justificatif = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_justificatif) {
    $_SESSION['error_message'] = "Identifiant de justificatif invalide";
    header('Location: justificatifs.php');
    exit;
}

// --- Récupération via Repository ---
$justificatif = $repo->getJustificatifById($id_justificatif);
if (!$justificatif) {
    $_SESSION['error_message'] = "Justificatif non trouvé";
    header('Location: justificatifs.php');
    exit;
}

// Authorization: parents/eleves can only view their own justificatifs
if (!canManageAbsences()) {
    $role = getUserRole();
    $allowed = false;
    if ($role === 'eleve' && (int)($justificatif['id_eleve'] ?? 0) === (int)$user['id']) {
        $allowed = true;
    } elseif ($role === 'parent') {
        $children = $repo->getChildrenForParent((int)$user['id']);
        $childIds = array_column($children, 'id');
        if (in_array((int)($justificatif['id_eleve'] ?? 0), $childIds, true)) {
            $allowed = true;
        }
    }
    if (!$allowed) {
        $_SESSION['error_message'] = "Accès non autorisé";
        header('Location: ../index.php');
        exit;
    }
}

// --- Absence associée ---
$absence = null;
if (!empty($justificatif['id_absence'])) {
    $absence = $repo->getById((int)$justificatif['id_absence']);
}

// --- Pièces jointes ---
$attachments = $repo->getAttachments($id_justificatif);

// --- Config page ---
$pageTitle      = 'Détails du justificatif';
$currentPage    = 'justificatifs';
$showBackButton = true;
$backLink       = 'justificatifs.php';

// Messages flash
$success_msg = $_SESSION['success_message'] ?? '';
$error_msg   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

include 'includes/header.php';
?>

<?php if ($success_msg): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($success_msg) ?></span></div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error_msg) ?></span></div>
<?php endif; ?>

<div class="content-container">
    <div class="content-header">
        <h2>Justificatif #<?= $id_justificatif ?></h2>
        <div class="content-actions">
            <?php if (!$justificatif['traite']): ?>
            <a href="traiter_justificatif.php?id=<?= $id_justificatif ?>" class="btn btn-primary">
                <i class="fas fa-check-circle"></i> Traiter
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-body">
        <!-- Timeline de suivi -->
        <?php
            $isTraite   = !empty($justificatif['traite']);
            $isApprouve = !empty($justificatif['approuve']);
            // Determine current step: 1 = Soumis, 2 = En examen (has attachment/been reviewed partially), 3 = Approuvé/Refusé
            $currentStep = 1; // Soumis
            if ($isTraite) {
                $currentStep = 3;
            } elseif (!empty($attachments) || !empty($justificatif['commentaire_admin'])) {
                $currentStep = 2; // En examen — attachments submitted or admin started reviewing
            }
        ?>
        <div class="justificatif-timeline">
            <div class="timeline-step <?= $currentStep >= 1 ? 'active' : '' ?>">
                <div class="timeline-dot"><i class="fas fa-paper-plane"></i></div>
                <div class="timeline-content">
                    <strong>Soumis</strong>
                    <?php if (!empty($justificatif['date_soumission'])): ?>
                    <span class="timeline-date"><?= date('d/m/Y à H:i', strtotime($justificatif['date_soumission'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="timeline-line <?= $currentStep >= 2 ? 'active' : '' ?>"></div>
            <div class="timeline-step <?= $currentStep >= 2 ? 'active' : '' ?>">
                <div class="timeline-dot"><i class="fas fa-search"></i></div>
                <div class="timeline-content">
                    <strong>En examen</strong>
                    <?php if ($currentStep === 2): ?>
                    <span class="timeline-date">En cours de traitement</span>
                    <?php elseif ($currentStep >= 3): ?>
                    <span class="timeline-date">Examiné</span>
                    <?php else: ?>
                    <span class="timeline-date text-muted">En attente</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="timeline-line <?= $currentStep >= 3 ? ($isApprouve ? 'active' : 'active refused') : '' ?>"></div>
            <div class="timeline-step <?= $currentStep >= 3 ? ($isApprouve ? 'active approved' : 'active refused') : '' ?>">
                <div class="timeline-dot">
                    <i class="fas <?= $isTraite ? ($isApprouve ? 'fa-check' : 'fa-times') : 'fa-question' ?>"></i>
                </div>
                <div class="timeline-content">
                    <strong><?= $isTraite ? ($isApprouve ? 'Approuvé' : 'Refusé') : 'Décision' ?></strong>
                    <?php if ($isTraite): ?>
                        <?php if (!empty($justificatif['traite_par'])): ?>
                        <span class="timeline-date">
                            par <?= htmlspecialchars($justificatif['traite_par']) ?>
                            <?php if (!empty($justificatif['date_traitement'])): ?>
                                le <?= date('d/m/Y à H:i', strtotime($justificatif['date_traitement'])) ?>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="timeline-date text-muted">En attente</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Informations élève -->
        <div class="form-container">
            <h3>Informations sur l'élève</h3>
            <div class="form-grid">
                <div class="form-group"><label>Nom</label><div class="form-value"><?= htmlspecialchars($justificatif['nom']) ?></div></div>
                <div class="form-group"><label>Prénom</label><div class="form-value"><?= htmlspecialchars($justificatif['prenom']) ?></div></div>
                <div class="form-group"><label>Classe</label><div class="form-value"><?= htmlspecialchars($justificatif['classe']) ?></div></div>
            </div>
        </div>

        <!-- Détails du justificatif -->
        <div class="form-container mt-4">
            <h3>Détails du justificatif</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date de soumission</label>
                    <div class="form-value"><?= isset($justificatif['date_soumission']) ? date('d/m/Y', strtotime($justificatif['date_soumission'])) : 'N/A' ?></div>
                </div>
                <div class="form-group">
                    <label>Période d'absence</label>
                    <div class="form-value">
                        Du <?= date('d/m/Y', strtotime($justificatif['date_debut_absence'])) ?>
                        au <?= date('d/m/Y', strtotime($justificatif['date_fin_absence'])) ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Motif</label>
                    <div class="form-value"><?= htmlspecialchars($justificatif['motif'] ?? 'Non spécifié') ?></div>
                </div>
                <div class="form-group form-full">
                    <label>Description</label>
                    <div class="form-value"><?= nl2br(htmlspecialchars($justificatif['description'] ?? 'Aucune description fournie')) ?></div>
                </div>

                <?php if (!empty($attachments)): ?>
                <div class="form-group form-full">
                    <label>Pièces jointes (<?= count($attachments) ?>)</label>
                    <div class="form-value">
                        <div class="attachments-list">
                            <?php foreach ($attachments as $att): ?>
                            <a href="download_fichier.php?id=<?= $att['id'] ?>" class="btn btn-outline attachment-link">
                                <i class="fas fa-file-download"></i> <?= htmlspecialchars($att['nom_original']) ?>
                                <span class="text-small text-muted">(<?= round($att['taille'] / 1024) ?> Ko)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php elseif (!empty($justificatif['fichier_path'])): ?>
                <div class="form-group form-full">
                    <label>Document justificatif</label>
                    <div class="form-value">
                        <a href="<?= htmlspecialchars($justificatif['fichier_path']) ?>" class="btn btn-outline" target="_blank">
                            <i class="fas fa-file-download"></i> Télécharger le justificatif
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($justificatif['traite'] && !empty($justificatif['commentaire_admin'])): ?>
                <div class="form-group form-full">
                    <label>Commentaire administratif</label>
                    <div class="form-value"><?= nl2br(htmlspecialchars($justificatif['commentaire_admin'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Absence associée -->
        <?php if ($absence): ?>
        <div class="form-container mt-4">
            <h3>Absence associée</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date de début</label>
                    <div class="form-value"><?= date('d/m/Y à H:i', strtotime($absence['date_debut'])) ?></div>
                </div>
                <div class="form-group">
                    <label>Date de fin</label>
                    <div class="form-value"><?= date('d/m/Y à H:i', strtotime($absence['date_fin'])) ?></div>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <div class="form-value">
                        <span class="badge badge-<?= htmlspecialchars($absence['type_absence']) ?>"><?= AbsenceHelper::typeLabel($absence['type_absence']) ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <div class="form-value">
                        <span class="badge <?= $absence['justifie'] ? 'badge-success' : 'badge-danger' ?>"><?= $absence['justifie'] ? 'Justifiée' : 'Non justifiée' ?></span>
                    </div>
                </div>
                <div class="form-group form-full">
                    <label>Actions</label>
                    <div class="form-value">
                        <a href="details_absence.php?id=<?= $absence['id'] ?>" class="btn btn-outline"><i class="fas fa-eye"></i> Voir les détails</a>
                        <?php if (canManageAbsences()): ?>
                        <a href="modifier_absence.php?id=<?= $absence['id'] ?>" class="btn btn-outline ml-2"><i class="fas fa-edit"></i> Modifier</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle"></i>
            <span>Aucune absence spécifique n'est associée à ce justificatif.</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <a href="justificatifs.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
        <?php if (!$justificatif['traite']): ?>
        <a href="traiter_justificatif.php?id=<?= $id_justificatif ?>" class="btn btn-primary"><i class="fas fa-check-circle"></i> Traiter ce justificatif</a>
        <?php endif; ?>
    </div>
</div>

<?php
include 'includes/footer.php';
ob_end_flush();
