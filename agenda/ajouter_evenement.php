<?php
// Démarrer la mise en mémoire tampon de sortie pour éviter l'erreur "headers already sent"
ob_start();

// Définir l'environnement si non défini
if (!defined('APP_ENV')) {
    define('APP_ENV', isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : 'production');
}

// Configuration de la gestion des erreurs selon l'environnement
if (APP_ENV !== 'development') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Inclusion de l'API centralisée
require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/auth.php';

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    // Journaliser la tentative d'accès non autorisée
    error_log("Tentative d'accès non autorisée à ajouter_evenement.php - Utilisateur non connecté");
    header('Location: ' . LOGIN_URL);
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Date par défaut (aujourd'hui ou la date passée en paramètre)
$date_par_defaut = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-d');
$heure_debut_defaut = date('H:i');
$heure_fin_defaut = date('H:i', strtotime('+1 hour'));

// Génération d'un token CSRF pour protéger le formulaire
try {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
    $_SESSION['csrf_token_time'] = time(); // Ajouter un timestamp pour l'expiration
} catch (Exception $e) {
    // Fallback si random_bytes échoue
    $csrf_token = hash('sha256', uniqid(mt_rand(), true));
    $_SESSION['csrf_token'] = $csrf_token;
    $_SESSION['csrf_token_time'] = time();
}

// Déterminer les types d'événements disponibles selon le rôle de l'utilisateur
$types_evenements = [];

if (isAdmin() || isVieScolaire()) {
    // Administrateurs et vie scolaire ont accès à tous les types
    $types_evenements = [
        'cours' => ['nom' => 'Cours', 'icone' => 'book', 'couleur' => '#00843d'],
        'devoirs' => ['nom' => 'Devoirs', 'icone' => 'pencil', 'couleur' => '#4285f4'],
        'reunion' => ['nom' => 'Réunion', 'icone' => 'users', 'couleur' => '#ff9800'],
        'examen' => ['nom' => 'Examen', 'icone' => 'file-text', 'couleur' => '#f44336'],
        'sortie' => ['nom' => 'Sortie scolaire', 'icone' => 'map-pin', 'couleur' => '#00c853'],
        'autre' => ['nom' => 'Autre', 'icone' => 'calendar', 'couleur' => '#9e9e9e']
    ];
} elseif (isTeacher()) {
    // Les professeurs peuvent créer des cours, devoirs et examens
    $types_evenements = [
        'cours' => ['nom' => 'Cours', 'icone' => 'book', 'couleur' => '#00843d'],
        'devoirs' => ['nom' => 'Devoirs', 'icone' => 'pencil', 'couleur' => '#4285f4'],
        'examen' => ['nom' => 'Examen', 'icone' => 'file-text', 'couleur' => '#f44336'],
        'autre' => ['nom' => 'Autre', 'icone' => 'calendar', 'couleur' => '#9e9e9e']
    ];
} else {
    // Élèves et parents ne peuvent créer que des événements personnels
    $types_evenements = [
        'autre' => ['nom' => 'Événement personnel', 'icone' => 'calendar', 'couleur' => '#9e9e9e']
    ];
}

// Options de visibilité selon le rôle
$options_visibilite = [];

if (isAdmin() || isVieScolaire()) {
    $options_visibilite = [
        'public' => ['nom' => 'Public (visible par tous)', 'icone' => 'globe'],
        'professeurs' => ['nom' => 'Professeurs uniquement', 'icone' => 'user-tie'],
        'eleves' => ['nom' => 'Élèves uniquement', 'icone' => 'user-graduate'],
        'administration' => ['nom' => 'Administration uniquement', 'icone' => 'user-shield'],
        'classes_specifiques' => ['nom' => 'Classes spécifiques', 'icone' => 'users'],
        'personnel' => ['nom' => 'Personnel (visible uniquement par moi)', 'icone' => 'user-lock']
    ];
} elseif (isTeacher()) {
    $options_visibilite = [
        'public' => ['nom' => 'Public (visible par tous)', 'icone' => 'globe'],
        'professeurs' => ['nom' => 'Professeurs uniquement', 'icone' => 'user-tie'],
        'eleves' => ['nom' => 'Élèves uniquement', 'icone' => 'user-graduate'],
        'classes_specifiques' => ['nom' => 'Classes spécifiques', 'icone' => 'users'],
        'personnel' => ['nom' => 'Personnel (visible uniquement par moi)', 'icone' => 'user-lock']
    ];
} else {
    // Élèves et parents - seulement personnel
    $options_visibilite = [
        'personnel' => ['nom' => 'Personnel (visible uniquement par moi)', 'icone' => 'user-lock']
    ];
}

// Récupérer la liste des classes de façon sécurisée
$classes = [];
$json_file = __DIR__ . '/../login/data/etablissement.json';
try {
    if (file_exists($json_file) && is_readable($json_file)) {
        $json_content = file_get_contents($json_file);
        if ($json_content === false) {
            throw new Exception("Impossible de lire le fichier etablissement.json");
        }
        
        $etablissement_data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erreur de parsing JSON dans etablissement.json: " . json_last_error_msg());
        }
        
        // Extraire les classes du secondaire
        if (!empty($etablissement_data['classes'])) {
            foreach ($etablissement_data['classes'] as $niveau => $niveaux) {
                foreach ($niveaux as $sousniveau => $classe_array) {
                    foreach ($classe_array as $classe) {
                        $classes[] = $classe;
                    }
                }
            }
        }
        
        // Extraire les classes du primaire
        if (!empty($etablissement_data['primaire'])) {
            foreach ($etablissement_data['primaire'] as $niveau => $classe_array) {
                foreach ($classe_array as $classe) {
                    $classes[] = $classe;
                }
            }
        }
    }
} catch (Exception $e) {
    // Journaliser l'erreur mais continuer avec une liste vide
    error_log("Erreur lors du chargement des classes: " . $e->getMessage());
}

// Récupérer la liste des personnels (professeurs, administration, vie scolaire)
$personnels = [];
try {
    // Récupérer les professeurs - Assurons-nous que la table existe avant de faire la requête
    $tableExists = false;
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'professeurs'");
    $tableExists = $tableCheck && $tableCheck->rowCount() > 0;
    
    if ($tableExists) {
        $stmt_profs = $pdo->prepare("SELECT id, nom, prenom, matiere FROM professeurs ORDER BY nom, prenom");
        $stmt_profs->execute();
        $professeurs = $stmt_profs->fetchAll(PDO::FETCH_ASSOC);
        foreach ($professeurs as $personne) {
            $personnels[] = [
                'id' => $personne['id'],
                'nom' => $personne['nom'],
                'prenom' => $personne['prenom'],
                'type' => 'Professeur',
                'matiere' => $personne['matiere'] ?? ''
            ];
        }
    }
} catch (PDOException $e) {
    // Journaliser l'erreur mais continuer avec une liste vide
    error_log("Erreur lors de la récupération des personnels: " . $e->getMessage());
}

// Si c'est un professeur, récupérer sa matière
$prof_matiere = '';
try {
    if (isTeacher() && isset($user['nom']) && isset($user['prenom'])) {
        $stmt_prof = $pdo->prepare('SELECT matiere FROM professeurs WHERE nom = ? AND prenom = ?');
        $stmt_prof->execute([$user['nom'], $user['prenom']]);
        $prof_data = $stmt_prof->fetch(PDO::FETCH_ASSOC);
        $prof_matiere = $prof_data ? $prof_data['matiere'] : '';
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération de la matière du professeur: " . $e->getMessage());
}

// Traitement du formulaire
$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF et sa validité temporelle
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 1800) {
        
        $erreur = "Erreur de sécurité: formulaire invalide ou expiré. Veuillez actualiser la page.";
        error_log("Tentative potentielle de CSRF lors de l'ajout d'un événement par {$user_fullname}");
    } else {
        // Validation des champs obligatoires avec filtrage approprié
        $titre = filter_input(INPUT_POST, 'titre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $date_debut = filter_input(INPUT_POST, 'date_debut', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $heure_debut = filter_input(INPUT_POST, 'heure_debut', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $date_fin = filter_input(INPUT_POST, 'date_fin', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $heure_fin = filter_input(INPUT_POST, 'heure_fin', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $type_evenement = filter_input(INPUT_POST, 'type_evenement', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $visibilite = filter_input(INPUT_POST, 'visibilite', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $lieu = filter_input(INPUT_POST, 'lieu', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        
        if (!$titre || !$date_debut || !$heure_debut || !$date_fin || !$heure_fin || !$type_evenement || !$visibilite) {
            $erreur = "Veuillez remplir tous les champs obligatoires.";
        } 
        // Vérifier que le type d'événement est autorisé pour ce rôle
        elseif (!array_key_exists($type_evenement, $types_evenements)) {
            $erreur = "Le type d'événement sélectionné n'est pas autorisé pour votre profil.";
        } 
        else {
            try {
                // Formatage des dates avec validation
                $date_debut_obj = DateTime::createFromFormat('Y-m-d H:i:s', $date_debut . ' ' . $heure_debut . ':00');
                $date_fin_obj = DateTime::createFromFormat('Y-m-d H:i:s', $date_fin . ' ' . $heure_fin . ':00');
                
                if (!$date_debut_obj || !$date_fin_obj) {
                    throw new Exception("Format de date ou d'heure invalide.");
                }
                
                $date_debut_str = $date_debut_obj->format('Y-m-d H:i:s');
                $date_fin_str = $date_fin_obj->format('Y-m-d H:i:s');
                
                // Vérifier que la date de fin est après la date de début
                if ($date_fin_obj <= $date_debut_obj) {
                    throw new Exception("La date/heure de fin doit être après la date/heure de début.");
                }
                
                // Traitement de la visibilité et des classes sélectionnées
                $visibilite_db = $visibilite;
                $classes_selectionnees = '';
                
                if ($visibilite === 'classes_specifiques' && isset($_POST['classes'])) {
                    // S'assurer que $_POST['classes'] est un tableau
                    if (!is_array($_POST['classes'])) {
                        throw new Exception("Format des classes sélectionnées invalide.");
                    }
                    
                    // Filtrer pour ne garder que les classes valides
                    $selected_classes = array_filter($_POST['classes'], function($class) use ($classes) {
                        return in_array($class, $classes);
                    });
                    
                    if (empty($selected_classes)) {
                        throw new Exception("Aucune classe valide n'a été sélectionnée.");
                    }
                    
                    $classes_selectionnees = implode(',', $selected_classes);
                    $visibilite_db = 'classes:' . $classes_selectionnees;
                }
                
                // Traitement des personnes concernées avec validation
                $personnes_concernees = '';
                if (!empty($_POST['personnes_concernees']) && is_array($_POST['personnes_concernees'])) {
                    // Valider chaque personne concernée (format: "type:id")
                    $validated_persons = [];
                    foreach ($_POST['personnes_concernees'] as $person) {
                        // Vérifier le format (type:id)
                        if (preg_match('/^(eleve|professeur|personnel|parent):[a-zA-Z0-9]+$/', $person)) {
                            $validated_persons[] = $person;
                        }
                    }
                    $personnes_concernees = implode(',', $validated_persons);
                }
                
                // Type personnalisé pour les événements de type "autre"
                $type_personnalise = '';
                if ($type_evenement === 'autre' && !empty($_POST['type_personnalise'])) {
                    $type_personnalise = filter_input(INPUT_POST, 'type_personnalise', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                
                // Récupérer la matière (soit depuis le formulaire, soit celle du professeur connecté)
                $matieres = '';
                if (isTeacher() && !empty($prof_matiere)) {
                    // Si c'est un professeur, utiliser sa matière
                    $matieres = $prof_matiere;
                } elseif (isset($_POST['matieres'])) {
                    // Sinon, utiliser la valeur du formulaire
                    $matieres = filter_input(INPUT_POST, 'matieres', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                
                // Insertion dans la base de données avec une requête préparée
                $stmt = $pdo->prepare('INSERT INTO evenements 
                                     (titre, description, date_debut, date_fin, type_evenement, type_personnalise, 
                                     statut, createur, visibilite, personnes_concernees, lieu, classes, matieres, date_creation) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
                
                $result = $stmt->execute([
                    $titre,
                    $description,
                    $date_debut_str,
                    $date_fin_str,
                    $type_evenement,
                    $type_personnalise,
                    'actif', // Statut par défaut
                    $user_fullname,
                    $visibilite_db,
                    $personnes_concernees,
                    $lieu,
                    $classes_selectionnees,
                    $matieres
                ]);
                
                if ($result) {
                    // Récupérer l'ID de l'événement créé
                    $id_evenement = $pdo->lastInsertId();
                    
                    // Journaliser la création
                    error_log("Événement ID=$id_evenement créé par $user_fullname");
                    
                    $message = "L'événement a été ajouté avec succès.";
                    
                    // Régénérer un token CSRF pour éviter la réutilisation
                    try {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } catch (Exception $e) {
                        $_SESSION['csrf_token'] = hash('sha256', uniqid(mt_rand(), true));
                    }
                    $_SESSION['csrf_token_time'] = time();
                    
                    // Rediriger après un court délai
                    header("refresh:2;url=details_evenement.php?id=$id_evenement&created=1");
                } else {
                    throw new Exception("Erreur lors de l'insertion dans la base de données.");
                }
            } catch (Exception $e) {
                $erreur = "Erreur lors de l'ajout de l'événement : " . $e->getMessage();
                error_log("Erreur lors de l'ajout d'un événement par $user_fullname: " . $e->getMessage());
            }
        }
    }
}

// Ajouter un gestionnaire AJAX pour récupérer les personnes selon la visibilité
if (isset($_GET['action']) && $_GET['action'] === 'get_persons' && isset($_GET['visibility'])) {
    header('Content-Type: application/json');
    $visibility = filter_input(INPUT_GET, 'visibility', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $persons = [];
    
    try {
        // Vérifier si l'utilisateur a les droits nécessaires
        if (!isAdmin() && !isTeacher() && !isVieScolaire()) {
            throw new Exception('Accès non autorisé');
        }
        
        switch ($visibility) {
            case 'eleves':
                $stmt = $pdo->prepare("SELECT id, nom, prenom, classe FROM eleves ORDER BY nom, prenom");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $persons[] = [
                        'id' => $row['id'],
                        'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                        'info' => htmlspecialchars($row['classe']),
                        'type' => 'eleve'
                    ];
                }
                break;
                
            case 'professeurs':
                $stmt = $pdo->prepare("SELECT id, nom, prenom, matiere FROM professeurs ORDER BY nom, prenom");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $persons[] = [
                        'id' => $row['id'],
                        'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                        'info' => htmlspecialchars($row['matiere'] ?? 'Professeur'),
                        'type' => 'professeur'
                    ];
                }
                break;
                
            case 'parents':
                $stmt = $pdo->prepare("SELECT p.id, p.nom, p.prenom, 
                                      GROUP_CONCAT(DISTINCT e.prenom SEPARATOR ', ') AS enfants 
                                      FROM parents p 
                                      LEFT JOIN parents_eleves pe ON p.id = pe.id_parent 
                                      LEFT JOIN eleves e ON pe.id_eleve = e.id 
                                      GROUP BY p.id 
                                      ORDER BY p.nom, p.prenom");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $persons[] = [
                        'id' => $row['id'],
                        'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                        'info' => 'Parent de : ' . htmlspecialchars($row['enfants'] ?: 'Non défini'),
                        'type' => 'parent'
                    ];
                }
                break;
                
            case 'vie_scolaire':
                $stmt = $pdo->prepare("SELECT id, nom, prenom, fonction FROM personnels 
                                      WHERE fonction LIKE '%scolaire%' OR service = 'vie scolaire' 
                                      ORDER BY nom, prenom");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $persons[] = [
                        'id' => $row['id'],
                        'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                        'info' => htmlspecialchars($row['fonction'] ?? 'Vie scolaire'),
                        'type' => 'personnel'
                    ];
                }
                break;
                
            case 'administration':
                $stmt = $pdo->prepare("SELECT id, nom, prenom, fonction FROM personnels 
                                      WHERE fonction LIKE '%admin%' OR service = 'administration' 
                                      ORDER BY nom, prenom");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $persons[] = [
                        'id' => $row['id'],
                        'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                        'info' => htmlspecialchars($row['fonction'] ?? 'Administration'),
                        'type' => 'personnel'
                    ];
                }
                break;
                
            // Pour les classes spécifiques (au format 'classes:NomClasse')
            default:
                if (strpos($visibility, 'classes:') === 0) {
                    $classe = substr($visibility, 8); // Récupère ce qui vient après "classes:"
                    $stmt = $pdo->prepare("SELECT id, nom, prenom FROM eleves WHERE classe = ? ORDER BY nom, prenom");
                    $stmt->execute([$classe]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $persons[] = [
                            'id' => $row['id'],
                            'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                            'info' => htmlspecialchars($classe),
                            'type' => 'eleve'
                        ];
                    }
                }
                break;
        }
        
        // Si aucune personne trouvée, générer des exemples seulement en mode développement
        if (empty($persons) && APP_ENV === 'development') {
            switch ($visibility) {
                case 'eleves':
                    $persons = [
                        ['id' => 'e1', 'name' => 'Louis Martin', 'info' => '2nde A', 'type' => 'eleve'],
                        ['id' => 'e2', 'name' => 'Emma Bernard', 'info' => '2nde B', 'type' => 'eleve'],
                        ['id' => 'e3', 'name' => 'Lucas Petit', 'info' => '1ère S', 'type' => 'eleve']
                    ];
                    break;
                case 'professeurs':
                    $persons = [
                        ['id' => 'p1', 'name' => 'Marie Dubois', 'info' => 'Mathématiques', 'type' => 'professeur'],
                        ['id' => 'p2', 'name' => 'Jean Dupont', 'info' => 'Français', 'type' => 'professeur'],
                        ['id' => 'p3', 'name' => 'Sophie Moreau', 'info' => 'Histoire-Géographie', 'type' => 'professeur']
                    ];
                    break;
                case 'parents':
                    $persons = [
                        ['id' => 'pa1', 'name' => 'Philippe Martin', 'info' => 'Parent de : Louis Martin', 'type' => 'parent'],
                        ['id' => 'pa2', 'name' => 'Christine Bernard', 'info' => 'Parent de : Emma Bernard', 'type' => 'parent']
                    ];
                    break;
                case 'vie_scolaire':
                    $persons = [
                        ['id' => 'vs1', 'name' => 'Valérie Lefevre', 'info' => 'CPE', 'type' => 'personnel'],
                        ['id' => 'vs2', 'name' => 'Thomas Roux', 'info' => 'Assistant d\'éducation', 'type' => 'personnel']
                    ];
                    break;
                case 'administration':
                    $persons = [
                        ['id' => 'a1', 'name' => 'Michel Durand', 'info' => 'Proviseur', 'type' => 'personnel'],
                        ['id' => 'a2', 'name' => 'Claire Petit', 'info' => 'Secrétaire', 'type' => 'personnel']
                    ];
                    break;
                default:
                    if (strpos($visibility, 'classes_specifiques') === 0) {
                        $classe = "Classe spécifique";
                        $persons = [
                            ['id' => 'ec1', 'name' => 'Alice Dumont', 'info' => $classe, 'type' => 'eleve'],
                            ['id' => 'ec2', 'name' => 'Hugo Lefebvre', 'info' => $classe, 'type' => 'eleve']
                        ];
                    }
                    break;
            }
        }
        
        echo json_encode(['success' => true, 'persons' => $persons]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données']);
        error_log("Erreur AJAX get_persons: " . $e->getMessage());
    }
    
    exit;
}

// Préparer l'en-tête avec les styles inline de ce formulaire
$pageTitle = 'Ajouter un événement';
$extraCss = [];

ob_start();
?>
    <style>
        .event-creation-container { max-width: 800px; margin: 20px auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 0; }
        .event-creation-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #eee; }
        .event-creation-header h2 { margin: 0; color: #333; font-weight: 500; font-size: 20px; }
        .role-indicator { padding: 4px 8px; background-color: #e0f2e9; color: #00843d; border-radius: 4px; font-size: 14px; font-weight: 500; }
        .event-creation-form { padding: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .form-full { grid-column: 1 / -1; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group input[type="time"], .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #00843d; outline: none; box-shadow: 0 0 0 2px rgba(0,132,61,0.1); }
        .type-personnalise { margin-top: 10px; display: none; }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .btn-cancel { padding: 10px 15px; background-color: #f5f5f5; color: #333; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; text-decoration: none; }
        .btn-cancel:hover { background-color: #e0e0e0; }
        .btn-submit { padding: 10px 15px; background-color: #00843d; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
        .btn-submit:hover { background-color: #006e32; }
        .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 4px; }
        .message.success { background-color: #e0f2e9; color: #00843d; border: 1px solid #00843d; }
        .message.error { background-color: #ffe6e6; color: #f44336; border: 1px solid #f44336; }
        .multiselect-container { border: 1px solid #ddd; border-radius: 4px; overflow: hidden; max-height: 300px; display: flex; flex-direction: column; }
        .multiselect-search { padding: 10px; border-bottom: 1px solid #eee; }
        .multiselect-search input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .multiselect-actions { padding: 10px; display: flex; gap: 10px; border-bottom: 1px solid #eee; }
        .multiselect-action { background: none; border: none; color: #00843d; cursor: pointer; font-size: 12px; padding: 0; }
        .multiselect-action:hover { text-decoration: underline; }
        .multiselect-options { padding: 10px; overflow-y: auto; flex: 1; max-height: 200px; }
        .multiselect-option { margin-bottom: 5px; }
        .multiselect-option label { display: flex; align-items: center; font-weight: normal; cursor: pointer; padding: 4px 0; }
        .multiselect-option input[type="checkbox"] { margin-right: 8px; width: auto; }
        .persons-selector { margin-top: 15px; }
        .persons-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-top: 10px; }
        .person-item { display: flex; align-items: center; padding: 5px 0; border-bottom: 1px solid #eee; }
        .person-item:last-child { border-bottom: none; }
        .person-checkbox { margin-right: 10px; }
        .person-name { font-weight: 500; }
        .person-info { font-size: 0.85em; color: #666; margin-left: 10px; }
        .persons-search { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
        .persons-actions { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .persons-action { background: none; border: none; color: #00843d; cursor: pointer; padding: 5px; }
        .persons-count { margin-top: 10px; font-size: 0.9em; color: #666; }
        .loading-indicator { text-align: center; padding: 15px; font-style: italic; color: #666; }
        .no-persons { padding: 15px; text-align: center; color: #666; font-style: italic; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .event-creation-container { margin: 0; border-radius: 0; max-width: none; } }
    </style>
<?php
$extraHeadHtml = ob_get_clean();

include 'includes/header.php';
?>
        
        <!-- Main Content -->
        <div class="event-creation-container">
            <div class="event-creation-header">
                <a href="agenda.php" class="back-button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 19L3 12L10 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M3 12H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Retour
                </a>
                <h2>Ajouter un événement</h2>
                <?php if ($user_role === 'eleve' || $user_role === 'parent'): ?>
                    <div class="role-indicator">Événement personnel</div>
                <?php endif; ?>
            </div>
            
            <div class="event-creation-form">
                <?php if ($message): ?>
                    <div class="message success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if ($erreur): ?>
                    <div class="message error"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>
                
                <form method="post" id="event-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label for="titre">Titre*</label>
                            <input type="text" name="titre" id="titre" required placeholder="Titre de l'événement" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="type_evenement">Type d'événement*</label>
                            <select name="type_evenement" id="type_evenement" required onchange="toggleTypePersonnalise()">
                                <?php if (count($types_evenements) > 1): ?>
                                    <option value="">Sélectionnez un type</option>
                                <?php endif; ?>
                                <?php foreach ($types_evenements as $code => $type): ?>
                                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($type['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <!-- Champ pour le type personnalisé (affiché seulement si "Autre" est sélectionné) -->
                            <div id="type_personnalise_container" class="type-personnalise">
                                <label for="type_personnalise">Précisez le type</label>
                                <input type="text" name="type_personnalise" id="type_personnalise" placeholder="Type d'événement personnalisé" maxlength="50">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="visibilite">Visibilité*</label>
                            <select id="visibilite" name="visibilite" required onchange="toggleClassesSection(); loadPersons(this.value);">
                                <?php foreach ($options_visibilite as $code => $option): ?>
                                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($option['nom']) ?></option>
                                <?php endforeach; ?>
                                
                                <?php if ((isAdmin() || isTeacher() || isVieScolaire()) && !empty($classes)): ?>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="classes:<?= htmlspecialchars($classe) ?>">Classe: <?= htmlspecialchars($classe) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small>Détermine qui peut voir cet événement.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_debut">Date de début*</label>
                            <input type="date" name="date_debut" id="date_debut" value="<?= htmlspecialchars($date_par_defaut) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="heure_debut">Heure de début*</label>
                            <input type="time" name="heure_debut" id="heure_debut" value="<?= htmlspecialchars($heure_debut_defaut) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_fin">Date de fin*</label>
                            <input type="date" name="date_fin" id="date_fin" value="<?= htmlspecialchars($date_par_defaut) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="heure_fin">Heure de fin*</label>
                            <input type="time" name="heure_fin" id="heure_fin" value="<?= htmlspecialchars($heure_fin_defaut) ?>" required>
                        </div>
                        
                        <!-- Section pour les classes (visible uniquement si "Classes spécifiques" est sélectionné) -->
                        <div id="section_classes" class="form-group form-full" style="display: none;">
                            <label>Classes concernées</label>
                            <div class="multiselect-container">
                                <div class="multiselect-search">
                                    <input type="text" id="classes_search" placeholder="Rechercher une classe" oninput="filterOptions('classes_search', 'class-option')">
                                </div>
                                <div class="multiselect-actions">
                                    <button type="button" class="multiselect-action" onclick="selectAll('class-checkbox')">Tout sélectionner</button>
                                    <button type="button" class="multiselect-action" onclick="deselectAll('class-checkbox')">Tout désélectionner</button>
                                </div>
                                <div class="multiselect-options">
                                    <?php foreach ($classes as $classe): ?>
                                        <div class="multiselect-option class-option">
                                            <label>
                                                <input type="checkbox" name="classes[]" class="class-checkbox" value="<?= htmlspecialchars($classe) ?>">
                                                <?= htmlspecialchars($classe) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Personnes concernées -->
                        <div class="form-group form-full" id="personnesContainer">
                            <label for="personnes">Personnes concernées</label>
                            <div class="persons-selector">
                                <div class="persons-actions">
                                    <button type="button" class="persons-action" id="selectAllPersons">Tout sélectionner</button>
                                    <button type="button" class="persons-action" id="deselectAllPersons">Tout désélectionner</button>
                                </div>
                                <input type="text" id="searchPersons" class="persons-search" placeholder="Rechercher...">
                                <div class="persons-list" id="personsList">
                                    <div class="loading-indicator">Chargement des personnes concernées...</div>
                                </div>
                                <div class="persons-count" id="personsCount">0 personne(s) sélectionnée(s)</div>
                            </div>
                            <small>Sélectionnez les personnes spécifiquement concernées par cet événement.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="lieu">Lieu</label>
                            <input type="text" name="lieu" id="lieu" placeholder="Salle, bâtiment, etc." maxlength="100">
                        </div>
                        
                        <?php if (isTeacher() && !empty($prof_matiere)): ?>
                            <div class="form-group">
                                <label for="matieres">Matière associée</label>
                                <input type="text" name="matieres" id="matieres" value="<?= htmlspecialchars($prof_matiere) ?>" readonly>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="matieres">Matière associée</label>
                                <input type="text" name="matieres" id="matieres" placeholder="Matière concernée" maxlength="50">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group form-full">
                            <label for="description">Description</label>
                            <textarea name="description" id="description" rows="4" placeholder="Détails de l'événement..." maxlength="2000"></textarea>
                        </div>
                        
                        <div class="form-full">
                            <div class="form-actions">
                                <a href="agenda.php" class="btn-cancel">Annuler</a>
                                <button type="submit" class="btn-submit">Créer l'événement</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Synchroniser les dates de début et de fin
        document.getElementById('date_debut').addEventListener('change', function() {
            const dateFinInput = document.getElementById('date_fin');
            // Si la date de fin est vide ou si elle est avant la date de début
            if (!dateFinInput.value || dateFinInput.value < this.value) {
                dateFinInput.value = this.value;
            }
        });
        
        // Vérifier la cohérence des dates lors de la soumission
        document.getElementById('event-form').addEventListener('submit', function(e) {
            const date_debut = document.getElementById('date_debut').value;
            const heure_debut = document.getElementById('heure_debut').value;
            const date_fin = document.getElementById('date_fin').value;
            const heure_fin = document.getElementById('heure_fin').value;
            
            // Créer des objets Date pour comparer
            const debut = new Date(date_debut + 'T' + heure_debut);
            const fin = new Date(date_fin + 'T' + heure_fin);
            
            if (isNaN(debut.getTime()) || isNaN(fin.getTime())) {
                e.preventDefault();
                alert('Format de date ou heure invalide.');
                return false;
            }
            
            if (fin <= debut) {
                e.preventDefault();
                alert('La date/heure de fin doit être après la date/heure de début.');
                return false;
            }
            
            return true;
        });
        
        // Initialiser les sections cachées au chargement
        toggleClassesSection();
        toggleTypePersonnalise();
    });
    
    // Afficher/masquer la section des classes selon la visibilité sélectionnée
    function toggleClassesSection() {
        const visibiliteSelect = document.getElementById('visibilite');
        const sectionClasses = document.getElementById('section_classes');
        
        if (visibiliteSelect.value === 'classes_specifiques') {
            sectionClasses.style.display = 'block';
        } else {
            sectionClasses.style.display = 'none';
        }
    }
    
    // Afficher/masquer le champ de type personnalisé
    function toggleTypePersonnalise() {
        const typeSelect = document.getElementById('type_evenement');
        const typePersonnaliseContainer = document.getElementById('type_personnalise_container');
        
        if (typeSelect.value === 'autre') {
            typePersonnaliseContainer.style.display = 'block';
        } else {
            typePersonnaliseContainer.style.display = 'none';
        }
    }
    
    // Fonction pour filtrer les options dans un multiselect
    function filterOptions(searchId, optionClass) {
        const searchText = document.getElementById(searchId).value.toLowerCase();
        document.querySelectorAll('.' + optionClass).forEach(option => {
            const text = option.textContent.toLowerCase();
            option.style.display = text.includes(searchText) ? 'block' : 'none';
        });
    }
    
    // Sélectionner toutes les options
    function selectAll(checkboxClass) {
        document.querySelectorAll('.' + checkboxClass).forEach(checkbox => {
            checkbox.checked = true;
        });
    }
    
    // Désélectionner toutes les options
    function deselectAll(checkboxClass) {
        document.querySelectorAll('.' + checkboxClass).forEach(checkbox => {
            checkbox.checked = false;
        });
    }
    
    // Fonction pour gérer le chargement des personnes en fonction de la visibilité
    document.addEventListener('DOMContentLoaded', function() {
        const visibiliteSelect = document.getElementById('visibilite');
        const personnesContainer = document.getElementById('personnesContainer');
        const personsList = document.getElementById('personsList');
        const searchInput = document.getElementById('searchPersons');
        const selectAllBtn = document.getElementById('selectAllPersons');
        const deselectAllBtn = document.getElementById('deselectAllPersons');
        const personsCount = document.getElementById('personsCount');
        
        // Fonction pour charger les personnes selon la visibilité
        function loadPersons(visibility) {
            personsList.innerHTML = '<div class="loading-indicator">Chargement des personnes concernées...</div>';
            
            // Définir la visibilité du conteneur en fonction du type de visibilité
            if (visibility === 'public') {
                personnesContainer.style.display = 'none';
            } else {
                personnesContainer.style.display = 'block';
            }
            
            // Appel AJAX pour récupérer les personnes
            fetch(`ajouter_evenement.php?action=get_persons&visibility=${encodeURIComponent(visibility)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.persons.length > 0) {
                        renderPersons(data.persons);
                    } else {
                        personsList.innerHTML = '<div class="no-persons">Aucune personne trouvée pour cette visibilité.</div>';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des personnes:', error);
                    personsList.innerHTML = '<div class="no-persons">Erreur lors du chargement des personnes.</div>';
                });
        }
        
        // Fonction pour afficher les personnes avec échappement XSS
        function renderPersons(persons) {
            personsList.innerHTML = '';
            
            persons.forEach(person => {
                const personItem = document.createElement('div');
                personItem.className = 'person-item';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'person-checkbox';
                checkbox.name = 'personnes_concernees[]';
                checkbox.value = `${person.type}:${person.id}`;
                checkbox.id = `person-${person.type}-${person.id}`;
                checkbox.addEventListener('change', updateSelectedCount);
                
                const label = document.createElement('label');
                label.htmlFor = checkbox.id;
                label.className = 'person-label';
                
                const nameSpan = document.createElement('span');
                nameSpan.className = 'person-name';
                nameSpan.textContent = person.name;
                
                const infoSpan = document.createElement('span');
                infoSpan.className = 'person-info';
                infoSpan.textContent = person.info || '';
                
                label.appendChild(nameSpan);
                label.appendChild(infoSpan);
                
                personItem.appendChild(checkbox);
                personItem.appendChild(label);
                
                personsList.appendChild(personItem);
            });
            
            updateSelectedCount();
        }
        
        // Fonction pour filtrer les personnes
        function filterPersons() {
            const searchTerm = searchInput.value.toLowerCase();
            const personItems = document.querySelectorAll('.person-item');
            
            personItems.forEach(item => {
                const name = item.querySelector('.person-name').textContent.toLowerCase();
                const info = item.querySelector('.person-info').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || info.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        // Fonction pour mettre à jour le compteur de sélection
        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.person-checkbox:checked');
            personsCount.textContent = checkedBoxes.length + ' personne(s) sélectionnée(s)';
        }
        
        // Initialisation des écouteurs d'événements
        visibiliteSelect.addEventListener('change', function() {
            loadPersons(this.value);
        });
        
        searchInput.addEventListener('input', filterPersons);
        
        selectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.person-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        });
        
        deselectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.person-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        });
        
        // Exposer la fonction pour permettre son utilisation externe
        window.loadPersons = loadPersons;
        window.updateSelectedCount = updateSelectedCount;
        
        // Charger les personnes au chargement initial selon la valeur par défaut
        loadPersons(visibiliteSelect.value);
    });
    </script>

<?php
include 'includes/footer.php';
ob_end_flush();
?>