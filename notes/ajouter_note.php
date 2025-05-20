<?php
// Démarrer la mise en mémoire tampon de sortie pour éviter l'erreur "headers already sent"
ob_start();

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est professeur ou administrateur
$user = $_SESSION['user'] ?? null;
$user_role = $user['profil'] ?? '';
if (!in_array($user_role, ['professeur', 'administrateur'])) {
    header('Location: notes.php');
    exit;
}

// Récupération des informations utilisateur
$user_nom = $user['nom'] ?? '';
$user_prenom = $user['prenom'] ?? '';
$user_initials = strtoupper(mb_substr($user_prenom, 0, 1) . mb_substr($user_nom, 0, 1));

// Fonction pour calculer le trimestre actuel
function getTrimestre() {
    $mois = date('n');
    if ($mois >= 9 && $mois <= 12) {
        return "1er trimestre";
    } elseif ($mois >= 1 && $mois <= 3) {
        return "2ème trimestre";
    } elseif ($mois >= 4 && $mois <= 6) {
        return "3ème trimestre";
    } else {
        return "Période estivale";
    }
}

// Récupérer la date du jour et le trimestre
$aujourdhui = date('d/m/Y');
$trimestre = getTrimestre();
$jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$jour = $jours[date('w')];

// Récupérer le nom de l'établissement depuis le fichier JSON
$json_file = __DIR__ . '/../login/data/etablissement.json';
$etablissement_data = [];
if (file_exists($json_file)) {
    $etablissement_data = json_decode(file_get_contents($json_file), true);
}
$nom_etablissement = $etablissement_data['nom'] ?? 'Établissement Scolaire';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une note - Pronote</title>
    <link rel="stylesheet" href="../assets/css/pronote-core.css">
    <link rel="stylesheet" href="assets/css/notes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Barre latérale -->
        <div class="sidebar">
            <a href="../accueil/accueil.php" class="logo-container">
                <div class="app-logo">P</div>
                <div class="app-title">PRONOTE</div>
            </a>
            
            <div class="sidebar-section">
                <div class="sidebar-section-header">Navigation</div>
                <div class="sidebar-nav">
                    <a href="../accueil/accueil.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                        <span>Accueil</span>
                    </a>
                    <a href="notes.php" class="sidebar-nav-item active">
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
                    <?php if ($user_role === 'vie_scolaire' || $user_role === 'administrateur'): ?>
                    <a href="../absences/absences.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                        <span>Absences</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-header">Informations</div>
                <div class="info-item">
                    <div class="info-label">Établissement</div>
                    <div class="info-value"><?= htmlspecialchars($nom_etablissement) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date</div>
                    <div class="info-value"><?= $jour . ' ' . $aujourdhui ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Période</div>
                    <div class="info-value"><?= $trimestre ?></div>
                </div>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="main-content">
            <!-- En-tête -->
            <div class="top-header">
                <div class="page-title">
                    <h1>Ajouter une note</h1>
                    <div class="subtitle">Entrez les informations de la note</div>
                </div>
                
                <div class="header-actions">
                    <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                    <div class="user-avatar"><?= $user_initials ?></div>
                </div>
            </div>
            
            <!-- Formulaire d'ajout de note -->
            <div class="content-container">
                <form class="form-container">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="classe">Classe <span class="required">*</span></label>
                            <select id="classe" name="classe" class="form-control" required>
                                <option value="">Sélectionnez une classe</option>
                                <option value="6A">6ème A</option>
                                <option value="6B">6ème B</option>
                                <option value="5A">5ème A</option>
                                <option value="5B">5ème B</option>
                                <option value="4A">4ème A</option>
                                <option value="4B">4ème B</option>
                                <option value="3A">3ème A</option>
                                <option value="3B">3ème B</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="matiere">Matière <span class="required">*</span></label>
                            <select id="matiere" name="matiere" class="form-control" required>
                                <option value="">Sélectionnez une matière</option>
                                <option value="francais">Français</option>
                                <option value="mathematiques">Mathématiques</option>
                                <option value="histoire-geo">Histoire-Géographie</option>
                                <option value="anglais">Anglais</option>
                                <option value="espagnol">Espagnol</option>
                                <option value="allemand">Allemand</option>
                                <option value="physique-chimie">Physique-Chimie</option>
                                <option value="svt">SVT</option>
                                <option value="technologie">Technologie</option>
                                <option value="arts">Arts Plastiques</option>
                                <option value="musique">Musique</option>
                                <option value="eps">EPS</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_evaluation">Date de l'évaluation <span class="required">*</span></label>
                            <input type="date" id="date_evaluation" name="date_evaluation" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="trimestre">Trimestre <span class="required">*</span></label>
                            <select id="trimestre" name="trimestre" class="form-control" required>
                                <option value="1">1er trimestre</option>
                                <option value="2">2ème trimestre</option>
                                <option value="3">3ème trimestre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="coefficient">Coefficient</label>
                            <input type="number" id="coefficient" name="coefficient" min="0.5" max="5" step="0.5" value="1" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="note_sur">Note sur</label>
                            <input type="number" id="note_sur" name="note_sur" min="10" max="100" step="1" value="20" class="form-control">
                        </div>
                        
                        <div class="form-group form-full">
                            <label for="description">Description de l'évaluation <span class="required">*</span></label>
                            <input type="text" id="description" name="description" class="form-control" placeholder="Ex: Contrôle sur les fractions" required>
                        </div>
                    </div>
                    
                    <!-- Tableau d'élèves à noter -->
                    <div class="students-table-container">
                        <h3>Attribuer les notes aux élèves</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>Note</th>
                                    <th>Absence</th>
                                    <th>Commentaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Dupont Jean</td>
                                    <td><input type="number" name="notes[1]" min="0" max="20" step="0.25" class="form-control"></td>
                                    <td>
                                        <select name="absence[1]" class="form-control">
                                            <option value="">Non</option>
                                            <option value="abs">Absent</option>
                                            <option value="disp">Dispensé</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="commentaire[1]" class="form-control"></td>
                                </tr>
                                <tr>
                                    <td>Martin Sophie</td>
                                    <td><input type="number" name="notes[2]" min="0" max="20" step="0.25" class="form-control"></td>
                                    <td>
                                        <select name="absence[2]" class="form-control">
                                            <option value="">Non</option>
                                            <option value="abs">Absent</option>
                                            <option value="disp">Dispensé</option>
                                        </select>
                                    </td>
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                    <span>Absences</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">Actions</div>
            <div class="sidebar-nav">
                <a href="notes.php" class="create-button">
                    <i class="fas fa-arrow-left"></i> Retour aux notes
                </a>
            </div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">Informations</div>
            <div class="info-item">
                <div class="info-label">Date</div>
                <div class="info-value"><?= date('d/m/Y') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Période</div>
                <div class="info-value"><?= $trimestre_actuel ?>ème trimestre</div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="top-header">
            <div class="page-title">
                <h1>Ajouter une note</h1>
            </div>
            
            <div class="header-actions">
                <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                <div class="user-avatar" title="<?= htmlspecialchars($nom_professeur) ?>"><?= $user_initials ?></div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2>Ajouter une note</h2>
                <p>Saisissez les informations pour ajouter une nouvelle note</p>
            </div>
            <div class="welcome-logo">
                <i class="fas fa-plus-circle"></i>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="content-container">
            <?php if ($error_message): ?>
                <div class="alert-banner alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="post">
                    <div class="form-grid">
                        <!-- Champ pour la classe -->
                        <div class="form-group">
                            <label for="classe" class="form-label">Classe<span class="required">*</span></label>
                            <select name="classe" id="classe" class="form-select" required>
                                <option value="">Sélectionnez une classe</option>
                                <?php if (!empty($etablissement_data['classes'])): ?>
                                    <?php foreach ($etablissement_data['classes'] as $niveau => $niveaux): ?>
                                        <optgroup label="<?= ucfirst($niveau) ?>">
                                            <?php foreach ($niveaux as $sousniveau => $classes): ?>
                                                <?php foreach ($classes as $classe): ?>
                                                    <option value="<?= htmlspecialchars($classe) ?>"><?= htmlspecialchars($classe) ?></option>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Champ pour l'élève -->
                        <div class="form-group">
                            <label for="nom_eleve" class="form-label">Élève<span class="required">*</span></label>
                            <select name="nom_eleve" id="nom_eleve" class="form-select" required>
                                <option value="">Sélectionnez un élève</option>
                                <?php foreach ($eleves as $eleve): ?>
                                    <option value="<?= htmlspecialchars($eleve['prenom']) ?>" data-classe="<?= htmlspecialchars($eleve['classe']) ?>">
                                        <?= htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']) ?> (<?= htmlspecialchars($eleve['classe']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Champ pour la matière -->
                        <div class="form-group">
                            <label for="nom_matiere" class="form-label">Matière<span class="required">*</span></label>
                            <?php if (isTeacher()): ?>
                                <!-- Si c'est un professeur, on présélectionne sa matière -->
                                <select name="nom_matiere" id="nom_matiere" class="form-select" required>
                                    <option value="">Sélectionnez une matière</option>
                                    <?php if (!empty($etablissement_data['matieres'])): ?>
                                        <?php foreach ($etablissement_data['matieres'] as $matiere): ?>
                                            <option value="<?= htmlspecialchars($matiere['nom']) ?>" <?= ($prof_matiere == $matiere['nom']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($matiere['nom']) ?> (<?= htmlspecialchars($matiere['code']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            <?php else: ?>
                                <!-- Pour admin/vie scolaire -->
                                <select name="nom_matiere" id="nom_matiere" class="form-select" required>
                                    <option value="">Sélectionnez une matière</option>
                                    <?php if (!empty($etablissement_data['matieres'])): ?>
                                        <?php foreach ($etablissement_data['matieres'] as $matiere): ?>
                                            <option value="<?= htmlspecialchars($matiere['nom']) ?>">
                                                <?= htmlspecialchars($matiere['nom']) ?> (<?= htmlspecialchars($matiere['code']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Champ pour le professeur -->
                        <div class="form-group">
                            <label for="nom_professeur" class="form-label">Professeur<span class="required">*</span></label>
                            <?php if (isTeacher()): ?>
                                <!-- Si c'est un professeur, il ne peut ajouter que des notes en son nom -->
                                <input type="text" name="nom_professeur" id="nom_professeur" class="form-control" value="<?= htmlspecialchars($nom_professeur) ?>" readonly>
                            <?php else: ?>
                                <!-- Admin et vie scolaire peuvent choisir n'importe quel professeur -->
                                <select name="nom_professeur" id="nom_professeur" class="form-select" required>
                                    <option value="">Sélectionnez un professeur</option>
                                    <?php foreach ($professeurs as $prof): ?>
                                        <option value="<?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?>" data-matiere="<?= htmlspecialchars($prof['matiere']) ?>">
                                            <?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Champ pour la note -->
                        <div class="form-group">
                            <label for="note" class="form-label">Note<span class="required">*</span></label>
                            <input type="number" name="note" id="note" class="form-control" max="20" min="0" step="0.1" placeholder="Note sur 20" required>
                        </div>
                        
                        <!-- Champ pour le coefficient -->
                        <div class="form-group">
                            <label for="coefficient" class="form-label">Coefficient<span class="required">*</span></label>
                            <input type="number" name="coefficient" id="coefficient" class="form-control" min="1" max="10" step="1" value="1" required>
                        </div>
                        
                        <!-- Champ pour la date -->
                        <div class="form-group">
                            <label for="date_ajout" class="form-label">Date<span class="required">*</span></label>
                            <input type="date" name="date_ajout" id="date_ajout" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <!-- Champ pour le trimestre -->
                        <div class="form-group">
                            <label for="trimestre" class="form-label">Trimestre<span class="required">*</span></label>
                            <select name="trimestre" id="trimestre" class="form-select" required>
                                <option value="1" <?= $trimestre_actuel == 1 ? 'selected' : '' ?>>Trimestre 1</option>
                                <option value="2" <?= $trimestre_actuel == 2 ? 'selected' : '' ?>>Trimestre 2</option>
                                <option value="3" <?= $trimestre_actuel == 3 ? 'selected' : '' ?>>Trimestre 3</option>
                            </select>
                        </div>
                    </div>
                        
                    <!-- Champ pour la description (intitulé) -->
                    <div class="form-group mt-3">
                        <label for="description" class="form-label">Intitulé de l'évaluation<span class="required">*</span></label>
                        <input type="text" name="description" id="description" class="form-control" placeholder="Ex: Contrôle sur les équations" required>
                    </div>
                    
                    <div class="form-actions">
                        <a href="notes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Ajouter la note
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
                    &copy; <?= date('Y') ?> PRONOTE - Tous droits réservés
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Navigation mobile
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const pageOverlay = document.getElementById('page-overlay');
    
    if (mobileMenuToggle && sidebar && pageOverlay) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-visible');
            pageOverlay.classList.toggle('visible');
            document.body.classList.toggle('menu-open');
        });
        
        pageOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-visible');
            pageOverlay.classList.remove('visible');
            document.body.classList.remove('menu-open');
        });
    }
});

// Script pour filtrer les élèves en fonction de la classe sélectionnée
document.getElementById('classe').addEventListener('change', function() {
    const classeSelectionnee = this.value;
    const selectEleve = document.getElementById('nom_eleve');
    const options = selectEleve.options;
    
    // Réinitialiser le sélecteur d'élève
    selectEleve.selectedIndex = 0;
    
    // Afficher/cacher les options en fonction de la classe
    for (let i = 1; i < options.length; i++) {
      const classeEleve = options[i].getAttribute('data-classe');
      if (classeSelectionnee === '' || classeEleve === classeSelectionnee) {
        options[i].style.display = '';
      } else {
        options[i].style.display = 'none';
      }
    }
});

// Script pour définir automatiquement la classe lorsqu'un élève est sélectionné
document.getElementById('nom_eleve').addEventListener('change', function() {
    if (this.selectedIndex > 0) {
      const classeEleve = this.options[this.selectedIndex].getAttribute('data-classe');
      const selectClasse = document.getElementById('classe');
      
      // Parcourir toutes les options pour trouver la classe correspondante
      for (let i = 0; i < selectClasse.options.length; i++) {
        if (selectClasse.options[i].value === classeEleve) {
          selectClasse.selectedIndex = i;
          break;
        }
      }
    }
});

<?php if (!isTeacher()): ?>
// Filtrer les professeurs en fonction de la matière sélectionnée
document.getElementById('nom_matiere').addEventListener('change', function() {
    const matiereSelectionnee = this.value;
    const selectProf = document.getElementById('nom_professeur');
    const options = selectProf.options;
    
    // Réinitialiser le sélecteur de professeur
    selectProf.selectedIndex = 0;
    
    // Afficher/cacher les options en fonction de la matière
    for (let i = 1; i < options.length; i++) {
      const matiereProf = options[i].getAttribute('data-matiere');
      if (matiereSelectionnee === '' || matiereProf === matiereSelectionnee) {
        options[i].style.display = '';
      } else {
        options[i].style.display = 'none';
      }
    }
});

// Si un administrateur ou vie scolaire sélectionne un professeur, 
// sélectionner automatiquement sa matière
document.getElementById('nom_professeur').addEventListener('change', function() {
    if (this.selectedIndex > 0) {
      const matiereProf = this.options[this.selectedIndex].getAttribute('data-matiere');
      const selectMatiere = document.getElementById('nom_matiere');
      
      // Parcourir toutes les options pour trouver la matière correspondante
      for (let i = 0; i < selectMatiere.options.length; i++) {
        if (selectMatiere.options[i].value === matiereProf) {
          selectMatiere.selectedIndex = i;
          break;
        }
      }
    }
});
<?php endif; ?>
</script>

</body>
</html>

<?php
// Terminer la mise en mémoire tampon et envoyer la sortie
ob_end_flush();
?>