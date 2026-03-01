<?php
/**
 * traiter_justificatif.php — Formulaire de traitement d'un justificatif
 * Refactorisé : AbsenceRepository + AbsenceHelper, suppression FILTER_SANITIZE_STRING,
 * suppression inline SQL et error_log, support file upload via FileUploadHandler.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

if (!isLoggedIn() || !canManageAbsences()) {
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

// Déjà traité ?
if ($justificatif['traite']) {
    $_SESSION['error_message'] = "Ce justificatif a déjà été traité";
    header('Location: details_justificatif.php?id=' . $id_justificatif);
    exit;
}

// --- Config page ---
$pageTitle      = 'Traiter le justificatif';
$currentPage    = 'justificatifs';
$showBackButton = true;
$backLink       = 'details_justificatif.php?id=' . $id_justificatif;

// --- CSRF ---
$csrf_token = AbsenceHelper::generateCsrf();

// --- Absences dans la période ---
$absences = $repo->getAbsencesForJustificatif(
    (int) $justificatif['id_eleve'],
    $justificatif['date_debut_absence'],
    $justificatif['date_fin_absence']
);

// --- Traitement POST ---
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AbsenceHelper::verifyCsrf()) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $approuve    = isset($_POST['approuve']) ? 1 : 0;
        $id_absence  = filter_input(INPUT_POST, 'id_absence', FILTER_VALIDATE_INT) ?: null;
        $commentaire = AbsenceHelper::sanitize($_POST['commentaire'] ?? '');

        $success = $repo->traiterJustificatif(
            $id_justificatif,
            (bool) $approuve,
            $commentaire,
            $user_fullname,
            $id_absence
        );

        if ($success) {
            $_SESSION['success_message'] = "Le justificatif a été traité avec succès.";
            header('Location: details_justificatif.php?id=' . $id_justificatif);
            exit;
        } else {
            $erreur = "Une erreur est survenue lors du traitement.";
        }
    }
}

include 'includes/header.php';
?>

<?php if (!empty($erreur)): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($erreur) ?></span>
</div>
<?php endif; ?>

<div class="content-container">
    <div class="content-header">
        <h2>Traiter le justificatif #<?= $id_justificatif ?></h2>
    </div>

    <div class="content-body">
        <!-- Résumé du justificatif -->
        <div class="form-container mb-4">
            <h3>Informations sur le justificatif</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Élève</label>
                    <div class="form-value"><?= htmlspecialchars($justificatif['prenom'] . ' ' . $justificatif['nom']) ?> (<?= htmlspecialchars($justificatif['classe']) ?>)</div>
                </div>
                <div class="form-group">
                    <label>Date de soumission</label>
                    <div class="form-value"><?= isset($justificatif['date_soumission']) ? date('d/m/Y', strtotime($justificatif['date_soumission'])) : 'N/A' ?></div>
                </div>
                <div class="form-group form-full">
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

                <?php if (!empty($justificatif['fichier_path'])): ?>
                <div class="form-group form-full">
                    <label>Document justificatif</label>
                    <div class="form-value">
                        <a href="<?= htmlspecialchars($justificatif['fichier_path']) ?>" class="btn btn-outline" target="_blank">
                            <i class="fas fa-file-download"></i> Télécharger
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulaire de traitement -->
        <form method="post" action="traiter_justificatif.php?id=<?= $id_justificatif ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="form-container">
                <h3>Traitement du justificatif</h3>
                <div class="form-grid">
                    <div class="form-group form-full">
                        <div class="checkbox-group">
                            <input type="checkbox" name="approuve" id="approuve" checked>
                            <label for="approuve">Approuver ce justificatif</label>
                        </div>
                        <p class="text-small text-muted">
                            Si approuvé, l'absence sélectionnée ci-dessous sera automatiquement marquée comme justifiée.
                        </p>
                    </div>

                    <div class="form-group form-full">
                        <label for="id_absence">Absence à justifier</label>
                        <select name="id_absence" id="id_absence">
                            <option value="">-- Sélectionner une absence --</option>
                            <?php foreach ($absences as $abs): ?>
                            <option value="<?= $abs['id'] ?>" <?= (isset($justificatif['id_absence']) && $abs['id'] == $justificatif['id_absence']) ? 'selected' : '' ?>>
                                <?= date('d/m/Y H:i', strtotime($abs['date_debut'])) ?> — <?= date('d/m/Y H:i', strtotime($abs['date_fin'])) ?>
                                (<?= AbsenceHelper::typeLabel($abs['type_absence']) ?>)
                                <?= $abs['justifie'] ? '[Déjà justifiée]' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label for="commentaire">Commentaire administratif</label>
                        <textarea name="commentaire" id="commentaire" rows="4"><?= htmlspecialchars($justificatif['commentaire_admin'] ?? '') ?></textarea>
                    </div>

                    <div class="form-actions form-full">
                        <a href="details_justificatif.php?id=<?= $id_justificatif ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Valider le traitement
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
include 'includes/footer.php';
ob_end_flush();
