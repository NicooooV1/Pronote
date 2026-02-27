<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Inclusion de l'API centralisée
require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/functions.php';

// Vérifier que l'utilisateur est connecté de façon centralisée
if (!isLoggedIn()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Récupérer les informations de l'utilisateur connecté via le système centralisé
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Définir les filtres par défaut avec validation des entrées
$date_debut = filter_input(INPUT_GET, 'date_debut', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-30 days'));
$date_fin = filter_input(INPUT_GET, 'date_fin', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
$classe = filter_input(INPUT_GET, 'classe', FILTER_SANITIZE_STRING) ?: '';
$justifie = filter_input(INPUT_GET, 'justifie', FILTER_SANITIZE_STRING) ?: '';

// Récupérer la liste des retards selon le rôle de l'utilisateur
$retards = [];

if (isAdmin() || isVieScolaire()) {
    // Administrateurs et vie scolaire voient tous les retards
    if (!empty($classe)) {
        $retards = getRetardsClasse($pdo, $classe, $date_debut, $date_fin);
    } else {
        // Requête pour tous les retards
        $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
               
                JOIN eleves e ON r.id_eleve = e.id 
                WHERE ((r.date_retard BETWEEN ? AND ?) OR 
                       (DATE(r.date_retard) BETWEEN ? AND ?))";
                
        if ($justifie !== '') {
            $sql .= "AND r.justifie = ? ";
            $params = [$date_debut, $date_fin, $date_debut, $date_fin, $justifie === 'oui'];
        } else {
            $params = [$date_debut, $date_fin, $date_debut, $date_fin];
        }
        
        $sql .= "ORDER BY r.date_retard DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $retards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif (isTeacher()) {
    // Récupérer les classes du professeur avec requête préparée
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.nom_classe as classe
        FROM professeur_classes c
        WHERE c.id_professeur = ?
    ");
    $stmt->execute([$user['id']]);
    $prof_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Si aucune classe trouvée, utiliser un tableau vide
    if (empty($prof_classes)) {
        $prof_classes = [];
    }
    
    if (!empty($classe) && in_array($classe, $prof_classes)) {
        $retards = getRetardsClasse($pdo, $classe, $date_debut, $date_fin);
    } else {
        // Tous les retards des classes du professeur - Gestion sécurisée des placeholders
        if (empty($prof_classes)) {
            // Aucune classe à afficher
            $retards = [];
        } else {
            // Construire la requête de manière sécurisée
            $placeholders = implode(',', array_fill(0, count($prof_classes), '?'));
            $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
                    FROM retards r 
                    JOIN eleves e ON r.id_eleve = e.id 
                    WHERE e.classe IN ($placeholders) 
                    AND ((r.date_retard BETWEEN ? AND ?) OR 
                         (DATE(r.date_retard) BETWEEN ? AND ?))";
                
            $params = array_merge($prof_classes, [$date_debut, $date_fin, $date_debut, $date_fin]);
            
            if ($justifie !== '') {
                $sql .= " AND r.justifie = ?";
                $params[] = ($justifie === 'oui') ? 1 : 0; // Convertir explicitement en booléen
            }
            
            $sql .= " ORDER BY e.classe, e.nom, e.prenom, r.date_retard DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $retards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} elseif (isStudent()) {
    // Élèves voient leurs propres retards
    $retards = getRetardsEleve($pdo, $user['id'], $date_debut, $date_fin);
} elseif (isParent()) {
    // Parents voient les retards de leurs enfants
    $stmt = $pdo->prepare("SELECT id_eleve FROM parents_eleves WHERE id_parent = ?");
    $stmt->execute([$user['id']]);
    $enfants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($enfants)) {
        $placeholders = implode(',', array_fill(0, count($enfants), '?'));
        $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
                FROM retards r 
                JOIN eleves e ON r.id_eleve = e.id 
                WHERE r.id_eleve IN ($placeholders) 
                AND ((r.date_retard BETWEEN ? AND ?) OR 
                     (DATE(r.date_retard) BETWEEN ? AND ?))";
                
        if ($justifie !== '') {
            $sql .= "AND r.justifie = ? ";
            $params = array_merge($enfants, [$date_debut, $date_fin, $date_debut, $date_fin, $justifie === 'oui']);
        } else {
            $params = array_merge($enfants, [$date_debut, $date_fin, $date_debut, $date_fin]);
        }
        
        $sql .= "ORDER BY e.nom, e.prenom, r.date_retard DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $retards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer la liste des classes pour le filtre
$classes = [];
$etablissement_data = json_decode(file_get_contents('../login/data/etablissement.json'), true);
if (!empty($etablissement_data['classes'])) {
    foreach ($etablissement_data['classes'] as $niveau => $niveaux) {
        foreach ($niveaux as $cycle => $liste_classes) {
            foreach ($liste_classes as $nom_classe) {
                $classes[] = $nom_classe;
            }
        }
    }
}
<?php
$pageTitle = 'Gestion des retards';
$currentPage = 'retards';

// Sidebar personnalisée avec filtres
ob_start();
?>
        <div class="sidebar-section">
            <div class="sidebar-section-header">FILTRES</div>
            <form id="filters-form" method="get" action="retards.php" style="padding: 0 15px;">
              <div class="form-group" style="margin-bottom: 10px;">
                <label for="date_debut" style="font-size: 12px; color: #8e9aaf;">Du</label>
                <input type="date" id="date_debut" name="date_debut" value="<?= $date_debut ?>" max="<?= date('Y-m-d') ?>" class="form-control" style="font-size: 13px;">
              </div>
              
              <div class="form-group" style="margin-bottom: 10px;">
                <label for="date_fin" style="font-size: 12px; color: #8e9aaf;">Au</label>
                <input type="date" id="date_fin" name="date_fin" value="<?= $date_fin ?>" max="<?= date('Y-m-d') ?>" class="form-control" style="font-size: 13px;">
              </div>
              
              <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
              <div class="form-group" style="margin-bottom: 10px;">
                <label for="classe" style="font-size: 12px; color: #8e9aaf;">Classe</label>
                <select id="classe" name="classe" class="form-control" style="font-size: 13px;">
                  <option value="">Toutes les classes</option>
                  <?php foreach ($classes as $c): ?>
                  <option value="<?= $c ?>" <?= $classe == $c ? 'selected' : '' ?>><?= $c ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              
              <div class="form-group" style="margin-bottom: 10px;">
                <label for="justifie" style="font-size: 12px; color: #8e9aaf;">Justification</label>
                <select id="justifie" name="justifie" class="form-control" style="font-size: 13px;">
                  <option value="">Toutes</option>
                  <option value="oui" <?= $justifie == 'oui' ? 'selected' : '' ?>>Justifiés</option>
                  <option value="non" <?= $justifie == 'non' ? 'selected' : '' ?>>Non justifiés</option>
                </select>
              </div>
              
              <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 5px; font-size: 13px;">Appliquer les filtres</button>
            </form>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">ACTIONS</div>
            <div class="sidebar-nav">
                <?php if (canManageAbsences()): ?>
                <a href="ajouter_retard.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
                    <span>Ajouter un retard</span>
                </a>
                <?php endif; ?>
                <a href="absences.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                    <span>Voir les absences</span>
                </a>
                <?php if (canManageAbsences()): ?>
                <a href="justificatifs.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-file-alt"></i></span>
                    <span>Justificatifs</span>
                </a>
                <?php endif; ?>
                <a href="statistiques.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                    <span>Statistiques</span>
                </a>
            </div>
        </div>
<?php
$sidebarExtraContent = ob_get_clean();
$headerExtraActions = '';
include 'includes/header.php';
?>
      
      <!-- Content -->
      <div class="content-container">
        <?php if (empty($retards)): ?>
          <div class="no-data-message">
            <i class="fas fa-info-circle"></i>
            <p>Aucun retard ne correspond aux critères sélectionnés.</p>
          </div>
        <?php else: ?>
          <!-- Liste des retards -->
          <div class="absences-list">
            <div class="list-header">
              <div class="list-row header-row">
                <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                  <div class="list-cell header-cell">Élève</div>
                  <div class="list-cell header-cell">Classe</div>
                <?php endif; ?>
                <div class="list-cell header-cell">Date</div>
                <div class="list-cell header-cell">Durée</div>
                <div class="list-cell header-cell">Motif</div>
                <div class="list-cell header-cell">Justifié</div>
                <div class="list-cell header-cell">Actions</div>
              </div>
            </div>
            
            <div class="list-body">
              <?php foreach ($retards as $retard): ?>
                <div class="list-row retard-row <?= $retard['justifie'] ? 'justified' : 'not-justified' ?>">
                  <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                    <div class="list-cell">
                      <?= htmlspecialchars($retard['nom'] . ' ' . $retard['prenom']) ?>
                    </div>
                    <div class="list-cell">
                      <?= htmlspecialchars($retard['classe']) ?>
                    </div>
                  <?php endif; ?>
                  <div class="list-cell">
                    <?= (new DateTime($retard['date']))->format('d/m/Y H:i') ?>
                  </div>
                  <div class="list-cell">
                    <?= $retard['duree'] ?> min
                  </div>
                  <div class="list-cell">
                    <?= !empty($retard['motif']) ? htmlspecialchars($retard['motif']) : '<em>Non spécifié</em>' ?>
                  </div>
                  <div class="list-cell">
                    <?php if ($retard['justifie']): ?>
                      <span class="badge badge-success">Oui</span>
                    <?php else: ?>
                      <span class="badge badge-danger">Non</span>
                    <?php endif; ?>
                  </div>
                  <div class="list-cell">
                    <div class="action-buttons">
                      <a href="details_retard.php?id=<?= $retard['id'] ?>" class="btn-icon" title="Voir les détails">
                        <i class="fas fa-eye"></i>
                      </a>
                      <?php if (canManageAbsences()): ?>
                        <a href="modifier_retard.php?id=<?= $retard['id'] ?>" class="btn-icon" title="Modifier">
                          <i class="fas fa-edit"></i>
                        </a>
                        <a href="supprimer_retard.php?id=<?= $retard['id'] ?>" class="btn-icon btn-danger" title="Supprimer" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce retard ?');">
                          <i class="fas fa-trash"></i>
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        
        <!-- Afficher les erreurs de façon conviviale -->
        <?php if (isset($error_message)): ?>
          <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_message) ?>
          </div>
        <?php endif; ?>
      </div>
<?php include 'includes/footer.php'; ob_end_flush(); ?>