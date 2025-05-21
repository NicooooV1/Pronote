<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Inclusion des fichiers nécessaires
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ../login/public/index.php');
    exit;
}

// Récupérer l'ID de l'absence
$id_absence = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer les informations de l'utilisateur connecté
$user = $_SESSION['user'];
$user_fullname = $user['prenom'] . ' ' . $user['nom'];
$user_role = $user['profil'];
$user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

// Récupérer les détails de l'absence
$absence = getAbsenceById($pdo, $id_absence);

// Vérifier si l'absence existe et si l'utilisateur a le droit de la voir
if (!$absence) {
    header('Location: absences.php');
    exit;
}

// Vérifier les droits d'accès selon le rôle
$access_granted = false;

if (isAdmin() || isVieScolaire()) {
    $access_granted = true;
} elseif (isTeacher()) {
    // Vérifier si l'élève est dans une des classes du professeur
    $stmt = $pdo->prepare("SELECT classe FROM professeurs WHERE id = ?");
    $stmt->execute([$user['id']]);
    $prof_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array($absence['classe'], $prof_classes)) {
        $access_granted = true;
    }
} elseif (isStudent()) {
    // Vérifier si c'est l'absence de l'élève connecté
    if ($absence['id_eleve'] == $user['id']) {
        $access_granted = true;
    }
} elseif (isParent()) {
    // Vérifier si c'est l'absence d'un des enfants du parent
    $stmt = $pdo->prepare("SELECT id_eleve FROM parents_eleves WHERE id_parent = ?");
    $stmt->execute([$user['id']]);
    $enfants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array($absence['id_eleve'], $enfants)) {
        $access_granted = true;
    }
}

// Rediriger si l'accès n'est pas autorisé
if (!$access_granted) {
    header('Location: absences.php');
    exit;
}

// Formatage des dates
$date_debut = new DateTime($absence['date_debut']);
$date_fin = new DateTime($absence['date_fin']);

// Calculer la durée
$duree = $date_debut->diff($date_fin);
$duree_str = '';

if ($duree->days > 0) {
    $duree_str .= $duree->days . ' jour(s) ';
}
if ($duree->h > 0) {
    $duree_str .= $duree->h . ' heure(s) ';
}
if ($duree->i > 0) {
    $duree_str .= $duree->i . ' minute(s)';
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Détails de l'absence - Pronote</title>
  <link rel="stylesheet" href="../agenda/assets/css/calendar.css">
  <link rel="stylesheet" href="assets/css/absences.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <div class="app-container">
    <!-- Sidebar - Style harmonisé avec l'accueil -->
    <div class="sidebar">
      <a href="../accueil/accueil.php" class="logo-container">
        <div class="app-logo">P</div>
        <div class="app-title">PRONOTE</div>
      </a>
      
      <!-- Module de navigation principal -->
      <div class="sidebar-section">
        <div class="sidebar-section-header">NAVIGATION</div>
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
          <a href="absences.php" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
            <span>Absences</span>
          </a>
        </div>
      </div>
      
      <!-- Utiliser la même structure et noms de sections que l'accueil -->
      <div class="sidebar-section">
        <div class="sidebar-section-header">ACTIONS</div>
        <div class="sidebar-nav">
          <a href="absences.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
            <span>Liste des absences</span>
          </a>
          <?php if (canManageAbsences()): ?>
          <a href="ajouter_absence.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
            <span>Signaler une absence</span>
          </a>
          <a href="justificatifs.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-file-alt"></i></span>
            <span>Justificatifs</span>
          </a>
          <?php endif; ?>
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
          <h1>Détails de l'absence</h1>
        </div>
        
        <div class="header-actions">
          <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">⏻</a>
          <div class="user-avatar"><?= $user_initials ?></div>
        </div>
      </div>
      
      <!-- Content -->
      <div class="content-container">
        <div class="details-container">
          <div class="details-header">
            <h2><?= htmlspecialchars($absence['prenom'] . ' ' . $absence['nom']) ?> - <?= htmlspecialchars($absence['classe']) ?></h2>
            <div class="details-actions">
              <?php if (canManageAbsences()): ?>
                <a href="modifier_absence.php?id=<?= $absence['id'] ?>" class="btn btn-primary">
                  <i class="fas fa-edit"></i> Modifier
                </a>
                <a href="supprimer_absence.php?id=<?= $absence['id'] ?>" class="btn btn-danger" 
                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette absence ?');">
                  <i class="fas fa-trash"></i> Supprimer
                </a>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="details-content">
            <div class="details-section">
              <h3>Informations générales</h3>
              <div class="details-grid">
                <div class="details-row">
                  <div class="details-label">Type d'absence:</div>
                  <div class="details-value">
                    <span class="badge badge-<?= $absence['type_absence'] ?>">
                      <?= ucfirst($absence['type_absence']) ?>
                    </span>
                  </div>
                </div>
                
                <div class="details-row">
                  <div class="details-label">Début:</div>
                  <div class="details-value"><?= $date_debut->format('d/m/Y H:i') ?></div>
                </div>
                
                <div class="details-row">
                  <div class="details-label">Fin:</div>
                  <div class="details-value"><?= $date_fin->format('d/m/Y H:i') ?></div>
                </div>
                
                <div class="details-row">
                  <div class="details-label">Durée:</div>
                  <div class="details-value"><?= $duree_str ?></div>
                </div>
                
                <div class="details-row">
                  <div class="details-label">Justifiée:</div>
                  <div class="details-value">
                    <?php if ($absence['justifie']): ?>
                      <span class="badge badge-success">Oui</span>
                    <?php else: ?>
                      <span class="badge badge-danger">Non</span>
                    <?php endif; ?>
                  </div>
                </div>
                
                <?php if (!empty($absence['motif'])): ?>
                <div class="details-row">
                  <div class="details-label">Motif:</div>
                  <div class="details-value"><?= htmlspecialchars($absence['motif']) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="details-row">
                  <div class="details-label">Signalée par:</div>
                  <div class="details-value"><?= htmlspecialchars($absence['signale_par']) ?></div>
                </div>
              </div>
            </div>
            
            <?php if (!empty($absence['commentaire'])): ?>
            <div class="details-section">
              <h3>Commentaire</h3>
              <div class="details-text">
                <?= nl2br(htmlspecialchars($absence['commentaire'])) ?>
              </div>
            </div>
            <?php endif; ?>
            
            <?php if (canManageAbsences() && !$absence['justifie']): ?>
            <div class="details-section">
              <h3>Justifier l'absence</h3>
              <form action="justifier_absence.php" method="post">
                <input type="hidden" name="id" value="<?= $absence['id'] ?>">
                <div class="form-group">
                  <label for="motif">Motif de justification</label>
                  <select name="motif" id="motif" class="form-control">
                    <option value="">Sélectionner un motif</option>
                    <option value="maladie">Maladie</option>
                    <option value="rdv_medical">Rendez-vous médical</option>
                    <option value="familial">Raison familiale</option>
                    <option value="transport">Problème de transport</option>
                    <option value="autre">Autre</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label for="commentaire_justification">Commentaire</label>
                  <textarea name="commentaire_justification" id="commentaire_justification" rows="3" class="form-control"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-check"></i> Justifier
                </button>
              </form>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <style>
    .details-container {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      overflow: hidden;
    }
    
    .details-header {
      padding: 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .details-header h2 {
      margin: 0;
      font-size: 1.4rem;
      color: #333;
    }
    
    .details-actions {
      display: flex;
      gap: 10px;
    }
    
    .details-content {
      padding: 20px;
    }
    
    .details-section {
      margin-bottom: 30px;
    }
    
    .details-section h3 {
      font-size: 1.1rem;
      color: #444;
      margin-bottom: 15px;
      padding-bottom: 8px;
      border-bottom: 1px solid #eee;
    }
    
    .details-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 15px;
    }
    
    .details-row {
      display: flex;
      margin-bottom: 10px;
    }
    
    .details-label {
      font-weight: 500;
      color: #666;
      width: 140px;
      flex-shrink: 0;
    }
    
    .details-value {
      color: #333;
    }
    
    .details-text {
      line-height: 1.5;
      color: #333;
    }
    
    @media (max-width: 768px) {
      .details-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .details-actions {
        margin-top: 15px;
      }
      
      .details-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</body>
</html>
<?php ob_end_flush(); ?>