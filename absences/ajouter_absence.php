<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Utiliser le système de gestion d'erreurs centralisé au lieu d'activer manuellement l'affichage des erreurs
require_once __DIR__ . '/../API/errors.php';

// Inclusion des fichiers nécessaires - Utiliser le système centralisé
require_once __DIR__ . '/../API/auth_central.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Vérifier que l'utilisateur est connecté et autorisé
if (!isLoggedIn() || !canManageAbsences()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Récupérer les informations de l'utilisateur connecté via le système centralisé
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Ajouter un jeton CSRF pour protéger le formulaire
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Message de succès ou d'erreur
$message = '';
$erreur = '';

// Traitement du formulaire avec vérification CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // Validation des données du formulaire
        if (empty($_POST['id_eleve']) || empty($_POST['date_debut']) || empty($_POST['heure_debut']) || 
            empty($_POST['date_fin']) || empty($_POST['heure_fin']) || empty($_POST['type_absence'])) {
            $erreur = "Veuillez remplir tous les champs obligatoires.";
        } else {
            // Formater les dates et heures
            $date_debut = $_POST['date_debut'] . ' ' . $_POST['heure_debut'] . ':00';
            $date_fin = $_POST['date_fin'] . ' ' . $_POST['heure_fin'] . ':00';
            
            // S'assurer que la date de fin est après la date de début
            if (strtotime($date_fin) <= strtotime($date_debut)) {
                $erreur = "La date/heure de fin doit être après la date/heure de début.";
            } else {
                // Préparer les données pour l'insertion
                $data = [
                    'id_eleve' => intval($_POST['id_eleve']),
                    'date_debut' => $date_debut,
                    'date_fin' => $date_fin,
                    'type_absence' => $_POST['type_absence'],
                    'motif' => !empty($_POST['motif']) ? $_POST['motif'] : null,
                    'justifie' => isset($_POST['justifie']),
                    'commentaire' => !empty($_POST['commentaire']) ? $_POST['commentaire'] : null,
                    'signale_par' => $user_fullname
                ];
                
                // Ajouter l'absence dans la base de données
                $id_absence = ajouterAbsence($pdo, $data);
                
                if ($id_absence) {
                    $message = "L'absence a été ajoutée avec succès.";
                    // Redirection après un court délai
                    header('refresh:2;url=absences.php');
                } else {
                    $erreur = "Une erreur est survenue lors de l'ajout de l'absence. Veuillez vérifier les logs pour plus de détails.";
                }
            }
        }
    }
}

// Récupérer la liste des élèves
$eleves = [];
try {
    $stmt = $pdo->query("SELECT id, nom, prenom, classe FROM eleves ORDER BY classe, nom, prenom");
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Journaliser l'erreur
    \Pronote\Logging\error("Erreur lors de la récupération des élèves: " . $e->getMessage());
    // Message générique pour l'utilisateur
    $erreur = "Une erreur est survenue lors du chargement des données. Veuillez réessayer ultérieurement.";
}

// Récupérer la date à suggérer (aujourd'hui ou date passée en paramètre)
$date_suggere = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$heure_debut_suggere = isset($_GET['debut']) ? $_GET['debut'] : '08:00';
$heure_fin_suggere = isset($_GET['fin']) ? $_GET['fin'] : '09:00';
$id_eleve_suggere = isset($_GET['eleve']) ? $_GET['eleve'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ajouter une absence - PRONOTE</title>
  <link rel="stylesheet" href="assets/css/absences.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
      <div class="logo-container">
        <div class="app-logo">P</div>
        <div class="app-title">PRONOTE</div>
      </div>
      
      <!-- Module de navigation principal -->
      <div class="sidebar-section">
        <div class="sidebar-section-header">Navigation</div>
        <div class="sidebar-nav">
          <a href="../accueil/accueil.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
            <span>Accueil</span>
          </a>
          <a href="../notes/notes.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
            <span>Notes</span>
          </a>
          <a href="../agenda/agenda.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
            <span>Agenda</span>
          </a>
          <a href="../cahierdetextes/cahierdetextes.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
            <span>Cahier de textes</span>
          </a>
          <a href="../messagerie/index.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
            <span>Messagerie</span>
          </a>
          <a href="absences.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
            <span>Absences</span>
          </a>
        </div>
      </div>
      
      <!-- Actions spécifiques au module absences -->
      <div class="sidebar-section">
        <div class="sidebar-section-header">Actions</div>
        <div class="sidebar-nav">
          <a href="absences.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
            <span>Liste des absences</span>
          </a>
          
          <a href="ajouter_absence.php" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
            <span>Signaler une absence</span>
          </a>
          <a href="appel.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
            <span>Faire l'appel</span>
          </a>
        </div>
      </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
      <!-- Header -->
      <div class="top-header">
        <div class="page-title">
          <a href="absences.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
          </a>
          <h1>Signaler une absence</h1>
        </div>
        
        <div class="header-actions">
          <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">⏻</a>
          <div class="user-avatar"><?= $user_initials ?></div>
        </div>
      </div>
      
      <!-- Content -->
      <div class="content-container">
        <?php if (!empty($message)): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= $message ?>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($erreur)): ?>
          <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= $erreur ?>
          </div>
        <?php endif; ?>
        
        <div class="form-container">
          <form method="post" action="ajouter_absence.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="form-grid">
              <div class="form-group form-full">
                <label for="id_eleve">Élève <span class="required">*</span></label>
                <select name="id_eleve" id="id_eleve" required>
                  <option value="">Sélectionner un élève</option>
                  <?php foreach ($eleves as $eleve): ?>
                    <option value="<?= $eleve['id'] ?>" <?= $id_eleve_suggere == $eleve['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom'] . ' (' . $eleve['classe'] . ')') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group">
                <label for="date_debut">Date de début <span class="required">*</span></label>
                <input type="date" name="date_debut" id="date_debut" value="<?= $date_suggere ?>" required max="<?= date('Y-m-d') ?>">
              </div>
              
              <div class="form-group">
                <label for="heure_debut">Heure de début <span class="required">*</span></label>
                <input type="time" name="heure_debut" id="heure_debut" value="<?= $heure_debut_suggere ?>" required>
            </div>
            
            <div class="form-group">
                <label for="date_fin">Date de fin <span class="required">*</span></label>
                <input type="date" name="date_fin" id="date_fin" value="<?= $date_suggere ?>" required max="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="form-group">
                <label for="heure_fin">Heure de fin <span class="required">*</span></label>
                <input type="time" name="heure_fin" id="heure_fin" value="<?= $heure_fin_suggere ?>" required>
            </div>
            
            <div class="form-group">
                <label for="type_absence">Type d'absence <span class="required">*</span></label>
                <select name="type_absence" id="type_absence" required>
                    <option value="">Sélectionner un type</option>
                    <option value="cours">Cours</option>
                    <option value="demi-journee">Demi-journée</option>
                    <option value="journee">Journée complète</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="motif">Motif</label>
                <select name="motif" id="motif">
                    <option value="">Sélectionner un motif</option>
                    <option value="maladie">Maladie</option>
                    <option value="rdv_medical">Rendez-vous médical</option>
                    <option value="familial">Raison familiale</option>
                    <option value="transport">Problème de transport</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            
            <div class="form-group form-full">
                <div class="checkbox-group">
                    <input type="checkbox" name="justifie" id="justifie">
                    <label for="justifie">Absence justifiée</label>
                </div>
            </div>
            
            <div class="form-group form-full">
                <label for="commentaire">Commentaire</label>
                <textarea name="commentaire" id="commentaire" rows="4"></textarea>
            </div>
            
            <div class="form-actions form-full">
                <a href="absences.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">Enregistrer l'absence</button>
            </div>
        </div>
    </form>
</div>

<script>
    // Script pour synchroniser les dates de début et fin
    document.getElementById('date_debut').addEventListener('change', function() {
        document.getElementById('date_fin').value = this.value;
    });
    
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

<?php
// Inclure le pied de page
include 'includes/footer.php';
ob_end_flush();
?>