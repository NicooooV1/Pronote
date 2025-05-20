<?php
// Démarrer la mise en mémoire tampon de sortie
ob_start();

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../API/auth_central.php';
require_once __DIR__ . '/includes/db.php';

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Vérifier que l'ID est fourni et valide
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $_SESSION['error_message'] = "Identifiant d'événement invalide";
    header('Location: agenda.php');
    exit;
}

// Initialiser les variables
$message = '';
$erreur = '';
$evenement = [];

// Récupérer l'événement
try {
    $stmt = $pdo->prepare('SELECT * FROM evenements WHERE id = ?');
    $stmt->execute([$id]);
    $evenement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evenement) {
        $_SESSION['error_message'] = "L'événement demandé n'existe pas";
        header('Location: agenda.php');
        exit;
    }
    
    // Vérifier si l'utilisateur a le droit de modifier cet événement
    $can_edit = false;
    
    // Administrateurs et vie scolaire peuvent tout modifier
    if (isAdmin() || isVieScolaire()) {
        $can_edit = true;
    } 
    // Les professeurs ne peuvent modifier que leurs propres événements
    elseif (isTeacher() && $evenement['createur'] === $user_fullname) {
        $can_edit = true;
    }
    
    if (!$can_edit) {
        $_SESSION['error_message'] = "Vous n'avez pas le droit de modifier cet événement";
        header('Location: details_evenement.php?id=' . $id . '&error=unauthorized');
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération de l'événement: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la récupération de l'événement";
    header('Location: agenda.php');
    exit;
}

// Récupérer la liste des classes
$classes = [];
$json_file = __DIR__ . '/../login/data/etablissement.json';
if (file_exists($json_file)) {
    $etablissement_data = json_decode(file_get_contents($json_file), true);
    
    // Extraire les classes
    if (!empty($etablissement_data['classes'])) {
        foreach ($etablissement_data['classes'] as $niveau => $niveaux) {
            foreach ($niveaux as $sousniveau => $classe_array) {
                foreach ($classe_array as $classe) {
                    $classes[] = $classe;
                }
            }
        }
    }
    
    if (!empty($etablissement_data['primaire'])) {
        foreach ($etablissement_data['primaire'] as $niveau => $classe_array) {
            foreach ($classe_array as $classe) {
                $classes[] = $classe;
            }
        }
    }
}

// Récupérer la liste des matières
$matieres = [
    'Français',
    'Mathématiques',
    'Histoire-Géographie',
    'Anglais',
    'Espagnol',
    'Allemand',
    'Physique-Chimie',
    'SVT',
    'Technologie',
    'Arts Plastiques',
    'Musique',
    'EPS',
    'EMC',
    'SNT',
    'NSI',
    'Philosophie',
    'SES',
    'LLCE',
    'Latin',
    'Grec',
    'Autre'
];

// Générer un token CSRF
if (empty($_SESSION['csrf_token_edit'])) {
    try {
        $_SESSION['csrf_token_edit'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token_edit'] = md5(uniqid(mt_rand(), true));
    }
}
$csrf_token = $_SESSION['csrf_token_edit'];

// Formater les dates pour le formulaire
try {
    $date_debut = new DateTime($evenement['date_debut']);
    $date_fin = new DateTime($evenement['date_fin']);
} catch (Exception $e) {
    error_log("Erreur lors du formatage des dates: " . $e->getMessage());
    $date_debut = new DateTime();
    $date_fin = new DateTime('+1 hour');
}

// Traitement du formulaire soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token_edit']) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // Récupérer et valider les données du formulaire
        $titre = trim(filter_input(INPUT_POST, 'titre', FILTER_SANITIZE_SPECIAL_CHARS));
        $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS));
        $type_evenement = filter_input(INPUT_POST, 'type_evenement', FILTER_SANITIZE_SPECIAL_CHARS);
        $lieu = trim(filter_input(INPUT_POST, 'lieu', FILTER_SANITIZE_SPECIAL_CHARS));
        $visibilite = filter_input(INPUT_POST, 'visibilite', FILTER_SANITIZE_SPECIAL_CHARS);
        $statut = filter_input(INPUT_POST, 'statut', FILTER_SANITIZE_SPECIAL_CHARS);
        
        // Dates et heures
        $date_debut_str = filter_input(INPUT_POST, 'date_debut', FILTER_SANITIZE_SPECIAL_CHARS);
        $heure_debut = filter_input(INPUT_POST, 'heure_debut', FILTER_SANITIZE_SPECIAL_CHARS);
        $date_fin_str = filter_input(INPUT_POST, 'date_fin', FILTER_SANITIZE_SPECIAL_CHARS);
        $heure_fin = filter_input(INPUT_POST, 'heure_fin', FILTER_SANITIZE_SPECIAL_CHARS);
        
        // Matière sélectionnée
        $matiere = filter_input(INPUT_POST, 'matieres', FILTER_SANITIZE_SPECIAL_CHARS);
        
        // Classes et personnes concernées
        $classes_selectionnees = isset($_POST['classes']) && is_array($_POST['classes']) ? $_POST['classes'] : [];
        $classes_str = implode(',', array_map('trim', $classes_selectionnees));
        
        $personnes_concernees = isset($_POST['personnes_concernees']) ? trim($_POST['personnes_concernees']) : '';
        
        // Validation
        $errors = [];
        
        // Valider le titre
        if (empty($titre)) {
            $errors[] = "Le titre est obligatoire";
        }
        
        // Valider le type d'événement
        $types_valides = ['cours', 'devoirs', 'reunion', 'examen', 'sortie', 'autre'];
        if (!in_array($type_evenement, $types_valides)) {
            $errors[] = "Type d'événement invalide";
        }
        
        // Valider le statut
        $statuts_valides = ['actif', 'annulé', 'reporté'];
        if (!in_array($statut, $statuts_valides)) {
            $errors[] = "Statut invalide";
        }
        
        // Valider la visibilité
        $visibilites_valides = ['public', 'eleves', 'professeurs', 'administration'];
        if (!in_array($visibilite, $visibilites_valides) && strpos($visibilite, 'classes:') !== 0) {
            $errors[] = "Visibilité invalide";
        }
        
        // Valider et formatter les dates
        try {
            $date_debut_obj = new DateTime($date_debut_str . ' ' . $heure_debut);
            $date_fin_obj = new DateTime($date_fin_str . ' ' . $heure_fin);
            
            if ($date_fin_obj <= $date_debut_obj) {
                $errors[] = "La date de fin doit être postérieure à la date de début";
            }
            
            $date_debut_formatted = $date_debut_obj->format('Y-m-d H:i:s');
            $date_fin_formatted = $date_fin_obj->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $errors[] = "Format de date invalide";
        }
        
        // Si pas d'erreurs, mettre à jour l'événement
        if (empty($errors)) {
            try {
                $sql = "UPDATE evenements SET 
                        titre = ?,
                        description = ?,
                        date_debut = ?,
                        date_fin = ?,
                        type_evenement = ?,
                        statut = ?,
                        lieu = ?,
                        visibilite = ?,
                        classes = ?,
                        matieres = ?,
                        modifie_par = ?,
                        date_modification = NOW()";
                
                $params = [
                    $titre,
                    $description,
                    $date_debut_formatted,
                    $date_fin_formatted,
                    $type_evenement,
                    $statut,
                    $lieu,
                    $visibilite,
                    $classes_str,
                    $matiere,
                    $user_fullname
                ];
                
                // Ajouter personnes_concernees si la colonne existe
                $stmt_check = $pdo->query("SHOW COLUMNS FROM evenements LIKE 'personnes_concernees'");
                if ($stmt_check && $stmt_check->rowCount() > 0) {
                    $sql .= ", personnes_concernees = ?";
                    $params[] = $personnes_concernees;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Rediriger vers la page de détails avec un message de succès
                header('Location: details_evenement.php?id=' . $id . '&updated=1');
                exit;
                
            } catch (PDOException $e) {
                error_log("Erreur lors de la mise à jour de l'événement: " . $e->getMessage());
                $erreur = "Une erreur est survenue lors de l'enregistrement de l'événement";
            }
        } else {
            $erreur = implode("<br>", $errors);
        }
    }
}

// Récupérer les valeurs actuelles pour affichage dans le formulaire
$classes_evenement = !empty($evenement['classes']) ? explode(',', $evenement['classes']) : [];

// Définir le titre de la page
$pageTitle = "Modifier l'événement";

// Inclure l'en-tête
include 'includes/header.php';
?>

<div class="calendar-navigation">
    <a href="details_evenement.php?id=<?= htmlspecialchars($id) ?>" class="back-button">
        <span class="back-icon">
            <i class="fas fa-arrow-left"></i>
        </span>
        Retour aux détails
    </a>
</div>

<div class="event-edit-container">
    <div class="event-edit-header">
        <h1>Modifier l'événement</h1>
    </div>
    
    <div class="event-edit-form">
        <?php if ($message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($erreur): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $erreur ?>
            </div>
        <?php endif; ?>
        
        <form method="post" id="event-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="form-grid">
                <div class="form-group form-full">
                    <label for="titre">
                        <i class="fas fa-heading"></i>
                        Titre de l'événement*
                    </label>
                    <input type="text" name="titre" id="titre" class="form-control" 
                           value="<?= htmlspecialchars($evenement['titre']) ?>" required maxlength="100">
                </div>
                
                <div class="form-group form-full">
                    <label for="description">
                        <i class="fas fa-align-left"></i>
                        Description
                    </label>
                    <textarea name="description" id="description" class="form-control" 
                              maxlength="2000"><?= htmlspecialchars($evenement['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="date_debut">
                        <i class="far fa-calendar"></i>
                        Date de début*
                    </label>
                    <input type="date" name="date_debut" id="date_debut" class="form-control" 
                           value="<?= $date_debut->format('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="heure_debut">
                        <i class="far fa-clock"></i>
                        Heure de début*
                    </label>
                    <input type="time" name="heure_debut" id="heure_debut" class="form-control" 
                           value="<?= $date_debut->format('H:i') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="date_fin">
                        <i class="far fa-calendar"></i>
                        Date de fin*
                    </label>
                    <input type="date" name="date_fin" id="date_fin" class="form-control" 
                           value="<?= $date_fin->format('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="heure_fin">
                        <i class="far fa-clock"></i>
                        Heure de fin*
                    </label>
                    <input type="time" name="heure_fin" id="heure_fin" class="form-control" 
                           value="<?= $date_fin->format('H:i') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="type_evenement">
                        <i class="fas fa-tag"></i>
                        Type d'événement*
                    </label>
                    <select name="type_evenement" id="type_evenement" class="form-control" required>
                        <option value="cours" <?= $evenement['type_evenement'] === 'cours' ? 'selected' : '' ?>>Cours</option>
                        <option value="devoirs" <?= $evenement['type_evenement'] === 'devoirs' ? 'selected' : '' ?>>Devoirs</option>
                        <option value="reunion" <?= $evenement['type_evenement'] === 'reunion' ? 'selected' : '' ?>>Réunion</option>
                        <option value="examen" <?= $evenement['type_evenement'] === 'examen' ? 'selected' : '' ?>>Examen</option>
                        <option value="sortie" <?= $evenement['type_evenement'] === 'sortie' ? 'selected' : '' ?>>Sortie scolaire</option>
                        <option value="autre" <?= $evenement['type_evenement'] === 'autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="statut">
                        <i class="fas fa-traffic-light"></i>
                        Statut
                    </label>
                    <select name="statut" id="statut" class="form-control">
                        <option value="actif" <?= $evenement['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="annulé" <?= $evenement['statut'] === 'annulé' ? 'selected' : '' ?>>Annulé</option>
                        <option value="reporté" <?= $evenement['statut'] === 'reporté' ? 'selected' : '' ?>>Reporté</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="lieu">
                        <i class="fas fa-map-marker-alt"></i>
                        Lieu
                    </label>
                    <input type="text" name="lieu" id="lieu" class="form-control" 
                           value="<?= htmlspecialchars($evenement['lieu'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="matieres">
                        <i class="fas fa-book"></i>
                        Matière
                    </label>
                    <select name="matieres" id="matieres" class="form-control">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($matieres as $matiere): ?>
                            <option value="<?= htmlspecialchars($matiere) ?>" <?= ($evenement['matieres'] == $matiere) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($matiere) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="visibilite">
                        <i class="fas fa-eye"></i>
                        Visibilité*
                    </label>
                    <select name="visibilite" id="visibilite" class="form-control" required>
                        <option value="public" <?= $evenement['visibilite'] === 'public' ? 'selected' : '' ?>>Public (tous)</option>
                        <option value="eleves" <?= $evenement['visibilite'] === 'eleves' ? 'selected' : '' ?>>Élèves uniquement</option>
                        <option value="professeurs" <?= $evenement['visibilite'] === 'professeurs' ? 'selected' : '' ?>>Professeurs uniquement</option>
                        <option value="administration" <?= $evenement['visibilite'] === 'administration' ? 'selected' : '' ?>>Administration uniquement</option>
                        <option value="classes" <?= strpos($evenement['visibilite'], 'classes:') === 0 ? 'selected' : '' ?>>Classes spécifiques</option>
                    </select>
                </div>
                
                <div class="form-group form-full" id="classes-selection" 
                     style="display:<?= strpos($evenement['visibilite'], 'classes:') === 0 ? 'block' : 'none' ?>;">
                    <label>
                        <i class="fas fa-users"></i>
                        Sélectionner les classes
                    </label>
                    <div class="classes-grid">
                        <?php foreach ($classes as $classe): ?>
                            <div class="class-option">
                                <input type="checkbox" name="classes[]" id="class-<?= htmlspecialchars($classe) ?>" 
                                       value="<?= htmlspecialchars($classe) ?>" 
                                       <?= in_array($classe, $classes_evenement) ? 'checked' : '' ?>>
                                <label for="class-<?= htmlspecialchars($classe) ?>"><?= htmlspecialchars($classe) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group form-full">
                    <label for="personnes_concernees">
                        <i class="fas fa-users"></i>
                        Personnes concernées
                        <small>(séparées par des virgules)</small>
                    </label>
                    <textarea name="personnes_concernees" id="personnes_concernees" class="form-control" 
                              placeholder="Jean Dupont, Marie Martin..."><?= htmlspecialchars($evenement['personnes_concernees'] ?? '') ?></textarea>
                </div>
                
                <div class="form-actions form-full">
                    <a href="details_evenement.php?id=<?= htmlspecialchars($id) ?>" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Afficher/masquer le sélecteur de classes en fonction de la visibilité sélectionnée
    document.getElementById('visibilite').addEventListener('change', function() {
        const classesSelection = document.getElementById('classes-selection');
        if (this.value === 'classes') {
            classesSelection.style.display = 'block';
        } else {
            classesSelection.style.display = 'none';
        }
    });
    
    // Synchroniser les dates de début et de fin
    document.getElementById('date_debut').addEventListener('change', function() {
        const dateFin = document.getElementById('date_fin');
        // Si la date de fin est antérieure à la date de début, la mettre à jour
        if (dateFin.value < this.value) {
            dateFin.value = this.value;
        }
    });
    
    // Vérifier la cohérence des dates lors de la soumission du formulaire
    document.getElementById('event-form').addEventListener('submit', function(e) {
        const dateDebut = document.getElementById('date_debut').value;
        const heureDebut = document.getElementById('heure_debut').value;
        const dateFin = document.getElementById('date_fin').value;
        const heureFin = document.getElementById('heure_fin').value;
        
        const debutDateTime = new Date(`${dateDebut}T${heureDebut}`);
        const finDateTime = new Date(`${dateFin}T${heureFin}`);
        
        if (finDateTime <= debutDateTime) {
            e.preventDefault();
            alert('La date et heure de fin doivent être postérieures à la date et heure de début.');
        }
        
        // Gestion spéciale pour la visibilité "classes"
        const visibilite = document.getElementById('visibilite');
        if (visibilite.value === 'classes') {
            const classesCochees = document.querySelectorAll('input[name="classes[]"]:checked');
            if (classesCochees.length === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins une classe.');
            } else {
                // Créer un champ caché pour envoyer la liste des classes dans le format attendu
                const classesValues = Array.from(classesCochees).map(cb => cb.value).join(',');
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = 'visibilite';
                hiddenField.value = 'classes:' + classesValues;
                this.appendChild(hiddenField);
                // Supprimer le champ select original du formulaire soumis
                visibilite.disabled = true;
            }
        }
    });
});
</script>

<?php
// Inclusion du pied de page
include 'includes/footer.php';

// Terminer la mise en mémoire tampon et envoyer la sortie
ob_end_flush();
?>