<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Inclusion des fichiers nécessaires avec chemins absolus
require_once __DIR__ . '/../API/auth_central.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Vérifier que l'utilisateur est connecté et autorisé avec le système centralisé
if (!isLoggedIn() || !canManageAbsences()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Récupérer les informations de l'utilisateur connecté via le système centralisé
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Configuration de la page
$pageTitle = 'Justificatifs d\'absence';
$currentPage = 'justificatifs';

// Définir les filtres par défaut avec validation
$date_debut = filter_input(INPUT_GET, 'date_debut', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-30 days'));
$date_fin = filter_input(INPUT_GET, 'date_fin', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
$classe = filter_input(INPUT_GET, 'classe', FILTER_SANITIZE_STRING) ?: '';
$traite = filter_input(INPUT_GET, 'traite', FILTER_SANITIZE_STRING) ?: '';

// Formatage des dates pour l'affichage convivial
$date_debut_formattee = date('d/m/Y', strtotime($date_debut));
$date_fin_formattee = date('d/m/Y', strtotime($date_fin));

// Vérifier si la table justificatifs existe
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'justificatifs'");
    if ($tableCheck->rowCount() == 0) {
        // Créer la table si elle n'existe pas
        createJustificatifsTableIfNotExists($pdo);
    } else {
        // Vérifier si la colonne date_soumission existe
        $columnCheck = $pdo->query("SHOW COLUMNS FROM justificatifs LIKE 'date_soumission'");
        if ($columnCheck->rowCount() == 0) {
            // Essayer de trouver une colonne similaire (date_depot ou autre)
            $columns = $pdo->query("DESCRIBE justificatifs");
            $dateColumns = [];
            while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
                if (strpos($col['Field'], 'date') !== false) {
                    $dateColumns[] = $col['Field'];
                }
            }
            
            // Si une colonne de date est trouvée, l'utiliser; sinon, créer la colonne
            if (!empty($dateColumns)) {
                $dateColumn = $dateColumns[0]; // Utiliser la première colonne de date trouvée
            } else {
                // Ajouter la colonne date_soumission
                $pdo->exec("ALTER TABLE justificatifs ADD COLUMN date_soumission DATE DEFAULT CURRENT_DATE");
                $dateColumn = 'date_soumission';
            }
            
            // Journaliser le changement
            error_log("Colonne date_soumission manquante dans la table justificatifs, utilisation de la colonne $dateColumn à la place");
        } else {
            $dateColumn = 'date_soumission';
        }
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la vérification de la table justificatifs: " . $e->getMessage());
    // Par défaut, utiliser une colonne qui existe probablement
    $dateColumn = 'date_depot';
}

// Récupérer la liste des justificatifs
$justificatifs = [];

if (isAdmin() || isVieScolaire()) {
    try {
        // Construire la requête en utilisant la colonne de date déterminée avec paramètres nommés
        $sql = "SELECT j.*, e.nom, e.prenom, e.classe 
                FROM justificatifs j 
                JOIN eleves e ON j.id_eleve = e.id 
                WHERE j.$dateColumn BETWEEN :date_debut AND :date_fin ";
                
        $params = [
            ':date_debut' => $date_debut,
            ':date_fin' => $date_fin
        ];
        
        if (!empty($classe)) {
            $sql .= "AND e.classe = :classe ";
            $params[':classe'] = $classe;
        }
        
        if ($traite !== '') {
            $sql .= "AND j.traite = :traite ";
            $params[':traite'] = ($traite === 'oui') ? 1 : 0; // Conversion explicite en booléen
        }
        
        $sql .= "ORDER BY j.$dateColumn DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $justificatifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Journalisation et gestion de l'erreur
        \Pronote\Logging\error("Erreur lors de la récupération des justificatifs: " . $e->getMessage());
        $justificatifs = [];
    }
}

// Traitement du formulaire de justification avec validation CSRF
$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'traiter') {
    // Vérifier le jeton CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $id_justificatif = filter_input(INPUT_POST, 'id_justificatif', FILTER_VALIDATE_INT);
        if (!$id_justificatif) {
            $erreur = "Identifiant de justificatif invalide";
        } else {
            $approuve = isset($_POST['approuve']) ? 1 : 0;
            $commentaire = filter_input(INPUT_POST, 'commentaire', FILTER_SANITIZE_STRING) ?: '';
            
            // Mise à jour du justificatif avec paramètres nommés
            $stmt = $pdo->prepare("
                UPDATE justificatifs 
                SET traite = 1, 
                    approuve = :approuve, 
                    commentaire_admin = :commentaire, 
                    date_traitement = NOW(), 
                    traite_par = :traite_par 
                WHERE id = :id
            ");
            
            if ($stmt->execute([
                ':approuve' => $approuve, 
                ':commentaire' => $commentaire, 
                ':traite_par' => $user_fullname, 
                ':id' => $id_justificatif
            ])) {
                // Si approuvé, mettre à jour l'absence
                if ($approuve && isset($_POST['id_absence'])) {
                    $id_absence = intval($_POST['id_absence']);
                    $stmt = $pdo->prepare("UPDATE absences SET justifie = 1 WHERE id = ?");
                    $stmt->execute([$id_absence]);
                }
                
                $message = "Le justificatif a été traité avec succès.";
                // Recharger la liste des justificatifs
                header('Location: justificatifs.php?success=1');
                exit;
            } else {
                $erreur = "Une erreur est survenue lors du traitement du justificatif.";
            }
        }
    }
}

// Récupérer la liste des classes pour le filtre
$classes = [];
try {
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
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des classes: " . $e->getMessage());
}

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Bannière de bienvenue pour le contexte des justificatifs -->
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Gestion des Justificatifs</h2>
        <p>Consultez et traitez les justificatifs d'absences soumis par les élèves et leurs parents.</p>
    </div>
    <div class="welcome-icon">
        <i class="fas fa-file-alt"></i>
    </div>
</div>

<!-- Barre de filtres -->
<div class="filters-bar">
    <form id="filter-form" class="filter-form" method="get" action="justificatifs.php">
        <div class="filter-item">
            <label for="date_debut" class="filter-label">Du</label>
            <input type="date" id="date_debut" name="date_debut" value="<?= $date_debut ?>" max="<?= date('Y-m-d') ?>">
        </div>
        
        <div class="filter-item">
            <label for="date_fin" class="filter-label">Au</label>
            <input type="date" id="date_fin" name="date_fin" value="<?= $date_fin ?>" max="<?= date('Y-m-d') ?>">
        </div>
        
        <div class="filter-item">
            <label for="classe" class="filter-label">Classe</label>
            <select id="classe" name="classe">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $classe === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-item">
            <label for="traite" class="filter-label">Statut</label>
            <select id="traite" name="traite">
                <option value="">Tous</option>
                <option value="oui" <?= $traite === 'oui' ? 'selected' : '' ?>>Traités</option>
                <option value="non" <?= $traite === 'non' ? 'selected' : '' ?>>Non traités</option>
            </select>
        </div>
        
        <div class="filter-buttons">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrer
            </button>
            <a href="justificatifs.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Réinitialiser
            </a>
        </div>
    </form>
</div>

<!-- Contenu principal -->
<div class="content-container">
    <div class="content-header">
        <h2>Justificatifs du <?= $date_debut_formattee ?> au <?= $date_fin_formattee ?></h2>
        <div class="content-actions">
            <a href="export_justificatifs.php?format=excel&<?= http_build_query($_GET) ?>" class="btn btn-outline">
                <i class="fas fa-file-excel"></i> Exporter
            </a>
        </div>
    </div>

    <div class="content-body">
        <?php if (empty($justificatifs)): ?>
            <div class="no-data-message">
                <i class="fas fa-file-alt"></i>
                <p>Aucun justificatif ne correspond aux critères sélectionnés.</p>
            </div>
        <?php else: ?>
            <div class="justificatifs-list absences-list">
                <div class="list-header">
                    <div class="list-row header-row">
                        <div class="list-cell">Élève</div>
                        <div class="list-cell">Classe</div>
                        <div class="list-cell">Date de dépôt</div>
                        <div class="list-cell">Période</div>
                        <div class="list-cell">Motif</div>
                        <div class="list-cell">Statut</div>
                        <div class="list-actions">Actions</div>
                    </div>
                </div>
                
                <div class="list-body">
                    <?php foreach ($justificatifs as $justificatif): ?>
                        <div class="list-row justificatif-row <?= $justificatif['traite'] ? 'traite' : 'non-traite' ?>">
                            <div class="list-cell">
                                <strong><?= htmlspecialchars($justificatif['nom']) ?></strong> <?= htmlspecialchars($justificatif['prenom']) ?>
                            </div>
                            <div class="list-cell"><?= htmlspecialchars($justificatif['classe']) ?></div>
                            <div class="list-cell">
                                <?= isset($justificatif[$dateColumn]) ? date('d/m/Y', strtotime($justificatif[$dateColumn])) : 'N/A' ?>
                            </div>
                            <div class="list-cell">
                                Du <?= date('d/m/Y', strtotime($justificatif['date_debut_absence'])) ?>
                                <br>
                                au <?= date('d/m/Y', strtotime($justificatif['date_fin_absence'])) ?>
                            </div>
                            <div class="list-cell"><?= htmlspecialchars($justificatif['motif'] ?? 'Non spécifié') ?></div>
                            <div class="list-cell">
                                <?php if ($justificatif['traite']): ?>
                                    <span class="badge badge-success">Traité</span>
                                    <span class="badge <?= $justificatif['approuve'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $justificatif['approuve'] ? 'Approuvé' : 'Rejeté' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">En attente</span>
                                <?php endif; ?>
                            </div>
                            <div class="list-actions">
                                <div class="action-buttons">
                                    <a href="details_justificatif.php?id=<?= $justificatif['id'] ?>" class="btn-icon" title="Voir les détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (!$justificatif['traite']): ?>
                                    <a href="traiter_justificatif.php?id=<?= $justificatif['id'] ?>" class="btn-icon" title="Traiter">
                                        <i class="fas fa-check-circle"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Inclure le pied de page
include 'includes/footer.php';
ob_end_flush();
?>