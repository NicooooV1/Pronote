<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Inclure UNIQUEMENT l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification
requireAuth();

// Récupérer l'utilisateur actuel
$user = getCurrentUser();

// Vérifier les droits d'accès (vie scolaire ou administrateur)
if (!in_array($user['profil'], ['vie_scolaire', 'administrateur'])) {
    redirect('/accueil/accueil.php');
}

// Récupérer la connexion UNIQUEMENT via l'API
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    logError("Erreur de connexion DB dans absences: " . $e->getMessage());
    die("Erreur de connexion à la base de données");
}

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ../login/public/index.php');
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: ../login/public/index.php');
    exit;
}

// Initialiser les variables nécessaires
$user_fullname = $user['prenom'] . ' ' . $user['nom'];
$user_role = $user['profil'];
$user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

// Configuration de la page
$pageTitle = 'Absences';
$currentPage = 'liste';

// Définir les filtres par défaut
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d', strtotime('-30 days'));
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');
$classe = isset($_GET['classe']) ? $_GET['classe'] : '';
$view = isset($_GET['view']) ? $_GET['view'] : 'list';
$justifie = isset($_GET['justifie']) ? $_GET['justifie'] : '';
$periode_active = isset($_GET['periode']) ? $_GET['periode'] : '';

// Récupérer la liste des absences selon le rôle de l'utilisateur
$absences = [];

// Pour déboguer: journaliser le rôle de l'utilisateur
error_log("absences.php - Rôle de l'utilisateur: " . $user_role);

if (isAdmin() || isVieScolaire()) {
    error_log("absences.php - Utilisateur admin ou vie scolaire détecté");
    if (!empty($classe)) {
        $absences = getAbsencesClasse($pdo, $classe, $date_debut, $date_fin, $justifie);
    } else {
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE (
                    (a.date_debut BETWEEN ? AND ?) OR  /* Commence dans la période */
                    (a.date_fin BETWEEN ? AND ?) OR    /* Finit dans la période */
                    (a.date_debut <= ? AND a.date_fin >= ?) /* Chevauche complètement la période */
                )";
                
        if ($justifie !== '') {
            $sql .= "AND a.justifie = ? ";
            $params = [$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_fin, $justifie === 'oui'];
        } else {
            $params = [$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_fin];
        }
        
        $sql .= "ORDER BY a.date_debut DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Journaliser le nombre d'absences récupérées
        error_log("absences.php - Récupération de " . count($absences) . " absences pour admin/vie scolaire");
    }
} elseif (isTeacher()) {
    error_log("absences.php - Utilisateur professeur détecté: ID=" . $user['id']);
    try {
        // Récupérer les classes du professeur
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.nom_classe as classe
            FROM professeur_classes c
            WHERE c.id_professeur = ?
        ");
        $stmt->execute([$user['id']]);
        $prof_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        error_log("absences.php - Classes du professeur: " . implode(", ", $prof_classes));
        
        // Si pas de classes trouvées, utiliser un tableau vide
        if (empty($prof_classes)) {
            $prof_classes = [];
        }
        
        if (!empty($classe) && in_array($classe, $prof_classes)) {
            $absences = getAbsencesClasse($pdo, $classe, $date_debut, $date_fin, $justifie);
        } else if (!empty($prof_classes)) {
            $placeholders = implode(',', array_fill(0, count($prof_classes), '?'));
            $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                    FROM absences a 
                    JOIN eleves e ON a.id_eleve = e.id 
                    WHERE e.classe IN ($placeholders) 
                    AND (
                        (a.date_debut BETWEEN ? AND ?) OR  /* Commence dans la période */
                        (a.date_fin BETWEEN ? AND ?) OR    /* Finit dans la période */
                        (a.date_debut <= ? AND a.date_fin >= ?) /* Chevauche complètement la période */
                    )";
                
            if ($justifie !== '') {
                $sql .= "AND a.justifie = ? ";
                $params = array_merge($prof_classes, [$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_fin, $justifie === 'oui']);
            } else {
                $params = array_merge($prof_classes, [$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_fin]);
            }
            
            $sql .= "ORDER BY e.classe, e.nom, e.prenom, a.date_debut DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("absences.php - Récupération de " . count($absences) . " absences pour professeur");
        }
    } catch (PDOException $e) {
        error_log("absences.php - Erreur dans la requête pour professeur: " . $e->getMessage());
        // Pas d'action corrective, l'application continue avec $absences vide
    }
} elseif (isStudent()) {
    error_log("absences.php - Utilisateur élève détecté: ID=" . $user['id']);
    $absences = getAbsencesEleve($pdo, $user['id'], $date_debut, $date_fin);
    error_log("absences.php - Récupération de " . count($absences) . " absences pour élève");
} elseif (isParent()) {
    $stmt = $pdo->prepare("SELECT id_eleve FROM parents_eleves WHERE id_parent = ?");
    $stmt->execute([$user['id']]);
    $enfants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($enfants)) {
        $placeholders = implode(',', array_fill(0, count($enfants), '?'));
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE a.id_eleve IN ($placeholders) 
                AND (
                    (a.date_debut BETWEEN ? AND ?) OR  /* Commence dans la période */
                    (a.date_fin BETWEEN ? AND ?) OR    /* Finit dans la période */
                    (a.date_debut <= ? AND a.date_fin >= ?) /* Chevauche complètement la période */
                )";
                
        if ($justifie !== '') {
            $sql .= "AND a.justifie = ? ";
            $params = array_merge($enfants, [$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_fin, $justifie === 'oui']);
        } else {
            $params = array_merge($enfants, [$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_fin]);
        }
        
        $sql .= "ORDER BY e.nom, e.prenom, a.date_debut DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Make sure the views directory exists
$views_dir = __DIR__ . '/views';
if (!is_dir($views_dir)) {
    mkdir($views_dir, 0755, true);
    // Aucune vérification si mkdir réussit
    
    // Écriture de fichiers sans vérification du succès de l'opération
    file_put_contents($views_dir . '/list_view.php', '<?php // Basic list view implementation ?>
        <div class="absences-list">
            <div class="list-header">
                <div class="list-row header-row">
                    <div class="list-cell header-cell">Élève</div>
                    <div class="list-cell header-cell">Classe</div>
                    <div class="list-cell header-cell">Date</div>
                    <div class="list-cell header-cell">Type</div>
                    <div class="list-cell header-cell">Justifié</div>
                </div>
            </div>
            <div class="list-body">
                <?php foreach ($absences as $absence): ?>
                    <div class="list-row">
                        <div class="list-cell"><?= htmlspecialchars($absence["nom"] . " " . $absence["prenom"]) ?></div>
                        <div class="list-cell"><?= htmlspecialchars($absence["classe"]) ?></div>
                        <div class="list-cell"><?= (new DateTime($absence["date_debut"]))->format("d/m/Y") ?></div>
                        <div class="list-cell"><?= htmlspecialchars($absence["type_absence"]) ?></div>
                        <div class="list-cell"><?= $absence["justifie"] ? "Oui" : "Non" ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>');
        
    file_put_contents($views_dir . '/calendar_view.php', 
        '<?php // Basic calendar view implementation ?>
        <div class="calendar-view">
            <p>Vue calendrier - Implémentation à venir</p>
        </div>');
        
    file_put_contents($views_dir . '/stats_view.php', 
        '<?php // Basic stats view implementation ?>
        <div class="stats-view">
            <p>Vue statistiques - Implémentation à venir</p>
        </div>');
}

// Récupérer la liste des classes pour le filtre
$classes = [];
$etablissement_data = [];

// Vérifier si le fichier existe avant de le lire
$etablissementJsonFile = '../login/data/etablissement.json';
if (file_exists($etablissementJsonFile)) {
    $etablissement_data = json_decode(file_get_contents($etablissementJsonFile), true);
    if (!empty($etablissement_data['classes'])) {
        foreach ($etablissement_data['classes'] as $niveau => $niveaux) {
            foreach ($niveaux as $cycle => $liste_classes) {
                foreach ($liste_classes as $nom_classe) {
                    $classes[] = $nom_classe;
                }
            }
        }
    }
} else {
    error_log("Fichier d'établissement non trouvé: $etablissementJsonFile");
}

// Formatage des dates pour affichage convivial
$date_debut_formattee = date('d/m/Y', strtotime($date_debut));
$date_fin_formattee = date('d/m/Y', strtotime($date_fin));

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Bannière de bienvenue conditionnelle basée sur les rôles -->
<?php if (isAdmin() || isVieScolaire()): ?>
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Gestion des Absences</h2>
        <p>Consultez, gérez et suivez les absences des élèves de l'établissement.</p>
    </div>
    <div class="welcome-icon">
        <i class="fas fa-user-clock"></i>
    </div>
</div>
<?php elseif (isTeacher()): ?>
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Suivi des Absences</h2>
        <p>Consultez les absences des élèves de vos classes.</p>
    </div>
    <div class="welcome-icon">
        <i class="fas fa-chalkboard-teacher"></i>
    </div>
</div>
<?php else: ?>
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Mes Absences</h2>
        <p>Consultez l'historique de vos absences.</p>
    </div>
    <div class="welcome-icon">
        <i class="fas fa-calendar-check"></i>
    </div>
</div>
<?php endif; ?>

<!-- Barre de filtres -->
<div class="filters-bar">
    <form id="filter-form" class="filter-form" method="get" action="absences.php">
        <div class="filter-item">
            <label for="date_debut" class="form-label">Du</label>
            <input type="date" id="date_debut" name="date_debut" value="<?= $date_debut ?>" max="<?= date('Y-m-d') ?>" class="form-control">
        </div>
        
        <div class="filter-item">
            <label for="date_fin" class="form-label">Au</label>
            <input type="date" id="date_fin" name="date_fin" value="<?= $date_fin ?>" max="<?= date('Y-m-d') ?>" class="form-control">
        </div>
        
        <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
        <div class="filter-item">
            <label for="classe" class="form-label">Classe</label>
            <select id="classe" name="classe" class="form-control">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $classe === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="filter-item">
            <label for="justifie" class="form-label">Justification</label>
            <select id="justifie" name="justifie" class="form-control">
                <option value="">Toutes</option>
                <option value="oui" <?= $justifie === 'oui' ? 'selected' : '' ?>>Justifiées</option>
                <option value="non" <?= $justifie === 'non' ? 'selected' : '' ?>>Non justifiées</option>
            </select>
        </div>
        
        <div class="filter-item">
            <label for="view" class="form-label">Vue</label>
            <select id="view" name="view" class="form-control">
                <option value="list" <?= $view === 'list' ? 'selected' : '' ?>>Liste</option>
                <option value="calendar" <?= $view === 'calendar' ? 'selected' : '' ?>>Calendrier</option>
                <?php if (isAdmin() || isVieScolaire()): ?>
                <option value="stats" <?= $view === 'stats' ? 'selected' : '' ?>>Statistiques</option>
                <?php endif; ?>
            </select>
        </div>
        
        <div class="filter-buttons">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrer
            </button>
            <a href="absences.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Réinitialiser
            </a>
        </div>
    </form>
</div>

<!-- Contenu principal selon le type de vue sélectionnée -->
<div class="content-section">
    <div class="content-header">
        <h2>
            <?php if (!empty($classe)): ?>
                Absences de la classe <?= htmlspecialchars($classe) ?>
            <?php else: ?>
                <?= isStudent() ? 'Mes absences' : 'Absences' ?> du <?= $date_debut_formattee ?> au <?= $date_fin_formattee ?>
            <?php endif; ?>
        </h2>
        <div class="content-actions">
            <?php if (canManageAbsences()): ?>
                <?php if ($view === 'list'): ?>
                <a href="export.php?format=excel&<?= http_build_query($_GET) ?>" class="btn btn-outline">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
                <?php endif; ?>
                <?php if (isAdmin() || isVieScolaire()): ?>
                <a href="imprimer_absences.php?<?= http_build_query($_GET) ?>" class="btn btn-outline" target="_blank">
                    <i class="fas fa-print"></i> Imprimer
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($dbError)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $dbError ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= $_SESSION['success_message'] ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $_SESSION['error_message'] ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="content-body">
        <?php if (empty($absences)): ?>
            <div class="no-data-message">
                <i class="fas fa-calendar-times"></i>
                <p>Aucune absence ne correspond aux critères sélectionnés.</p>
                <?php if (canManageAbsences()): ?>
                <a href="ajouter_absence.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus"></i> Signaler une absence
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($view === 'list'): ?>
                <?php include 'views/list_view.php'; ?>
            <?php elseif ($view === 'calendar'): ?>
                <?php include 'views/calendar_view.php'; ?>
            <?php elseif ($view === 'stats'): ?>
                <?php include 'views/stats_view.php'; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Inclure le pied de page
include 'includes/footer.php';
ob_end_flush();
?>