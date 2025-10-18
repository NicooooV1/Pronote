<?php
ob_start();

// Utilisation des Facades API
$bootstrap_path = dirname(__DIR__) . '/API/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    die("Erreur: Le fichier bootstrap.php n'existe pas à l'emplacement: " . $bootstrap_path);
}
require_once $bootstrap_path;

use API\Core\Facades\Auth;
use API\Core\Facades\DB;

// Authentification via API
Auth::requireAuth();

// Récupération des infos utilisateur via API
$user = Auth::user();
$eleve_nom = $user['prenom'] . ' ' . $user['nom'];
$classe = $user['classe'] ?? '';
$user_role = $user['profil'];
$user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

// Détermination du trimestre
function getTrimestre() {
    $mois = date('n');
    if ($mois >= 9 && $mois <= 12) return "1er trimestre";
    if ($mois >= 1 && $mois <= 3) return "2ème trimestre";
    if ($mois >= 4 && $mois <= 6) return "3ème trimestre";
    return "Période estivale";
}

$aujourdhui = date('d/m/Y');
$trimestre = getTrimestre();
$jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$jour = $jours[date('w')];

// Nom de l'établissement
$json_file = __DIR__ . '/../login/data/etablissement.json';
$etablissement_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$nom_etablissement = $etablissement_data['nom'] ?? 'Établissement Scolaire';

// Connexion DB via API avec gestion d'erreur
try {
    if (!class_exists('API\Core\Facades\DB')) {
        throw new Exception("La classe DB facade n'est pas disponible");
    }
    $pdo = DB::getPDO();
} catch (Exception $e) {
    error_log("Erreur DB Facade: " . $e->getMessage());
    // Fallback vers connexion directe si nécessaire
    $config_path = dirname(__DIR__) . '/API/config/database.php';
    if (file_exists($config_path)) {
        $db_config = require $config_path;
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['database']};charset=utf8mb4",
            $db_config['username'],
            $db_config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } else {
        die("Erreur: Impossible de se connecter à la base de données");
    }
}

// Prochains événements
$prochains_evenements = [];
try {
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'evenements'");
    if ($stmt_check && $stmt_check->rowCount() > 0) {
        $date_actuelle = date('Y-m-d');
        $query = "SELECT * FROM evenements WHERE date_debut >= ?";
        $params = [$date_actuelle];
        if ($user_role == 'eleve') {
            $query .= " AND (visibilite = 'public' OR visibilite = 'eleves' OR visibilite LIKE ? OR classes LIKE ?)";
            $params[] = '%' . $classe . '%';
            $params[] = '%' . $classe . '%';
        } elseif ($user_role == 'professeur') {
            $query .= " AND (visibilite = 'public' OR visibilite = 'professeurs' OR nom_professeur = ?)";

            $params[] = $eleve_nom;
        }
        $query .= " ORDER BY date_debut ASC LIMIT 3";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $prochains_evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\PDOException $e) {
    error_log("Erreur événements: " . $e->getMessage());
}

// Devoirs à faire
$devoirs_a_faire = [];
try {
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'devoirs'");
    if ($stmt_check && $stmt_check->rowCount() > 0) {
        $date_actuelle = date('Y-m-d');
        $query = "SELECT * FROM devoirs WHERE date_rendu >= ?";
        $params = [$date_actuelle];
        if ($user_role == 'eleve') {
            $query .= " AND classe = ?";
            $params[] = $classe;
        } elseif ($user_role == 'professeur') {
            $query .= " AND nom_professeur = ?";
            $params[] = $eleve_nom;
        }
        $query .= " ORDER BY date_rendu ASC LIMIT 3";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $devoirs_a_faire = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\PDOException $e) {
    error_log("Erreur devoirs: " . $e->getMessage());
}

// Dernières notes
$dernieres_notes = [];
try {
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'notes'");
    if ($stmt_check && $stmt_check->rowCount() > 0) {
        $query = "SELECT * FROM notes";
        $params = [];
        if ($user_role == 'eleve') {
            $query .= " WHERE nom_eleve = ?";
            $params[] = $eleve_nom;
        } elseif ($user_role == 'professeur') {
            $query .= " WHERE nom_professeur = ?";
            $params[] = $eleve_nom;
        }
        $query .= " ORDER BY date_creation DESC LIMIT 3";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $dernieres_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\PDOException $e) {
    error_log("Erreur notes: " . $e->getMessage());
}

// Détermination admin
$isAdmin = $user_role === 'administrateur';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - PRONOTE</title>
    <link rel="stylesheet" href="assets/css/accueil.css">
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
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">Navigation</div>
            <div class="sidebar-nav">
                <a href="accueil.php" class="sidebar-nav-item active">
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
                <a href="../absences/absences.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                    <span>Absences</span>
                </a>
            </div>
        </div>
        
        <?php if ($isAdmin): ?>
        <div class="sidebar-section">
            <div class="sidebar-section-header">Administration</div>
            <div class="sidebar-nav">
                <a href="../login/public/register.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Ajouter un utilisateur</span>
                </a>
                <a href="../admin/reset_user_password.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-key"></i></span>
                    <span>Réinitialiser mot de passe</span>
                </a>
                <a href="../admin/reset_requests.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Demandes de réinitialisation</span>
                </a>
                <a href="../admin/admin_accounts.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span>Gestion des administrateurs</span>
                </a>
                <a href="../admin/user_accounts.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-users-cog"></i></span>
                    <span>Gestion des utilisateurs</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
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

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="top-header">
            <div class="page-title">
                <h1>Tableau de bord</h1>
            </div>
            
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/login/public/logout.php" class="logout-button" title="Déconnexion">⏻</a>
                <div class="user-avatar"><?= $user_initials ?></div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2>Bienvenue, <?= htmlspecialchars($eleve_nom) ?></h2>
                <?php if (!empty($classe)): ?>
                <p>Classe de <?= htmlspecialchars($classe) ?></p>
                <?php endif; ?>
                <p class="welcome-date"><?= $jour . ' ' . $aujourdhui ?> - <?= $trimestre ?></p>
            </div>
            <div class="welcome-logo">
                <i class="fas fa-school"></i>
            </div>
        </div>
        
        <!-- Main Dashboard Content -->
        <div class="dashboard-content">
            <!-- Modules Grid -->
            <div class="modules-grid">
                <a href="../notes/notes.php" class="module-card notes-card">
                    <div class="module-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="module-info">
                        <h3>Notes</h3>
                        <p>Consultez vos notes et moyennes</p>
                    </div>
                </a>
                
                <a href="../agenda/agenda.php" class="module-card agenda-card">
                    <div class="module-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="module-info">
                        <h3>Agenda</h3>
                        <p>Consultez votre planning et vos événements</p>
                    </div>
                </a>
                
                <a href="../cahierdetextes/cahierdetextes.php" class="module-card devoirs-card">
                    <div class="module-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="module-info">
                        <h3>Cahier de textes</h3>
                        <p>Consultez vos devoirs à faire</p>
                    </div>
                </a>
                
                <a href="../messagerie/index.php" class="module-card messagerie-card">
                    <div class="module-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="module-info">
                        <h3>Messagerie</h3>
                        <p>Communiquez avec vos professeurs et l'administration</p>
                    </div>
                </a>
                
                <?php if ($user_role === 'vie_scolaire' || $user_role === 'administrateur'): ?>
                <a href="../absences/absences.php" class="module-card absences-card">
                    <div class="module-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="module-info">
                        <h3>Absences</h3>
                        <p>Gérez les absences et retards</p>
                    </div>
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Widgets Section -->
            <div class="widgets-section">
                <!-- Agenda Widget -->
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-calendar"></i> Prochains événements</h3>
                        <a href="../agenda/agenda.php" class="widget-action">Voir tout</a>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($prochains_evenements)): ?>
                            <div class="empty-widget-message">
                                <i class="fas fa-info-circle"></i>
                                <p>Aucun événement à venir</p>
                            </div>
                        <?php else: ?>
                            <ul class="events-list">
                                <?php foreach ($prochains_evenements as $event): ?>
                                    <li class="event-item event-<?= strtolower($event['type_evenement']) ?>">
                                        <div class="event-date">
                                            <?= date('d/m', strtotime($event['date_debut'])) ?>
                                        </div>
                                        <div class="event-details">
                                            <div class="event-title"><?= htmlspecialchars($event['titre']) ?></div>
                                            <div class="event-time"><?= date('H:i', strtotime($event['date_debut'])) ?> - <?= date('H:i', strtotime($event['date_fin'])) ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Cahier de textes Widget -->
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-book"></i> Devoirs à faire</h3>
                        <a href="../cahierdetextes/cahierdetextes.php" class="widget-action">Voir tout</a>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($devoirs_a_faire)): ?>
                            <div class="empty-widget-message">
                                <i class="fas fa-info-circle"></i>
                                <p>Aucun devoir à rendre prochainement</p>
                            </div>
                        <?php else: ?>
                            <ul class="assignments-list">
                                <?php foreach ($devoirs_a_faire as $devoir): ?>
                                    <li class="assignment-item">
                                        <div class="assignment-date">
                                            <?= date('d/m', strtotime($devoir['date_rendu'])) ?>
                                        </div>
                                        <div class="assignment-details">
                                            <div class="assignment-title"><?= htmlspecialchars($devoir['titre']) ?></div>
                                            <div class="assignment-subject"><?= htmlspecialchars($devoir['nom_matiere']) ?> - <?= htmlspecialchars($devoir['nom_professeur']) ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Notes Widget -->
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-chart-bar"></i> Dernières notes</h3>
                        <a href="../notes/notes.php" class="widget-action">Voir tout</a>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($dernieres_notes)): ?>
                            <div class="empty-widget-message">
                                <i class="fas fa-info-circle"></i>
                                <p>Aucune note récente</p>
                            </div>
                        <?php else: ?>
                            <ul class="grades-list">
                                <?php foreach ($dernieres_notes as $note): ?>
                                    <li class="grade-item">
                                        <div class="grade-value"><?= htmlspecialchars($note['note']) ?>/<?= $note['note_sur'] ?? 20 ?></div>
                                        <div class="grade-details">
                                            <div class="grade-title"><?= htmlspecialchars($note['matiere'] ?? $note['nom_matiere']) ?></div>
                                            <div class="grade-date"><?= date('d/m/Y', strtotime($note['date_creation'])) ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
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

</body>
</html>

<?php
ob_end_flush();
?>