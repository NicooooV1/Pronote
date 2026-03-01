<?php
/**
 * modifier_absence.php — Formulaire de modification d'absence.
 * Corrections: remplacement FILTER_SANITIZE_STRING par AbsenceHelper::sanitize(),
 * utilisation AbsenceRepository.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

$pdo = getPDO();

if (!isLoggedIn() || !canManageAbsences()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();

$repo = new AbsenceRepository($pdo);

// Récupérer l'ID de l'absence
$id_absence = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_absence) {
    $_SESSION['error_message'] = "Identifiant d'absence non valide";
    header('Location: absences.php');
    exit;
}

$absence = $repo->getById($id_absence);
if (!$absence) {
    $_SESSION['error_message'] = "Absence non trouvée";
    header('Location: absences.php');
    exit;
}

$message = '';
$erreur  = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AbsenceHelper::verifyCsrf()) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $date_debut_post    = AbsenceHelper::sanitize($_POST['date_debut'] ?? '');
        $heure_debut_post   = AbsenceHelper::sanitize($_POST['heure_debut'] ?? '');
        $date_fin_post      = AbsenceHelper::sanitize($_POST['date_fin'] ?? '');
        $heure_fin_post     = AbsenceHelper::sanitize($_POST['heure_fin'] ?? '');
        $type_absence_post  = AbsenceHelper::sanitize($_POST['type_absence'] ?? '');
        $motif_post         = AbsenceHelper::sanitize($_POST['motif'] ?? '');
        $justifie_post      = isset($_POST['justifie']) ? 1 : 0;
        $commentaire_post   = AbsenceHelper::sanitize($_POST['commentaire'] ?? '');
        
        if (empty($date_debut_post) || empty($heure_debut_post) || empty($date_fin_post) || 
            empty($heure_fin_post) || empty($type_absence_post)) {
            $erreur = "Veuillez remplir tous les champs obligatoires.";
        } elseif (!in_array($type_absence_post, AbsenceHelper::validTypes())) {
            $erreur = "Type d'absence invalide.";
        } elseif (!empty($motif_post) && !in_array($motif_post, AbsenceHelper::validMotifs())) {
            $erreur = "Motif invalide.";
        } else {
            $datetime_debut = $date_debut_post . ' ' . $heure_debut_post . ':00';
            $datetime_fin   = $date_fin_post . ' ' . $heure_fin_post . ':00';
            
            $d1 = DateTime::createFromFormat('Y-m-d H:i:s', $datetime_debut);
            $d2 = DateTime::createFromFormat('Y-m-d H:i:s', $datetime_fin);
            
            if (!$d1 || !$d2) {
                $erreur = "Format de date ou d'heure invalide.";
            } elseif ($d2 <= $d1) {
                $erreur = "La date/heure de fin doit être après la date/heure de début.";
            } else {
                $data = [
                    'date_debut'    => $datetime_debut,
                    'date_fin'      => $datetime_fin,
                    'type_absence'  => $type_absence_post,
                    'motif'         => $motif_post ?: null,
                    'justifie'      => $justifie_post,
                    'commentaire'   => $commentaire_post ?: null
                ];
                
                if ($repo->update($id_absence, $data)) {
                    $_SESSION['success_message'] = "L'absence a été modifiée avec succès.";
                    header('Location: details_absence.php?id=' . $id_absence);
                    exit;
                } else {
                    $erreur = "Une erreur est survenue lors de la modification.";
                }
            }
        }
    }
}

// Extraire les composantes de date et heure
$date_debut = new DateTime($absence['date_debut']);
$date_fin   = new DateTime($absence['date_fin']);

$csrf_token = AbsenceHelper::generateCsrf();

$pageTitle   = 'Modifier une absence';
$currentPage = 'modifier';
include 'includes/header.php';
?>
      
<div class="content-section">
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $erreur ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="post" action="modifier_absence.php?id=<?= $id_absence ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="form-grid">
                <div class="form-group form-full">
                    <h3>Élève : <?= htmlspecialchars($absence['prenom'] . ' ' . $absence['nom'] . ' (' . $absence['classe'] . ')') ?></h3>
                </div>
                
                <div class="form-group">
                    <label for="date_debut" class="form-label">Date de début <span class="required">*</span></label>
                    <input type="date" name="date_debut" id="date_debut" value="<?= $date_debut->format('Y-m-d') ?>" required max="<?= date('Y-m-d') ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="heure_debut" class="form-label">Heure de début <span class="required">*</span></label>
                    <input type="time" name="heure_debut" id="heure_debut" value="<?= $date_debut->format('H:i') ?>" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="date_fin" class="form-label">Date de fin <span class="required">*</span></label>
                    <input type="date" name="date_fin" id="date_fin" value="<?= $date_fin->format('Y-m-d') ?>" required max="<?= date('Y-m-d') ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="heure_fin" class="form-label">Heure de fin <span class="required">*</span></label>
                    <input type="time" name="heure_fin" id="heure_fin" value="<?= $date_fin->format('H:i') ?>" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="type_absence" class="form-label">Type d'absence <span class="required">*</span></label>
                    <select name="type_absence" id="type_absence" required class="form-control">
                        <?php foreach (AbsenceHelper::validTypes() as $t): ?>
                        <option value="<?= $t ?>" <?= $absence['type_absence'] === $t ? 'selected' : '' ?>><?= AbsenceHelper::typeLabel($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="motif" class="form-label">Motif</label>
                    <select name="motif" id="motif" class="form-control">
                        <option value="">Sélectionner un motif</option>
                        <?php foreach (array_filter(AbsenceHelper::validMotifs()) as $m): ?>
                        <option value="<?= $m ?>" <?= ($absence['motif'] ?? '') === $m ? 'selected' : '' ?>><?= AbsenceHelper::motifLabel($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group form-full">
                    <div class="checkbox-group">
                        <input type="checkbox" name="justifie" id="justifie" <?= $absence['justifie'] ? 'checked' : '' ?> class="form-check">
                        <label for="justifie" class="form-check-label">Absence justifiée</label>
                    </div>
                </div>
                
                <div class="form-group form-full">
                    <label for="commentaire" class="form-label">Commentaire</label>
                    <textarea name="commentaire" id="commentaire" rows="4" class="form-control"><?= htmlspecialchars($absence['commentaire'] ?? '') ?></textarea>
                </div>
                
                <div class="form-actions form-full">
                    <a href="details_absence.php?id=<?= $id_absence ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const dateDebut = document.getElementById('date_debut').value;
        const dateFin = document.getElementById('date_fin').value;
        const heureDebut = document.getElementById('heure_debut').value;
        const heureFin = document.getElementById('heure_fin').value;
        
        const debutComplet = new Date(dateDebut + 'T' + heureDebut);
        const finComplet = new Date(dateFin + 'T' + heureFin);
        
        if (finComplet <= debutComplet) {
            alert("La date et l'heure de fin doivent être après la date et l'heure de début.");
            e.preventDefault();
        }
    });
</script>

<?php
include 'includes/footer.php';
ob_end_flush();
