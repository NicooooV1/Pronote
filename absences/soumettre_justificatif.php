<?php
/**
 * soumettre_justificatif.php — Formulaire de soumission d'un justificatif
 * Accessible aux parents et élèves pour justifier une absence.
 * Supporte l'upload de pièces jointes via FileUploadHandler.
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
$role = getUserRole();

// Seuls les élèves, parents et gestionnaires peuvent soumettre
if (!in_array($role, ['eleve', 'parent', 'admin', 'vie_scolaire'])) {
    header('Location: absences.php');
    exit;
}

$user         = getCurrentUser();
$userId       = (int) $user['id'];
$user_fullname = getUserFullName();
$user_initials = getUserInitials();
$user_role     = $role;

$pageTitle   = 'Soumettre un justificatif';
$currentPage = 'justificatifs';

// --- CSRF ---
$csrf_token = AbsenceHelper::generateCsrf();

// --- Récupérer les élèves disponibles ---
$eleves = [];
if ($role === 'eleve') {
    // L'élève ne peut soumettre que pour lui-même
    $stmt = $pdo->prepare("SELECT id, nom, prenom, classe FROM eleves WHERE id = ?");
    $stmt->execute([$userId]);
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($role === 'parent') {
    // Le parent ne peut soumettre que pour ses enfants
    $enfantIds = $repo->getChildrenForParent($userId);
    if (!empty($enfantIds)) {
        $ph = implode(',', array_fill(0, count($enfantIds), '?'));
        $stmt = $pdo->prepare("SELECT id, nom, prenom, classe FROM eleves WHERE id IN ($ph) ORDER BY nom, prenom");
        $stmt->execute($enfantIds);
        $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Admin/vie scolaire : tous les élèves
    $eleves = $repo->getAllEleves();
}

// --- Pré-remplissage depuis l'URL ---
$prefilledEleve = filter_input(INPUT_GET, 'eleve', FILTER_VALIDATE_INT) ?: '';
$prefilledAbsence = filter_input(INPUT_GET, 'absence', FILTER_VALIDATE_INT) ?: '';

// --- Traitement POST ---
$erreur  = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AbsenceHelper::verifyCsrf()) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $id_eleve    = filter_input(INPUT_POST, 'id_eleve', FILTER_VALIDATE_INT);
        $date_debut  = AbsenceHelper::sanitizeDate($_POST['date_debut_absence'] ?? '');
        $date_fin    = AbsenceHelper::sanitizeDate($_POST['date_fin_absence'] ?? '');
        $motif       = AbsenceHelper::sanitize($_POST['motif'] ?? '');
        $description = AbsenceHelper::sanitize($_POST['description'] ?? '');

        // Validations
        if (!$id_eleve) {
            $erreur = "Veuillez sélectionner un élève.";
        } elseif (empty($date_debut) || empty($date_fin)) {
            $erreur = "Veuillez renseigner les dates de début et de fin.";
        } elseif ($date_debut > $date_fin) {
            $erreur = "La date de début doit être antérieure à la date de fin.";
        } elseif (empty($motif)) {
            $erreur = "Veuillez indiquer un motif.";
        } else {
            // Vérifier que l'utilisateur a bien accès à cet élève
            $eleveAutorise = false;
            foreach ($eleves as $e) {
                if ((int) $e['id'] === $id_eleve) {
                    $eleveAutorise = true;
                    break;
                }
            }

            if (!$eleveAutorise) {
                $erreur = "Vous n'avez pas l'autorisation de soumettre un justificatif pour cet élève.";
            } else {
                // Créer le justificatif
                $justifId = $repo->createJustificatif([
                    'id_eleve'            => $id_eleve,
                    'date_debut_absence'  => $date_debut,
                    'date_fin_absence'    => $date_fin,
                    'motif'               => $motif,
                    'description'         => $description,
                    'soumis_par'          => $user_fullname,
                    'id_absence'          => $prefilledAbsence ?: null
                ]);

                if ($justifId) {
                    // Gérer les pièces jointes via FileUploadService centralisé
                    if (!empty($_FILES['fichiers']['name'][0])) {
                        $uploader = new \API\Services\FileUploadService('justificatifs');
                        $results = $uploader->uploadMultiple($_FILES['fichiers']);
                        foreach ($results as $result) {
                            if ($result['success']) {
                                $repo->addAttachment(
                                    $justifId,
                                    $result['nom_original'],
                                    $result['chemin'],
                                    $result['type_mime'],
                                    $result['taille']
                                );
                            }
                        }
                    }

                    $_SESSION['success_message'] = "Votre justificatif a été soumis avec succès.";
                    header('Location: justificatifs.php');
                    exit;
                } else {
                    $erreur = "Une erreur est survenue lors de la soumission.";
                }
            }
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
        <h2><i class="fas fa-file-upload"></i> Soumettre un justificatif</h2>
    </div>

    <div class="content-body">
        <form method="post" action="soumettre_justificatif.php<?= $prefilledAbsence ? '?absence=' . $prefilledAbsence : '' ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="form-container">
                <div class="form-grid">
                    <!-- Sélection de l'élève -->
                    <div class="form-group <?= count($eleves) === 1 ? '' : 'form-full' ?>">
                        <label for="id_eleve">Élève <span class="required">*</span></label>
                        <?php if (count($eleves) === 1): ?>
                            <input type="hidden" name="id_eleve" value="<?= $eleves[0]['id'] ?>">
                            <div class="form-value"><?= htmlspecialchars($eleves[0]['prenom'] . ' ' . $eleves[0]['nom'] . ' (' . $eleves[0]['classe'] . ')') ?></div>
                        <?php else: ?>
                            <select name="id_eleve" id="id_eleve" required>
                                <option value="">-- Sélectionner un élève --</option>
                                <?php foreach ($eleves as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= ($prefilledEleve == $e['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['prenom'] . ' ' . $e['nom'] . ' (' . $e['classe'] . ')') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Dates -->
                    <div class="form-group">
                        <label for="date_debut_absence">Date de début <span class="required">*</span></label>
                        <input type="date" name="date_debut_absence" id="date_debut_absence" required max="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($_POST['date_debut_absence'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_fin_absence">Date de fin <span class="required">*</span></label>
                        <input type="date" name="date_fin_absence" id="date_fin_absence" required max="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($_POST['date_fin_absence'] ?? '') ?>">
                    </div>

                    <!-- Motif -->
                    <div class="form-group form-full">
                        <label for="motif">Motif <span class="required">*</span></label>
                        <select name="motif" id="motif" required>
                            <option value="">-- Sélectionner un motif --</option>
                            <option value="maladie" <?= ($_POST['motif'] ?? '') === 'maladie' ? 'selected' : '' ?>>Maladie</option>
                            <option value="familial" <?= ($_POST['motif'] ?? '') === 'familial' ? 'selected' : '' ?>>Raison familiale</option>
                            <option value="medical" <?= ($_POST['motif'] ?? '') === 'medical' ? 'selected' : '' ?>>Rendez-vous médical</option>
                            <option value="administratif" <?= ($_POST['motif'] ?? '') === 'administratif' ? 'selected' : '' ?>>Raison administrative</option>
                            <option value="autre" <?= ($_POST['motif'] ?? '') === 'autre' ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </div>

                    <!-- Description -->
                    <div class="form-group form-full">
                        <label for="description">Description / Détails</label>
                        <textarea name="description" id="description" rows="4" placeholder="Décrivez la raison de l'absence..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Pièces jointes -->
                    <div class="form-group form-full">
                        <label for="fichiers">Pièces jointes (max 5 fichiers, 5 Mo chacun)</label>
                        <input type="file" name="fichiers[]" id="fichiers" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                        <p class="text-small text-muted">Formats acceptés : PDF, images (JPG, PNG, GIF), documents Word</p>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions form-full">
                        <a href="absences.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Soumettre le justificatif
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
