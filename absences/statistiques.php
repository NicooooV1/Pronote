<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Inclusion de l'API centralisée
require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/functions.php';

// Vérifier que l'utilisateur est connecté
requireAuth();

// Récupérer les informations de l'utilisateur connecté via l'API
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Définir les filtres par défaut
$annee_scolaire = isset($_GET['annee_scolaire']) ? $_GET['annee_scolaire'] : 'current';
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'trimestre_1';
$classe = isset($_GET['classe']) ? $_GET['classe'] : '';

// Déterminer les dates de début et fin selon la période
$date_debut = '';
$date_fin = '';

if ($annee_scolaire === 'current') {
    // Année scolaire en cours (du 1er septembre au 31 août)
    $annee_debut = date('Y');
    $mois_actuel = date('n');
    
    // Si on est entre janvier et août, l'année scolaire a commencé l'année précédente
    if ($mois_actuel < 9) {
        $annee_debut = $annee_debut - 1;
    }
    
    $annee_fin = $annee_debut + 1;
    
    if ($periode === 'annee') {
        $date_debut = $annee_debut . '-09-01';
        $date_fin = $annee_fin . '-08-31';
    } elseif ($periode === 'trimestre_1') {
        $date_debut = $annee_debut . '-09-01';
        $date_fin = $annee_debut . '-11-30';
    } elseif ($periode === 'trimestre_2') {
        $date_debut = $annee_debut . '-12-01';
        $date_fin = $annee_fin . '-02-28';
    } elseif ($periode === 'trimestre_3') {
        $date_debut = $annee_fin . '-03-01';
        $date_fin = $annee_fin . '-05-31';
    } elseif ($periode === 'trimestre_4') {
        $date_debut = $annee_fin . '-06-01';
        $date_fin = $annee_fin . '-08-31';
    }
} else {
    // Année scolaire précédente
    $parts = explode('-', $annee_scolaire);
    $annee_debut = intval($parts[0]);
    $annee_fin = intval($parts[1]);
    
    if ($periode === 'annee') {
        $date_debut = $annee_debut . '-09-01';
        $date_fin = $annee_fin . '-08-31';
    } elseif ($periode === 'trimestre_1') {
        $date_debut = $annee_debut . '-09-01';
        $date_fin = $annee_debut . '-11-30';
    } elseif ($periode === 'trimestre_2') {
        $date_debut = $annee_debut . '-12-01';
        $date_fin = $annee_fin . '-02-28';
    } elseif ($periode === 'trimestre_3') {
        $date_debut = $annee_fin . '-03-01';
        $date_fin = $annee_fin . '-05-31';
    } elseif ($periode === 'trimestre_4') {
        $date_debut = $annee_fin . '-06-01';
        $date_fin = $annee_fin . '-08-31';
    }
}

// Récupérer les statistiques selon le rôle
$stats = [];
$eleves_stats = [];
$classes_stats = [];

if (isAdmin() || isVieScolaire()) {
    // Statistiques globales
    if (!empty($classe)) {
        // Statistiques pour une classe spécifique
        $sql = "SELECT 
                    COUNT(DISTINCT a.id) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    COUNT(DISTINCT a.id_eleve) as nb_eleves_absents,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM absences a
                JOIN eleves e ON a.id_eleve = e.id
                WHERE a.date_debut BETWEEN ? AND ?
                AND e.classe = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date_debut, $date_fin, $classe]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistiques par élève pour cette classe
        $sql = "SELECT 
                    e.id,
                    e.nom,
                    e.prenom,
                    COUNT(a.id) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM eleves e
                LEFT JOIN absences a ON e.id = a.id_eleve AND a.date_debut BETWEEN ? AND ?
                WHERE e.classe = ?
                GROUP BY e.id
                ORDER BY nb_absences DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date_debut, $date_fin, $classe]);
        $eleves_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Statistiques pour toutes les classes
        $sql = "SELECT 
                    COUNT(DISTINCT a.id) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    COUNT(DISTINCT a.id_eleve) as nb_eleves_absents,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM absences a
                WHERE a.date_debut BETWEEN ? AND ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date_debut, $date_fin]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistiques par classe
        $sql = "SELECT 
                    e.classe,
                    COUNT(DISTINCT a.id) as nb_absences,
                    COUNT(DISTINCT a.id_eleve) as nb_eleves_absents,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM absences a
                JOIN eleves e ON a.id_eleve = e.id
                WHERE a.date_debut BETWEEN ? AND ?
                GROUP BY e.classe
                ORDER BY nb_absences DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date_debut, $date_fin]);
        $classes_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif (isTeacher()) {
    // Fix the query to get the classes taught by the teacher
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.nom_classe as classe
        FROM professeur_classes c
        WHERE c.id_professeur = ?
    ");
    $stmt->execute([$user['id']]);
    $prof_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no classes found for this professor, use an empty array to avoid SQL errors
    if (empty($prof_classes)) {
        $prof_classes = [];
    }
    
    if (!empty($classe) && in_array($classe, $prof_classes)) {
        // Statistiques pour une classe spécifique
        $sql = "SELECT 
                    COUNT(DISTINCT a.id) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    COUNT(DISTINCT a.id_eleve) as nb_eleves_absents,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM absences a
                JOIN eleves e ON a.id_eleve = e.id
                WHERE a.date_debut BETWEEN ? AND ?
                AND e.classe = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date_debut, $date_fin, $classe]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistiques par élève pour cette classe
        $sql = "SELECT 
                    e.id,
                    e.nom,
                    e.prenom,
                    COUNT(a.id) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM eleves e
                LEFT JOIN absences a ON e.id = a.id_eleve AND a.date_debut BETWEEN ? AND ?
                WHERE e.classe = ?
                GROUP BY e.id
                ORDER BY nb_absences DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date_debut, $date_fin, $classe]);
        $eleves_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Statistiques pour toutes les classes du professeur
        $placeholders = implode(',', array_fill(0, count($prof_classes), '?'));
        
        $sql = "SELECT 
                    COUNT(DISTINCT a.id) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    COUNT(DISTINCT a.id_eleve) as nb_eleves_absents,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM absences a
                JOIN eleves e ON a.id_eleve = e.id
                WHERE a.date_debut BETWEEN ? AND ?
                AND e.classe IN ($placeholders)";
        
        $params = array_merge([$date_debut, $date_fin], $prof_classes);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistiques par classe
        $sql = "SELECT 
                    e.classe,
                    COUNT(DISTINCT a.id) as nb_absences,
                    COUNT(DISTINCT a.id_eleve) as nb_eleves_absents,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM absences a
                JOIN eleves e ON a.id_eleve = e.id
                WHERE a.date_debut BETWEEN ? AND ?
                AND e.classe IN ($placeholders)
                GROUP BY e.classe
                ORDER BY nb_absences DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $classes_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif (isStudent()) {
    // Statistiques pour l'élève connecté
    $sql = "SELECT 
                COUNT(a.id) as nb_absences,
                SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
            FROM absences a
            WHERE a.id_eleve = ?
            AND a.date_debut BETWEEN ? AND ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $date_debut, $date_fin]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les détails de l'élève
    $stmt = $pdo->prepare("SELECT nom, prenom, classe FROM eleves WHERE id = ?");
    $stmt->execute([$user['id']]);
    $eleve = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($eleve) {
        $classe = $eleve['classe'];
    }
} elseif (isParent()) {
    // Statistiques pour les enfants du parent
    $stmt = $pdo->prepare("SELECT id_eleve FROM parents_eleves WHERE id_parent = ?");
    $stmt->execute([$user['id']]);
    $enfants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($enfants)) {
        $placeholders = implode(',', array_fill(0, count($enfants), '?'));
        
        $sql = "SELECT 
                    COUNT(a.id) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM absences a
                WHERE a.id_eleve IN ($placeholders)
                AND a.date_debut BETWEEN ? AND ?";
        
        $params = array_merge($enfants, [$date_debut, $date_fin]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistiques par enfant
        $sql = "SELECT 
                    e.id,
                    e.nom,
                    e.prenom,
                    e.classe,
                    COUNT(a.id) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)) as duree_totale_minutes
                FROM eleves e
                LEFT JOIN absences a ON e.id = a.id_eleve AND a.date_debut BETWEEN ? AND ?
                WHERE e.id IN ($placeholders)
                GROUP BY e.id
                ORDER BY e.nom, e.prenom";
        
        $params = array_merge([$date_debut, $date_fin], $enfants);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $eleves_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer la liste des classes pour le filtre
$liste_classes = [];
$etablissement_data = json_decode(file_get_contents('../login/data/etablissement.json'), true);
if (!empty($etablissement_data['classes'])) {
    foreach ($etablissement_data['classes'] as $niveau => $niveaux) {
        foreach ($niveaux as $cycle => $classes_list) {
            foreach ($classes_list as $nom_classe) {
                $liste_classes[] = $nom_classe;
            }
        }
    }
}

// Récupérer les années scolaires disponibles
$annees_scolaires = [
    'current' => 'Année scolaire en cours',
    '2024-2025' => 'Année scolaire 2024-2025',
    '2023-2024' => 'Année scolaire 2023-2024',
    '2022-2023' => 'Année scolaire 2022-2023'
];
<?php
$pageTitle = 'Statistiques des absences';
$currentPage = 'statistiques';

// CSS et JS supplémentaires
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .stats-section {
      background-color: white;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .stats-section h2 {
      font-size: 1.3rem;
      color: #333;
      margin-top: 0;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }
    .stats-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 20px;
    }
    .stats-card {
      background-color: #f9f9f9;
      border-radius: 8px;
      padding: 20px;
      display: flex;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .stats-icon {
      width: 60px;
      height: 60px;
      background-color: #e6f3ef;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 15px;
    }
    .stats-icon i { font-size: 24px; color: #009b72; }
    .stats-content h3 { margin: 0; font-size: 14px; color: #666; font-weight: normal; }
    .stats-value { font-size: 24px; font-weight: 500; color: #333; margin-top: 5px; }
    .stats-percent { font-size: 14px; color: #009b72; }
    .charts-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
      gap: 20px;
    }
    .chart-wrapper {
      background-color: #f9f9f9;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .chart-wrapper h3 { margin-top: 0; font-size: 16px; color: #444; margin-bottom: 15px; }
    canvas { width: 100%; height: 250px; }
    .stats-table { overflow-x: auto; }
    .stats-table table { width: 100%; border-collapse: collapse; }
    .stats-table th, .stats-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
    .stats-table th { background-color: #f5f5f5; font-weight: 500; color: #444; }
    .stats-table tr:hover { background-color: #f9f9f9; }
    @media (max-width: 768px) {
      .stats-cards { grid-template-columns: 1fr; }
      .charts-container { grid-template-columns: 1fr; }
    }
</style>
<?php
$extraHeadHtml = ob_get_clean();

// Sidebar personnalisée avec filtres
ob_start();
?>
        <div class="sidebar-section">
            <div class="sidebar-section-header">FILTRES</div>
            <form id="filters-form" method="get" action="statistiques.php" style="padding: 0 15px;">
              <div class="form-group" style="margin-bottom: 10px;">
                <label for="annee_scolaire" style="font-size: 12px; color: #8e9aaf;">Année scolaire</label>
                <select id="annee_scolaire" name="annee_scolaire" class="form-control" style="font-size: 13px;">
                  <?php foreach ($annees_scolaires as $key => $label): ?>
                  <option value="<?= $key ?>" <?= $annee_scolaire == $key ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group" style="margin-bottom: 10px;">
                <label for="periode" style="font-size: 12px; color: #8e9aaf;">Période</label>
                <select id="periode" name="periode" class="form-control" style="font-size: 13px;">
                  <option value="annee" <?= $periode == 'annee' ? 'selected' : '' ?>>Année complète</option>
                  <option value="trimestre_1" <?= $periode == 'trimestre_1' ? 'selected' : '' ?>>1er trimestre (Sep-Nov)</option>
                  <option value="trimestre_2" <?= $periode == 'trimestre_2' ? 'selected' : '' ?>>2e trimestre (Déc-Fév)</option>
                  <option value="trimestre_3" <?= $periode == 'trimestre_3' ? 'selected' : '' ?>>3e trimestre (Mar-Mai)</option>
                  <option value="trimestre_4" <?= $periode == 'trimestre_4' ? 'selected' : '' ?>>4e trimestre (Juin-Août)</option>
                </select>
              </div>
              
              <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && count($liste_classes) > 0): ?>
              <div class="form-group" style="margin-bottom: 10px;">
                <label for="classe" style="font-size: 12px; color: #8e9aaf;">Classe</label>
                <select id="classe" name="classe" class="form-control" style="font-size: 13px;">
                  <option value="">Toutes les classes</option>
                  <?php foreach ($liste_classes as $c): ?>
                  <option value="<?= $c ?>" <?= $classe == $c ? 'selected' : '' ?>><?= $c ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              
              <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 5px; font-size: 13px;">Appliquer les filtres</button>
            </form>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">ACTIONS</div>
            <div class="sidebar-nav">
                <a href="absences.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                    <span>Voir les absences</span>
                </a>
                <a href="retards.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-clock"></i></span>
                    <span>Voir les retards</span>
                </a>
                <?php if (canManageAbsences()): ?>
                <a href="export_stats.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-file-excel"></i></span>
                    <span>Exporter les statistiques</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
<?php
$sidebarExtraContent = ob_get_clean();
$headerExtraActions = '';
include 'includes/header.php';
?>
      
      <!-- Content -->
      <div class="content-container">
        <?php if (!$stats || (isset($stats['nb_absences']) && $stats['nb_absences'] == 0)): ?>
          <div class="no-data-message">
            <i class="fas fa-info-circle"></i>
            <p>Aucune donnée d'absence n'est disponible pour la période sélectionnée.</p>
          </div>
        <?php else: ?>
          <!-- Statistiques globales -->
          <div class="stats-section">
            <h2>Statistiques globales <?= !empty($classe) ? 'pour la classe ' . $classe : '' ?></h2>
            
            <div class="stats-cards">
              <div class="stats-card">
                <div class="stats-icon">
                  <i class="fas fa-calendar-xmark"></i>
                </div>
                <div class="stats-content">
                  <h3>Nombre d'absences</h3>
                  <div class="stats-value"><?= isset($stats['nb_absences']) ? $stats['nb_absences'] : 0 ?></div>
                </div>
              </div>
              
              <div class="stats-card">
                <div class="stats-icon">
                  <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stats-content">
                  <h3>Absences justifiées</h3>
                  <div class="stats-value"><?= isset($stats['nb_absences_justifiees']) ? $stats['nb_absences_justifiees'] : 0 ?></div>
                  <div class="stats-percent">
                    <?php
                    $pourcentage_justifiees = 0;
                    if (isset($stats['nb_absences']) && $stats['nb_absences'] > 0) {
                        $pourcentage_justifiees = round(($stats['nb_absences_justifiees'] / $stats['nb_absences']) * 100);
                    }
                    echo $pourcentage_justifiees . '%';
                    ?>
                  </div>
                </div>
              </div>
              
              <?php if (isset($stats['nb_eleves_absents'])): ?>
              <div class="stats-card">
                <div class="stats-icon">
                  <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                  <h3>Élèves concernés</h3>
                  <div class="stats-value"><?= $stats['nb_eleves_absents'] ?></div>
                </div>
              </div>
              <?php endif; ?>
              
              <div class="stats-card">
                <div class="stats-icon">
                  <i class="fas fa-clock"></i>
                </div>
                <div class="stats-content">
                  <h3>Durée totale</h3>
                  <div class="stats-value">
                    <?php
                    $duree_totale_minutes = isset($stats['duree_totale_minutes']) ? $stats['duree_totale_minutes'] : 0;
                    $heures = floor($duree_totale_minutes / 60);
                    $minutes = $duree_totale_minutes % 60;
                    echo $heures . 'h ' . $minutes . 'min';
                    ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Graphiques -->
          <div class="stats-section">
            <h2>Analyse graphique</h2>
            
            <div class="charts-container">
              <div class="chart-wrapper">
                <h3>Répartition des absences</h3>
                <canvas id="absencesTypeChart"></canvas>
              </div>
              
              <?php if (isset($classes_stats) && !empty($classes_stats)): ?>
              <div class="chart-wrapper">
                <h3>Absences par classe</h3>
                <canvas id="absencesClasseChart"></canvas>
              </div>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Détails par classe/élève -->
          <?php if (isset($classes_stats) && !empty($classes_stats)): ?>
          <div class="stats-section">
            <h2>Détails par classe</h2>
            
            <div class="stats-table">
              <table>
                <thead>
                  <tr>
                    <th>Classe</th>
                    <th>Nombre d'absences</th>
                    <th>Justifiées</th>
                    <th>% Justifiées</th>
                    <th>Élèves concernés</th>
                    <th>Durée totale</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($classes_stats as $classe_stat): ?>
                    <tr>
                      <td><?= htmlspecialchars($classe_stat['classe']) ?></td>
                      <td><?= $classe_stat['nb_absences'] ?></td>
                      <td><?= $classe_stat['nb_absences_justifiees'] ?></td>
                      <td>
                        <?php
                        $pourcentage_justifiees = 0;
                        if ($classe_stat['nb_absences'] > 0) {
                            $pourcentage_justifiees = round(($classe_stat['nb_absences_justifiees'] / $classe_stat['nb_absences']) * 100);
                        }
                        echo $pourcentage_justifiees . '%';
                        ?>
                      </td>
                      <td><?= $classe_stat['nb_eleves_absents'] ?></td>
                      <td>
                        <?php
                        $heures = floor($classe_stat['duree_totale_minutes'] / 60);
                        $minutes = $classe_stat['duree_totale_minutes'] % 60;
                        echo $heures . 'h ' . $minutes . 'min';
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (isset($eleves_stats) && !empty($eleves_stats)): ?>
          <div class="stats-section">
            <h2>Détails par élève <?= !empty($classe) ? 'de la classe ' . $classe : '' ?></h2>
            
            <div class="stats-table">
              <table>
                <thead>
                  <tr>
                    <th>Élève</th>
                    <th>Nombre d'absences</th>
                    <th>Justifiées</th>
                    <th>% Justifiées</th>
                    <th>Durée totale</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($eleves_stats as $eleve_stat): ?>
                    <tr>
                      <td><?= htmlspecialchars($eleve_stat['prenom'] . ' ' . $eleve_stat['nom']) ?></td>
                      <td><?= $eleve_stat['nb_absences'] ?></td>
                      <td><?= $eleve_stat['nb_absences_justifiees'] ?></td>
                      <td>
                        <?php
                        $pourcentage_justifiees = 0;
                        if ($eleve_stat['nb_absences'] > 0) {
                            $pourcentage_justifiees = round(($eleve_stat['nb_absences_justifiees'] / $eleve_stat['nb_absences']) * 100);
                        }
                        echo $pourcentage_justifiees . '%';
                        ?>
                      </td>
                      <td>
                        <?php
                        $heures = floor($eleve_stat['duree_totale_minutes'] / 60);
                        $minutes = $eleve_stat['duree_totale_minutes'] % 60;
                        echo $heures . 'h ' . $minutes . 'min';
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    
  <script>
    // Graphique des types d'absences (justifiées/non justifiées)
    const absencesTypeCtx = document.getElementById('absencesTypeChart').getContext('2d');
    const absencesTypeChart = new Chart(absencesTypeCtx, {
      type: 'pie',
      data: {
        labels: ['Justifiées', 'Non justifiées'],
        datasets: [{
          label: 'Répartition des absences',
          data: [
            <?= isset($stats['nb_absences_justifiees']) ? $stats['nb_absences_justifiees'] : 0 ?>,
            <?= isset($stats['nb_absences']) ? $stats['nb_absences'] - $stats['nb_absences_justifiees'] : 0 ?>
          ],
          backgroundColor: ['#4caf50', '#f44336'],
          borderColor: ['#fff', '#fff'],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'top',
          },
          tooltip: {
            callbacks: {
              label: function(tooltipItem) {
                let label = tooltipItem.label || '';
                if (label) {
                  label += ': ';
                }
                label += tooltipItem.raw;
                return label;
              }
            }
          }
        }
      }
    });
    
    // Graphique des absences par classe
    <?php if (isset($classes_stats) && !empty($classes_stats)): ?>
    const absencesClasseCtx = document.getElementById('absencesClasseChart').getContext('2d');
    const absencesClasseChart = new Chart(absencesClasseCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($classes_stats, 'classe')) ?>,
        datasets: [{
          label: 'Nombre d\'absences',
          data: <?= json_encode(array_column($classes_stats, 'nb_absences')) ?>,
          backgroundColor: '#2196f3',
          borderColor: '#1976d2',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        scales: {
          x: {
            title: {
              display: true,
              text: 'Classes'
            }
          },
          y: {
            title: {
              display: true,
              text: 'Nombre d\'absences'
            },
            beginAtZero: true
          }
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function(tooltipItem) {
                let label = tooltipItem.dataset.label || '';
                if (label) {
                  label += ': ';
                }
                label += tooltipItem.raw;
                return label;
              }
            }
          }
        }
      }
    });
    <?php endif; ?>
  </script>
<?php include 'includes/footer.php'; ?>