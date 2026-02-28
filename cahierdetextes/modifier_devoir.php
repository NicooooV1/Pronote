<?php
ob_start();

// Inclusion de l'API centralisée
require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/auth.php';

// Vérifications d'accès
requireAuth();
if (!canManageDevoirs()) {
  header('Location: cahierdetextes.php');
  exit;
}

// Générer ou vérifier le token CSRF
$csrf_token = csrf_token();

// Récupérer les informations de l'utilisateur
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header('Location: cahierdetextes.php');
  exit;
}

$id = intval($_GET['id']); // Sanitize with intval

try {
  $stmt = $pdo->prepare('SELECT * FROM devoirs WHERE id = ?');
  $stmt->execute([$id]);
  $devoir = $stmt->fetch();

  if (!$devoir) {
    header('Location: cahierdetextes.php?error=notfound');
    exit;
  }

  // Si c'est un professeur (et pas un admin ou vie scolaire), il peut seulement modifier ses propres devoirs
  if (isTeacher() && !isAdmin() && !isVieScolaire()) {
    if ($devoir['nom_professeur'] !== $user_fullname) {
      header('Location: cahierdetextes.php?error=unauthorized');
      exit;
    }
  }
} catch (PDOException $e) {
  error_log("Erreur dans modifier_devoir.php: " . $e->getMessage());
  header('Location: cahierdetextes.php?error=dbfailed');
  exit;
}

// Récupérer la liste des professeurs
$stmt_profs = $pdo->query('SELECT id, nom, prenom, matiere FROM professeurs ORDER BY nom, prenom');
$professeurs = $stmt_profs->fetchAll();

// Si c'est un professeur, récupérer sa matière
$prof_matiere = '';
if (isTeacher()) {
  $stmt_prof = $pdo->prepare('SELECT matiere FROM professeurs WHERE nom = ? AND prenom = ?');
  $stmt_prof->execute([$user['nom'], $user['prenom']]);
  $prof_data = $stmt_prof->fetch();
  $prof_matiere = $prof_data ? $prof_data['matiere'] : '';
}

// Charger les données depuis le fichier JSON
$json_file = __DIR__ . '/../login/data/etablissement.json';
$etablissement_data = [];
if (file_exists($json_file)) {
  $etablissement_data = json_decode(file_get_contents($json_file), true);
}

$message = '';
$erreur = '';
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Vérification du token CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
    $erreur = "Erreur de validation du formulaire. Veuillez réessayer.";
  }
  // Validation des champs
  else if (empty($_POST['titre']) || empty($_POST['description']) || empty($_POST['classe']) ||
      empty($_POST['nom_matiere']) || empty($_POST['nom_professeur']) || 
      empty($_POST['date_ajout']) || empty($_POST['date_rendu'])) {
    $erreur = "Veuillez remplir tous les champs obligatoires.";
  } else {
    // Vérifier que la date de rendu est postérieure à la date d'ajout
    if (strtotime($_POST['date_rendu']) <= strtotime($_POST['date_ajout'])) {
      $erreur = "La date de rendu doit être postérieure à la date d'ajout.";
    } else {
      try {
        // Mettre à jour le devoir dans la base de données
        $stmt = $pdo->prepare('UPDATE devoirs SET titre = ?, description = ?, classe = ?, nom_matiere = ?, nom_professeur = ?, date_ajout = ?, date_rendu = ? WHERE id = ?');
        $stmt->execute([
          trim($_POST['titre']), // Trim pour enlever les espaces inutiles
          trim($_POST['description']),
          $_POST['classe'],
          $_POST['nom_matiere'],
          $_POST['nom_professeur'],
          $_POST['date_ajout'],
          $_POST['date_rendu'],
          $id
        ]);
        
        $message = "Le devoir a été mis à jour avec succès.";
        $success = true;
        
        // Recharger les données du devoir
        $stmt = $pdo->prepare('SELECT * FROM devoirs WHERE id = ?');
        $stmt->execute([$id]);
        $devoir = $stmt->fetch();
        
        // Redirection après un court délai
        header('refresh:2;url=cahierdetextes.php?success=updated');
      } catch (PDOException $e) {
        error_log("Erreur de mise à jour dans modifier_devoir.php: " . $e->getMessage());
        $erreur = "Une erreur est survenue lors de la mise à jour du devoir.";
      }
    }
  }
}

// Calculer l'état du devoir
$date_rendu = new DateTime($devoir['date_rendu']);
$aujourdhui = new DateTime();
$diff = $aujourdhui->diff($date_rendu);

$statusClass = '';
$statusText = '';

if ($date_rendu < $aujourdhui) {
    $statusClass = 'expired';
    $statusText = 'Expiré';
} elseif ($diff->days <= 3) {
    $statusClass = 'urgent';
    $statusText = 'Urgent (< 3 jours)';
} elseif ($diff->days <= 7) {
    $statusClass = 'soon';
    $statusText = 'Cette semaine';
} else {
    $statusText = 'À venir';
}

// Variables pour le template
$pageTitle = "Modifier un devoir";

include 'includes/header.php';
?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2>Modifier un devoir</h2>
                <p>Mise à jour du devoir : <?= htmlspecialchars($devoir['titre']) ?></p>
            </div>
            <div class="welcome-logo">
                <i class="fas fa-edit"></i>
            </div>
        </div>
        
        <!-- Main Dashboard Content -->
        <div class="dashboard-content">
            <?php if ($message): ?>
                <div class="alert-banner alert-<?= $success ? 'success' : 'error' ?>">
                  <i class="fas fa-<?= $success ? 'check-circle' : 'exclamation-circle' ?>"></i>
                  <?= htmlspecialchars($message) ?>
                  <button class="alert-close">&times;</button>
                </div>
            <?php endif; ?>
              
            <?php if ($erreur): ?>
                <div class="alert-banner alert-error">
                  <i class="fas fa-exclamation-circle"></i>
                  <?= htmlspecialchars($erreur) ?>
                  <button class="alert-close">&times;</button>
                </div>
            <?php endif; ?>
              
            <div class="devoir-card <?= $statusClass ?>">
                <div class="card-header">
                  <div class="devoir-title">
                    <i class="fas fa-edit"></i> Modifier le devoir
                    <?php if ($statusClass): ?>
                      <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="devoir-meta">Créé le: <?= date('d/m/Y', strtotime($devoir['date_creation'])) ?></div>
                </div>
                
                <div class="card-body">
                  <form method="post" id="modifier-devoir-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="form-grid">
                      <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="titre">Titre du devoir <span class="required">*</span></label>
                        <input type="text" name="titre" id="titre" class="form-control" required value="<?= htmlspecialchars($devoir['titre']) ?>">
                      </div>
                      
                      <div class="form-group">
                        <label class="form-label" for="classe">Classe <span class="required">*</span></label>
                        <select name="classe" id="classe" class="form-select" required>
                          <option value="">Sélectionnez une classe</option>
                          <?php if (!empty($etablissement_data['classes'])): ?>
                            <?php foreach ($etablissement_data['classes'] as $niveau => $niveaux): ?>
                              <optgroup label="<?= ucfirst($niveau) ?>">
                                <?php foreach ($niveaux as $sousniveau => $classes): ?>
                                  <?php foreach ($classes as $classe): ?>
                                    <option value="<?= $classe ?>" <?= ($devoir['classe'] == $classe) ? 'selected' : '' ?>><?= $classe ?></option>
                                  <?php endforeach; ?>
                                <?php endforeach; ?>
                              </optgroup>
                            <?php endforeach; ?>
                          <?php endif; ?>
                          
                          <?php if (!empty($etablissement_data['primaire'])): ?>
                            <optgroup label="Primaire">
                              <?php foreach ($etablissement_data['primaire'] as $niveau => $classes): ?>
                                <?php foreach ($classes as $classe): ?>
                                  <option value="<?= $classe ?>" <?= ($devoir['classe'] == $classe) ? 'selected' : '' ?>><?= $classe ?></option>
                                <?php endforeach; ?>
                              <?php endforeach; ?>
                            </optgroup>
                          <?php endif; ?>
                        </select>
                      </div>
                      
                      <div class="form-group">
                        <label class="form-label" for="nom_matiere">Matière <span class="required">*</span></label>
                        <select name="nom_matiere" id="nom_matiere" class="form-select" required>
                          <option value="">Sélectionnez une matière</option>
                          <?php if (!empty($etablissement_data['matieres'])): ?>
                            <?php foreach ($etablissement_data['matieres'] as $matiere): ?>
                              <option value="<?= $matiere['nom'] ?>" <?= ($devoir['nom_matiere'] == $matiere['nom']) ? 'selected' : '' ?>><?= $matiere['nom'] ?> (<?= $matiere['code'] ?>)</option>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </select>
                      </div>
                      
                      <div class="form-group">
                        <label class="form-label" for="nom_professeur">Professeur <span class="required">*</span></label>
                        <?php if (isTeacher() && !isAdmin() && !isVieScolaire()): ?>
                          <div class="selected-user-display"><?= htmlspecialchars($devoir['nom_professeur']) ?></div>
                          <input type="hidden" name="nom_professeur" id="nom_professeur" value="<?= htmlspecialchars($devoir['nom_professeur']) ?>">
                        <?php else: ?>
                          <select name="nom_professeur" id="nom_professeur" class="form-select" required>
                            <option value="">Sélectionnez un professeur</option>
                            <?php foreach ($professeurs as $prof): ?>
                              <option value="<?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?>" 
                                      data-matiere="<?= htmlspecialchars($prof['matiere']) ?>" 
                                      <?= ($devoir['nom_professeur'] == $prof['prenom'] . ' ' . $prof['nom']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        <?php endif; ?>
                      </div>
                      
                      <div class="form-group">
                        <label class="form-label" for="date_ajout">Date d'ajout <span class="required">*</span></label>
                        <input type="date" name="date_ajout" id="date_ajout" class="form-control" required value="<?= htmlspecialchars($devoir['date_ajout']) ?>">
                      </div>
                      
                      <div class="form-group">
                        <label class="form-label" for="date_rendu">Date de rendu <span class="required">*</span></label>
                        <input type="date" name="date_rendu" id="date_rendu" class="form-control" required value="<?= htmlspecialchars($devoir['date_rendu']) ?>">
                        <div class="text-muted" id="jours-restants" style="margin-top: 5px;"></div>
                      </div>
                      
                      <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="description">Description <span class="required">*</span></label>
                        <textarea name="description" id="description" class="form-control" rows="6" required><?= htmlspecialchars($devoir['description']) ?></textarea>
                      </div>
                    </div>
                    
                    <div class="form-actions">
                      <a href="cahierdetextes.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                      </a>
                      <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                      </button>
                    </div>
                  </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <div class="footer-content">
                    <div class="footer-links">
                        <a href="#">Mentions Légales</a>
                    </div>
                    <div class="footer-copyright">
                        &copy; <?= date('Y') ?> FRONOTE - Tous droits réservés
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Calculer et afficher les jours restants jusqu'à la date de rendu
function updateJoursRestants() {
  const dateRendu = new Date(document.getElementById('date_rendu').value);
  const aujourdhui = new Date();
  
  // Vérifier que la date est valide
  if (isNaN(dateRendu.getTime())) {
    document.getElementById('jours-restants').textContent = '';
    return;
  }
  
  // Calculer la différence en jours
  const diffTime = dateRendu - aujourdhui;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  // Afficher le résultat
  if (diffDays < 0) {
    document.getElementById('jours-restants').textContent = 'Expiré (depuis ' + Math.abs(diffDays) + ' jours)';
    document.getElementById('jours-restants').style.color = 'var(--expired-color)';
  } else if (diffDays === 0) {
    document.getElementById('jours-restants').textContent = 'À rendre aujourd\'hui !';
    document.getElementById('jours-restants').style.color = 'var(--urgent-color)';
  } else if (diffDays === 1) {
    document.getElementById('jours-restants').textContent = 'À rendre demain';
    document.getElementById('jours-restants').style.color = 'var(--urgent-color)';
  } else {
    document.getElementById('jours-restants').textContent = `À rendre dans ${diffDays} jours`;
    
    // Changer la couleur en fonction du nombre de jours
    if (diffDays <= 3) {
      document.getElementById('jours-restants').style.color = 'var(--urgent-color)';
    } else if (diffDays <= 7) {
      document.getElementById('jours-restants').style.color = 'var(--deadline-soon, #ff9500)';
    } else {
      document.getElementById('jours-restants').style.color = 'var(--module-color)';
    }
  }
}

// Vérifier la relation entre date d'ajout et date de rendu
function validateDates() {
  const dateAjout = new Date(document.getElementById('date_ajout').value);
  const dateRendu = new Date(document.getElementById('date_rendu').value);
  
  // Vérifier que les dates sont valides
  if (isNaN(dateAjout.getTime()) || isNaN(dateRendu.getTime())) {
    return;
  }
  
  // Vérifier que la date de rendu est après la date d'ajout
  if (dateRendu <= dateAjout) {
    document.getElementById('jours-restants').textContent = 'La date de rendu doit être postérieure à la date d\'ajout';
    document.getElementById('jours-restants').style.color = 'var(--urgent-color)';
  } else {
    updateJoursRestants();
  }
}

// Ajouter les écouteurs d'événements
document.addEventListener('DOMContentLoaded', function() {
  // Afficher les jours restants lors du chargement initial
  updateJoursRestants();
  
  // Mettre à jour lorsque la date de rendu change
  document.getElementById('date_rendu').addEventListener('change', validateDates);
  
  // Mettre à jour lorsque la date d'ajout change
  document.getElementById('date_ajout').addEventListener('change', validateDates);
  
  // Validation du formulaire avant soumission
  document.getElementById('modifier-devoir-form').addEventListener('submit', function(e) {
    const dateAjout = new Date(document.getElementById('date_ajout').value);
    const dateRendu = new Date(document.getElementById('date_rendu').value);
    
    if (dateRendu <= dateAjout) {
      e.preventDefault();
      alert("La date de rendu doit être ultérieure à la date d'ajout.");
    }
  });
  
  <?php if (!isTeacher() || isAdmin() || isVieScolaire()): ?>
  // Synchroniser la matière avec le professeur sélectionné
  document.getElementById('nom_professeur').addEventListener('change', function() {
    if (this.selectedIndex > 0) {
      const matiereProf = this.options[this.selectedIndex].getAttribute('data-matiere');
      const selectMatiere = document.getElementById('nom_matiere');
      
      for (let i = 0; i < selectMatiere.options.length; i++) {
        if (selectMatiere.options[i].value === matiereProf) {
          selectMatiere.selectedIndex = i;
          break;
        }
      }
    }
  });
  
  // Filtrer les professeurs en fonction de la matière sélectionnée
  document.getElementById('nom_matiere').addEventListener('change', function() {
    const matiereSelectionnee = this.value;
    const selectProf = document.getElementById('nom_professeur');
    const options = selectProf.options;
    const selectedProfOption = selectProf.options[selectProf.selectedIndex];
    const selectedProfMatiere = selectedProfOption ? selectedProfOption.getAttribute('data-matiere') : null;
    
    // Afficher/cacher les options en fonction de la matière
    for (let i = 1; i < options.length; i++) {
      const matiereProf = options[i].getAttribute('data-matiere');
      if (matiereSelectionnee === '' || matiereProf === matiereSelectionnee) {
        options[i].style.display = '';
      } else {
        options[i].style.display = 'none';
        // Si le prof actuellement sélectionné est caché, on réinitialise la sélection
        if (options[i].selected && matiereProf !== matiereSelectionnee) {
          selectProf.selectedIndex = 0;
        }
      }
    }
  });
  <?php endif; ?>
  
  // Fermer automatiquement les alertes après 5 secondes
  document.querySelectorAll('.alert-banner').forEach(function(alert) {
    setTimeout(function() {
      alert.style.opacity = '0';
      setTimeout(function() {
        alert.style.display = 'none';
      }, 300);
    }, 5000);
  });
  
  document.querySelectorAll('.alert-close').forEach(function(button) {
    button.addEventListener('click', function() {
      const alert = this.parentElement;
      alert.style.opacity = '0';
      setTimeout(function() {
        alert.style.display = 'none';
      }, 300);
    });
  });
});
</script>

<?php
include 'includes/footer.php';
?>