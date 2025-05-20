<?php
// Démarrer la mise en mémoire tampon de sortie pour éviter l'erreur "headers already sent"
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
    // Rediriger avec un message d'erreur
    $_SESSION['error_message'] = "Identifiant d'événement invalide ou non spécifié";
    header('Location: agenda.php');
    exit;
}

// Récupérer le paramètre updated pour afficher un message de succès
$updated = filter_input(INPUT_GET, 'updated', FILTER_VALIDATE_INT);
$updateMessage = '';
if ($updated === 1) {
    $updateMessage = "L'événement a été mis à jour avec succès.";
}

// Récupérer le paramètre d'erreur s'il existe
$error = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$errorMessage = '';
if ($error) {
    switch ($error) {
        case 'unauthorized':
            $errorMessage = "Vous n'avez pas les permissions nécessaires pour modifier cet événement.";
            break;
        case 'event_not_found':
            $errorMessage = "L'événement demandé n'existe pas ou a été supprimé.";
            break;
        case 'database_error':
            $errorMessage = "Une erreur de base de données s'est produite lors de l'accès à l'événement.";
            break;
        default:
            $errorMessage = "Une erreur s'est produite. Veuillez réessayer.";
    }
}

// Utiliser try-catch pour gérer les erreurs de base de données
try {
    // Vérifier si la colonne 'personnes_concernees' existe
    $stmt_check_column = $pdo->query("SHOW COLUMNS FROM evenements LIKE 'personnes_concernees'");
    $personnes_concernees_exists = $stmt_check_column && $stmt_check_column->rowCount() > 0;

    // Récupérer les détails de l'événement avec une requête préparée
    $stmt = $pdo->prepare('SELECT * FROM evenements WHERE id = ?');
    $stmt->execute([$id]);
    $evenement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier que l'événement existe
    if (!$evenement) {
        error_log("Événement non trouvé (ID: $id)");
        $_SESSION['error_message'] = "L'événement demandé n'existe pas ou a été supprimé.";
        header('Location: agenda.php?error=event_not_found');
        exit;
    }
} catch (PDOException $e) {
    // Journaliser l'erreur
    error_log("Erreur lors de la récupération de l'événement ID=$id: " . $e->getMessage());
    
    // Rediriger vers la page principale avec un message d'erreur
    $_SESSION['error_message'] = "Impossible de récupérer les détails de l'événement.";
    header('Location: agenda.php?error=database_error');
    exit;
}

// Vérifier les autorisations (si l'événement est visible pour l'utilisateur)
$can_view = false;

// Administrateurs et vie scolaire peuvent tout voir
if (isAdmin() || isVieScolaire()) {
    $can_view = true;
} 
// Vérifier la visibilité pour les autres rôles
else {
    // Si l'utilisateur est le créateur de l'événement
    if ($evenement['createur'] === $user_fullname) {
        $can_view = true;
    }
    // Si l'événement est public
    elseif ($evenement['visibilite'] === 'public') {
        $can_view = true;
    }
    // Si l'événement est pour les professeurs et l'utilisateur est un professeur
    elseif ($evenement['visibilite'] === 'professeurs' && isTeacher()) {
        $can_view = true;
    }
    // Si l'événement est pour les élèves et l'utilisateur est un élève
    elseif ($evenement['visibilite'] === 'eleves' && isStudent()) {
        $can_view = true;
    }
    // Si l'événement est pour des classes spécifiques
    elseif (strpos($evenement['visibilite'], 'classes:') === 0) {
        $classes_concernees = explode(',', substr($evenement['visibilite'], 8));
        
        // Si l'utilisateur est un élève, vérifier si sa classe est concernée
        if (isStudent() && isset($user['classe'])) {
            $classe_eleve = $user['classe'];
            
            if (!empty($classe_eleve) && in_array($classe_eleve, $classes_concernees)) {
                $can_view = true;
            }
        }
        // Si l'utilisateur est un professeur, il peut voir tous les événements pour des classes
        elseif (isTeacher()) {
            $can_view = true;
        }
    }
    // Si l'événement est pour l'administration et l'utilisateur est de l'administration
    elseif ($evenement['visibilite'] === 'administration' && isAdmin()) {
        $can_view = true;
    }
}

// Si l'utilisateur n'a pas les droits, rediriger
if (!$can_view) {
    error_log("Tentative non autorisée de visualisation de l'événement (ID: $id) par l'utilisateur: $user_fullname");
    $_SESSION['error_message'] = "Vous n'avez pas l'autorisation de consulter cet événement.";
    header('Location: agenda.php?error=unauthorized_view');
    exit;
}

// Déterminer si l'utilisateur peut modifier ou supprimer l'événement
$can_edit = false;
$can_delete = false;

// Administrateurs et vie scolaire peuvent tout modifier/supprimer
if (isAdmin() || isVieScolaire()) {
    $can_edit = true;
    $can_delete = true;
} 
// Les professeurs ne peuvent modifier/supprimer que leurs propres événements
elseif (isTeacher() && $evenement['createur'] === $user_fullname) {
    $can_edit = true;
    $can_delete = true;
}

// Formater les dates pour l'affichage de manière sécurisée
try {
    $date_debut = new DateTime($evenement['date_debut']);
    $date_fin = new DateTime($evenement['date_fin']);
    $format_date = 'd/m/Y';
    $format_heure = 'H:i';

    // Déterminer si l'événement est aujourd'hui, demain, passé ou futur
    $aujourd_hui = new DateTime();
    $demain = new DateTime('tomorrow');
    $is_today = $date_debut->format('Y-m-d') === $aujourd_hui->format('Y-m-d');
    $is_tomorrow = $date_debut->format('Y-m-d') === $demain->format('Y-m-d');
    $is_past = $date_fin < $aujourd_hui;
    $is_future = $date_debut > $aujourd_hui;
    $days_until = $is_future ? $date_debut->diff($aujourd_hui)->days : 0;
} catch (Exception $e) {
    // En cas d'erreur avec les dates, utiliser des valeurs par défaut
    error_log("Erreur lors du formatage des dates pour l'événement ID=$id: " . $e->getMessage());
    $is_today = false;
    $is_tomorrow = false;
    $is_past = false;
    $is_future = false;
    $days_until = 0;
}

// Déterminer le type d'événement pour l'affichage
$types_evenements = [
    'cours' => ['nom' => 'Cours', 'icone' => 'book', 'couleur' => '#00843d'],
    'devoirs' => ['nom' => 'Devoirs', 'icone' => 'pencil', 'couleur' => '#4285f4'],
    'reunion' => ['nom' => 'Réunion', 'icone' => 'users', 'couleur' => '#ff9800'],
    'examen' => ['nom' => 'Examen', 'icone' => 'file-text', 'couleur' => '#f44336'],
    'sortie' => ['nom' => 'Sortie scolaire', 'icone' => 'map-pin', 'couleur' => '#00c853'],
    'autre' => ['nom' => 'Autre', 'icone' => 'calendar', 'couleur' => '#9e9e9e']
];

$type_info = isset($types_evenements[$evenement['type_evenement']]) 
            ? $types_evenements[$evenement['type_evenement']] 
            : $types_evenements['autre'];

// Préparer les classes pour l'affichage
$classes_array = !empty($evenement['classes']) ? explode(',', $evenement['classes']) : [];

// Préparer le texte de visibilité
$visibilite_texte = '';
$visibilite_icone = 'lock';
if ($evenement['visibilite'] === 'public') {
    $visibilite_texte = 'Public (visible par tous)';
    $visibilite_icone = 'globe';
} elseif ($evenement['visibilite'] === 'professeurs') {
    $visibilite_texte = 'Professeurs uniquement';
    $visibilite_icone = 'user-tie';
} elseif ($evenement['visibilite'] === 'eleves') {
    $visibilite_texte = 'Élèves uniquement';
    $visibilite_icone = 'user-graduate';
} elseif (strpos($evenement['visibilite'], 'classes:') === 0) {
    $classes = substr($evenement['visibilite'], 8);
    $visibilite_texte = 'Classes spécifiques: ' . $classes;
    $visibilite_icone = 'users';
} elseif ($evenement['visibilite'] === 'administration') {
    $visibilite_texte = 'Administration uniquement';
    $visibilite_icone = 'user-shield';
} else {
    $visibilite_texte = $evenement['visibilite'];
}

// Générer un jeton sécurisé pour les opérations sensibles
try {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_' . $id] = $csrf_token;
    $_SESSION['csrf_token_time_' . $id] = time(); // Pour l'expiration
} catch (Exception $e) {
    // Fallback si random_bytes n'est pas disponible
    $csrf_token = hash('sha256', uniqid(mt_rand(), true) . time() . $id);
    $_SESSION['csrf_token_' . $id] = $csrf_token;
    $_SESSION['csrf_token_time_' . $id] = time();
}

// Générer le lien iCal de façon sécurisée
$ical_token = $csrf_token; // Réutiliser le token CSRF pour la simplicité
$ical_filename = urlencode(preg_replace('/[^a-z0-9]+/i', '_', $evenement['titre'])) . '.ics';
$ical_link = "export_ical.php?id=" . $id . "&token=" . $ical_token . "&filename=" . $ical_filename;

// Générer un lien de partage
$share_token = $csrf_token;
$share_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . 
              dirname($_SERVER['PHP_SELF']) . "/share_event.php?id=$id&token=$share_token";

// Personnes concernées (si la colonne existe)
$personnes_concernees_array = [];
if ($personnes_concernees_exists && !empty($evenement['personnes_concernees'])) {
    $personnes_concernees_array = explode(',', $evenement['personnes_concernees']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com">
    <title><?= htmlspecialchars($evenement['titre']) ?> - Agenda Pronote</title>
    <link rel="stylesheet" href="assets/css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Styles pour les notifications et alertes */
        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fade-in 0.3s ease-out forwards;
            transition: opacity 0.5s;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Animation d'entrée pour les messages d'alerte */
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Style pour la modale de confirmation */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1050; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%;
            overflow: auto; 
            background-color: rgba(0,0,0,0.4);
            animation: fade-in 0.2s;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            width: 400px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 15px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        /* Style pour les boutons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="../accueil/accueil.php" class="logo-container">
                <div class="app-logo">P</div>
                <div class="app-title">Pronote Agenda</div>
            </a>
            
            <!-- Mini-calendrier pour la navigation -->
            <div class="mini-calendar">
                <!-- Le mini-calendrier sera généré dynamiquement -->
            </div>
            
            <!-- Créer un événement -->
            <div class="sidebar-section">
                <a href="ajouter_evenement.php" class="create-button">
                    <span>+</span> Créer un événement
                </a>
            </div>
            
            <!-- Autres modules -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">Autres modules</div>
                <div class="folder-menu">
                    <a href="../notes/notes.php" class="module-link">
                        <i class="fas fa-chart-bar"></i> Notes
                    </a>
                    <a href="../messagerie/index.php" class="module-link">
                        <i class="fas fa-envelope"></i> Messagerie
                    </a>
                    <a href="../absences/absences.php" class="module-link">
                        <i class="fas fa-calendar-times"></i> Absences
                    </a>
                    <a href="../cahierdetextes/cahierdetextes.php" class="module-link">
                        <i class="fas fa-book"></i> Cahier de textes
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="top-header">
                <div class="calendar-navigation">
                    <a href="agenda.php" class="back-button">
                        <span class="back-icon">
                            <i class="fas fa-arrow-left"></i>
                        </span>
                        Retour à l'agenda
                    </a>
                </div>
                
                <div class="header-actions">
                    <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">⏻</a>
                    <div class="user-avatar"><?= htmlspecialchars($user_initials) ?></div>
                </div>
            </div>
            
            <!-- Container principal -->
            <div class="calendar-container">
                <div class="event-details-container">
                    <div class="event-header">
                        <div class="event-header-top">
                            <div class="event-title-container">
                                <h1 class="event-title"><?= htmlspecialchars($evenement['titre']) ?></h1>
                                <div class="event-subtitle">Créé par <?= htmlspecialchars($evenement['createur']) ?></div>
                            </div>
                            
                            <?php if ($evenement['statut'] !== 'actif'): ?>
                                <div class="event-status <?= $evenement['statut'] === 'annulé' ? 'cancelled' : 'postponed' ?>">
                                    <i class="fas fa-<?= $evenement['statut'] === 'annulé' ? 'ban' : 'clock' ?>"></i>
                                    <?= htmlspecialchars(ucfirst($evenement['statut'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="event-type" style="background-color: <?= htmlspecialchars($type_info['couleur']) ?>;">
                            <i class="fas fa-<?= htmlspecialchars($type_info['icone']) ?>"></i>
                            <?= htmlspecialchars($type_info['nom']) ?>
                        </div>
                        
                        <div class="event-timing">
                            <div class="event-date-display">
                                <i class="far fa-calendar-alt"></i>
                                <?php if ($date_debut->format('Y-m-d') === $date_fin->format('Y-m-d')): ?>
                                    <?= $date_debut->format($format_date) ?>
                                <?php else: ?>
                                    Du <?= $date_debut->format($format_date) ?> au <?= $date_fin->format($format_date) ?>
                                <?php endif; ?>
                                
                                <?php if ($is_today): ?>
                                    <span class="event-badge today">Aujourd'hui</span>
                                <?php elseif ($is_tomorrow): ?>
                                    <span class="event-badge tomorrow">Demain</span>
                                <?php elseif ($is_future): ?>
                                    <span class="event-badge future">Dans <?= $days_until ?> jour<?= $days_until > 1 ? 's' : '' ?></span>
                                <?php elseif ($is_past): ?>
                                    <span class="event-badge past">Passé</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-date-display">
                                <i class="far fa-clock"></i>
                                <?php if ($date_debut->format('Y-m-d') === $date_fin->format('Y-m-d')): ?>
                                    De <?= $date_debut->format($format_heure) ?> à <?= $date_fin->format($format_heure) ?>
                                <?php else: ?>
                                    De <?= $date_debut->format($format_date . ' à ' . $format_heure) ?> à <?= $date_fin->format($format_date . ' à ' . $format_heure) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="event-body">
                        <!-- Description -->
                        <?php if (!empty($evenement['description'])): ?>
                        <div class="event-section">
                            <h3 class="section-title">
                                <i class="fas fa-align-left"></i>
                                Description
                            </h3>
                            <div class="section-content description">
                                <?= nl2br(htmlspecialchars($evenement['description'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Informations supplémentaires -->
                        <div class="event-section">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle"></i>
                                Informations
                            </h3>
                            <div class="info-grid">
                                <?php if (!empty($evenement['lieu'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Lieu</div>
                                    <div class="info-value">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars($evenement['lieu']) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <div class="info-label">Visibilité</div>
                                    <div class="info-value">
                                        <i class="fas fa-<?= htmlspecialchars($visibilite_icone) ?>"></i>
                                        <?= htmlspecialchars($visibilite_texte) ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($evenement['matieres'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Matière</div>
                                    <div class="info-value">
                                        <i class="fas fa-book"></i>
                                        <?= htmlspecialchars($evenement['matieres']) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($evenement['date_modification']) && !empty($evenement['date_modification'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Dernière modification</div>
                                    <div class="info-value">
                                        <i class="fas fa-edit"></i>
                                        <?= (new DateTime($evenement['date_modification']))->format('d/m/Y à H:i') ?>
                                        <?= isset($evenement['modifie_par']) ? ' par ' . htmlspecialchars($evenement['modifie_par']) : '' ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($classes_array)): ?>
                            <div class="tags-container">
                                <?php foreach ($classes_array as $classe): ?>
                                    <div class="tag"><?= htmlspecialchars($classe) ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($personnes_concernees_exists && !empty($personnes_concernees_array)): ?>
                        <div class="event-section">
                            <h3 class="section-title">
                                <i class="fas fa-users"></i>
                                Personnes concernées
                            </h3>
                            <div class="tags-container">
                                <?php foreach ($personnes_concernees_array as $personne): ?>
                                    <div class="tag"><?= htmlspecialchars($personne) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actions -->
                        <div class="event-actions">
                            <a href="agenda.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Retour à l'agenda
                            </a>
                            
                            <a href="<?= htmlspecialchars($ical_link) ?>" class="btn btn-secondary" download>
                                <i class="fas fa-calendar-plus"></i>
                                Exporter (iCal)
                            </a>
                            
                            <button type="button" class="btn btn-secondary" onclick="shareEvent()">
                                <i class="fas fa-share-alt"></i>
                                Partager
                            </button>
                            
                            <?php if ($can_edit): ?>
                            <a href="modifier_evenement.php?id=<?= htmlspecialchars($id) ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i>
                                Modifier
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($can_delete): ?>
                            <button type="button" onclick="confirmerSuppression(<?= htmlspecialchars($id) ?>)" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i>
                                Supprimer
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Affichage des messages de succès ou d'erreur -->
    <?php if ($updateMessage): ?>
        <div id="updateMessage" class="alert-message alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($updateMessage) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
        <div id="errorMessage" class="alert-message alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($errorMessage) ?></span>
        </div>
    <?php endif; ?>
    
    <!-- Modal de confirmation de suppression -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmation de suppression</h3>
                <span class="close" onclick="fermerModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cet événement ?</p>
                <p>Cette action est irréversible.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fermerModal()">Annuler</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Supprimer</a>
            </div>
        </div>
    </div>
    
    <script>
    // Faire disparaître les messages après 5 secondes
    document.addEventListener('DOMContentLoaded', function() {
        const updateMessage = document.getElementById('updateMessage');
        const errorMessage = document.getElementById('errorMessage');
        
        if (updateMessage) {
            setTimeout(function() {
                updateMessage.style.opacity = '0';
                setTimeout(function() {
                    updateMessage.style.display = 'none';
                }, 1000);
            }, 5000);
        }
        
        if (errorMessage) {
            setTimeout(function() {
                errorMessage.style.opacity = '0';
                setTimeout(function() {
                    errorMessage.style.display = 'none';
                }, 1000);
            }, 5000);
        }
    });
    
    // Fonction pour partager l'événement
    function shareEvent() {
        const shareUrl = "<?= htmlspecialchars($share_link) ?>";
        const eventTitle = "<?= addslashes(htmlspecialchars($evenement['titre'])) ?>";
        
        // Utiliser l'API de partage si disponible
        if (navigator.share) {
            navigator.share({
                title: eventTitle,
                text: "Événement: " + eventTitle,
                url: shareUrl
            }).catch(error => {
                console.error("Erreur lors du partage:", error);
                copyToClipboard(shareUrl);
            });
        } else {
            // Fallback: copier le lien dans le presse-papier
            copyToClipboard(shareUrl);
        }
    }
    
    // Fonction pour copier dans le presse-papier
    function copyToClipboard(text) {
        // Créer un champ temporaire
        const tempInput = document.createElement("input");
        tempInput.style.position = "absolute";
        tempInput.style.left = "-1000px";
        tempInput.value = text;
        document.body.appendChild(tempInput);
        
        // Sélectionner et copier
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        
        // Afficher un message de confirmation
        alert("Lien de partage copié dans le presse-papier");
    }
    
    // Fonctions pour la modal de confirmation
    function confirmerSuppression(id) {
        const modal = document.getElementById('confirmationModal');
        const confirmBtn = document.getElementById('confirmDelete');
        
        // Définir l'URL de suppression avec le token CSRF
        confirmBtn.href = "supprimer_evenement.php?id=" + id + "&token=<?= $csrf_token ?>";
        
        // Afficher la modal
        modal.style.display = "block";
    }
    
    function fermerModal() {
        const modal = document.getElementById('confirmationModal');
        modal.style.display = "none";
    }
    
    // Fermer la modal en cliquant en dehors
    window.onclick = function(event) {
        const modal = document.getElementById('confirmationModal');
        if (event.target === modal) {
            modal.style.display = "none";
        }
    }
    </script>

<?php
// Inclusion du pied de page
include 'includes/footer.php';

// Terminer la mise en mémoire tampon et envoyer la sortie
ob_end_flush();
?>