<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Inclusion de l'API centralisée
require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/functions.php';

// Vérifier que l'utilisateur est connecté et autorisé avec le système centralisé
if (!isLoggedIn() || !canManageAbsences()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Récupérer les informations de l'utilisateur connecté via le système centralisé
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Récupérer l'ID de l'absence à modifier avec validation
$id_absence = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_absence) {
    // Redirection si l'ID n'est pas valide
    $_SESSION['error_message'] = "Identifiant d'absence non valide";
    header('Location: absences.php');
    exit;
}

// Récupérer les détails de l'absence
$absence = getAbsenceById($pdo, $id_absence);

// Vérifier si l'absence existe
if (!$absence) {
    header('Location: absences.php');
    exit;
}

// Message de succès ou d'erreur
$message = '';
$erreur = '';

// Traitement du formulaire avec validation renforcée
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // Validation des données du formulaire avec filter_input
        $date_debut = filter_input(INPUT_POST, 'date_debut', FILTER_SANITIZE_STRING);
        $heure_debut = filter_input(INPUT_POST, 'heure_debut', FILTER_SANITIZE_STRING);
        $date_fin = filter_input(INPUT_POST, 'date_fin', FILTER_SANITIZE_STRING);
        $heure_fin = filter_input(INPUT_POST, 'heure_fin', FILTER_SANITIZE_STRING);
        $type_absence = filter_input(INPUT_POST, 'type_absence', FILTER_SANITIZE_STRING);
        $motif = filter_input(INPUT_POST, 'motif', FILTER_SANITIZE_STRING);
        $justifie = isset($_POST['justifie']) ? 1 : 0;
        $commentaire = filter_input(INPUT_POST, 'commentaire', FILTER_SANITIZE_STRING);
        
        // Vérifications supplémentaires
        $types_valides = ['cours', 'demi-journee', 'journee'];
        $motifs_valides = ['', 'maladie', 'rdv_medical', 'familial', 'transport', 'autre'];
        
        if (empty($date_debut) || empty($heure_debut) || empty($date_fin) || 
            empty($heure_fin) || empty($type_absence)) {
            $erreur = "Veuillez remplir tous les champs obligatoires.";
        } elseif (!in_array($type_absence, $types_valides)) {
            $erreur = "Type d'absence invalide.";
        } elseif (!empty($motif) && !in_array($motif, $motifs_valides)) {
            $erreur = "Motif invalide.";
        } else {
            // Formater les dates et les valider
            $datetime_debut = $date_debut . ' ' . $heure_debut . ':00';
            $datetime_fin = $date_fin . ' ' . $heure_fin . ':00';
            
            // Valider le format des dates
            $format = 'Y-m-d H:i:s';
            $d1 = DateTime::createFromFormat($format, $datetime_debut);
            $d2 = DateTime::createFromFormat($format, $datetime_fin);
            
            if (!$d1 || $d1->format($format) !== $datetime_debut || 
                !$d2 || $d2->format($format) !== $datetime_fin) {
                $erreur = "Format de date ou d'heure invalide.";
            } elseif (strtotime($datetime_fin) <= strtotime($datetime_debut)) {
                $erreur = "La date/heure de fin doit être après la date/heure de début.";
            } else {
                // Préparer les données pour la mise à jour
                $data = [
                    'date_debut' => $datetime_debut,
                    'date_fin' => $datetime_fin,
                    'type_absence' => $type_absence,
                    'motif' => $motif,
                    'justifie' => $justifie,
                    'commentaire' => $commentaire
                ];
                
                // Mettre à jour l'absence dans la base de données
                $success = modifierAbsence($pdo, $id_absence, $data);
                
                if ($success) {
                    $message = "L'absence a été modifiée avec succès.";
                    // Mettre à jour les données affichées
                    $absence = getAbsenceById($pdo, $id_absence);
                    // Redirection après un court délai
                    header('refresh:2;url=details_absence.php?id=' . $id_absence);
                } else {
                    $erreur = "Une erreur est survenue lors de la modification de l'absence.";
                }
            }
        }
    }
}

// Extraire les composantes de date et heure
$date_debut = new DateTime($absence['date_debut']);
$date_fin = new DateTime($absence['date_fin']);

// Ajouter un jeton CSRF pour le formulaire
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
<?php
$pageTitle = 'Modifier une absence';
$currentPage = 'modifier';
include 'includes/header.php';
?>
      
      <!-- Content -->
      <div class="content-section">
        <?php if (!empty($message)): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= $message ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
          </div>
        <?php endif; ?>
        
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
                <h3>Élève: <?= htmlspecialchars($absence['prenom'] . ' ' . $absence['nom'] . ' (' . $absence['classe'] . ')') ?></h3>
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
                  <option value="cours" <?= $absence['type_absence'] === 'cours' ? 'selected' : '' ?>>Cours</option>
                  <option value="demi-journee" <?= $absence['type_absence'] === 'demi-journee' ? 'selected' : '' ?>>Demi-journée</option>
                  <option value="journee" <?= $absence['type_absence'] === 'journee' ? 'selected' : '' ?>>Journée complète</option>
                </select>
              </div>
              
              <div class="form-group">
                <label for="motif" class="form-label">Motif</label>
                <select name="motif" id="motif" class="form-control">
                  <option value="">Sélectionner un motif</option>
                  <option value="maladie" <?= $absence['motif'] === 'maladie' ? 'selected' : '' ?>>Maladie</option>
                  <option value="rdv_medical" <?= $absence['motif'] === 'rdv_medical' ? 'selected' : '' ?>>Rendez-vous médical</option>
                  <option value="familial" <?= $absence['motif'] === 'familial' ? 'selected' : '' ?>>Raison familiale</option>
                  <option value="transport" <?= $absence['motif'] === 'transport' ? 'selected' : '' ?>>Problème de transport</option>
                  <option value="autre" <?= $absence['motif'] === 'autre' ? 'selected' : '' ?>>Autre</option>
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
                <textarea name="commentaire" id="commentaire" rows="4" class="form-control"><?= htmlspecialchars($absence['commentaire']) ?></textarea>
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
    </div>
  </div>
  
  <script>
    // Script pour valider que la date de fin est après la date de début
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
<?php include 'includes/footer.php'; ob_end_flush(); ?>