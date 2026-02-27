<?php
ob_start();

// Inclure l'API centralisée
require_once dirname(__DIR__) . '/API/core.php';

// Authentification via API
requireAuth();

// Récupération des infos utilisateur via API
$user = getCurrentUser();
$eleve_nom = getUserFullName();
$classe = $user['classe'] ?? '';
$user_role = getUserRole();
$user_initials = getUserInitials();

// getTrimestre() est fourni par l'API (Bridge)

$aujourdhui = date('d/m/Y');
$trimestre = getTrimestre();
$jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$jour = $jours[date('w')];

// Nom de l'établissement
$json_file = __DIR__ . '/../login/data/etablissement.json';
$etablissement_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$nom_etablissement = $etablissement_data['nom'] ?? 'Établissement Scolaire';

// Connexion DB via API centralisée
$pdo = getPDO();

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

// Contenu supplémentaire sidebar : Informations
ob_start();
?>
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
<?php
$sidebarExtraContent = ob_get_clean();

// Configuration des templates partagés
$pageTitle = 'Tableau de bord';
$activePage = 'accueil';
$user_fullname = $eleve_nom;
$extraCss = ['assets/css/accueil.css'];

// Inclusion des templates partagés
include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

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
        
<?php
include __DIR__ . '/../templates/shared_footer.php';
ob_end_flush();
?>