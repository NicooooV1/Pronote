<?php
/**
 * /new_announcement.php - Création d'une nouvelle annonce
 */

// Inclure les fichiers nécessaires
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/utils.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/csrf.php';
require_once __DIR__ . '/core/validator.php';
require_once __DIR__ . '/controllers/message.php';
require_once __DIR__ . '/models/message.php';

// Vérifier l'authentification
$user = requireAuth();

// Vérifier que l'utilisateur a le droit de créer des annonces
$canSendAnnouncement = in_array($user['type'], ['vie_scolaire', 'administrateur']);
if (!$canSendAnnouncement) {
    redirect('index.php');
}

// Définir le titre de la page
$pageTitle = 'Nouvelle annonce';

$error = '';
$success = '';

/**
 * Récupère les classes disponibles depuis la base de données
 * @return array
 */
function getAvailableClasses() {
    global $pdo;
    $allClasses = [];
    $query = $pdo->query("SELECT DISTINCT classe FROM eleves WHERE classe IS NOT NULL AND classe != '' ORDER BY classe");
    if ($query) {
        $allClasses = $query->fetchAll(PDO::FETCH_COLUMN);
    }
    return $allClasses;
}

// Traitement du formulaire d'envoi d'annonce
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $contenu = isset($_POST['contenu']) ? trim($_POST['contenu']) : '';
        $cible = isset($_POST['cible']) ? $_POST['cible'] : '';
        $parametres = isset($_POST['parametres']) ? $_POST['parametres'] : [];
        
        if (empty($titre)) {
            throw new Exception("Le titre de l'annonce est obligatoire");
        }
        
        // Vérifier la longueur du titre
        if (mb_strlen($titre) > 100) {
            throw new Exception("Le titre ne peut pas dépasser 100 caractères");
        }
        
        if (empty($contenu)) {
            throw new Exception("Le contenu de l'annonce ne peut pas être vide");
        }
        
        if (empty($cible)) {
            throw new Exception("Veuillez sélectionner une cible pour l'annonce");
        }
        
        // Récupérer les destinataires selon la cible
        $participants = [];
        
        switch ($cible) {
            case 'tous':
                // Tous les utilisateurs
                $tables = ['eleves', 'parents', 'professeurs', 'vie_scolaire', 'administrateurs'];
                foreach ($tables as $table) {
                    $type = rtrim($table, 's'); // Enlever le 's' final pour obtenir le type
                    
                    $query = $pdo->query("SELECT id FROM $table");
                    $users = $query->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($users as $userId) {
                        $participants[] = ['id' => $userId, 'type' => $type];
                    }
                }
                break;
                
            case 'personnel':
                // Uniquement le personnel (professeurs, vie scolaire, administrateurs)
                $tables = ['professeurs', 'vie_scolaire', 'administrateurs'];
                foreach ($tables as $table) {
                    $type = rtrim($table, 's');
                    
                    $query = $pdo->query("SELECT id FROM $table");
                    $users = $query->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($users as $userId) {
                        $participants[] = ['id' => $userId, 'type' => $type];
                    }
                }
                break;
                
            case 'parents':
                // Tous les parents
                $query = $pdo->query("SELECT id FROM parents");
                $parents = $query->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($parents as $parentId) {
                    $participants[] = ['id' => $parentId, 'type' => 'parent'];
                }
                break;
                
            case 'eleves':
                // Tous les élèves
                $query = $pdo->query("SELECT id FROM eleves");
                $eleves = $query->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($eleves as $eleveId) {
                    $participants[] = ['id' => $eleveId, 'type' => 'eleve'];
                }
                break;
                
            case 'classes':
                // Classes spécifiques
                if (empty($parametres['classes'])) {
                    throw new Exception("Veuillez sélectionner au moins une classe");
                }
                
                $classes = $parametres['classes'];
                $includeParents = isset($parametres['include_parents']) && $parametres['include_parents'] === 'on';
                
                foreach ($classes as $classe) {
                    // Élèves de la classe
                    $stmt = $pdo->prepare("SELECT id FROM eleves WHERE classe = ?");
                    $stmt->execute([$classe]);
                    $eleves = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($eleves as $eleveId) {
                        $participants[] = ['id' => $eleveId, 'type' => 'eleve'];
                    }
                    
                    // Parents des élèves de la classe si demandé
                    if ($includeParents) {
                        // Dans la nouvelle structure, nous n'avons pas de relation explicite parents-élèves
                        // On récupère simplement tous les parents
                        $stmt = $pdo->prepare("SELECT id FROM parents WHERE est_parent_eleve = 'oui'");
                        $stmt->execute();
                        $parents = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($parents as $parentId) {
                            $participants[] = ['id' => $parentId, 'type' => 'parent'];
                        }
                    }
                }
                break;
        }
        
        if (empty($participants)) {
            throw new Exception("Aucun destinataire n'a pu être identifié avec les critères sélectionnés");
        }
        
        // Appeler la fonction d'envoi d'annonce
        $notificationObligatoire = isset($_POST['notification_obligatoire']) && $_POST['notification_obligatoire'] === 'on';
        $filesData = isset($_FILES['attachments']) ? $_FILES['attachments'] : [];
        
        $result = handleSendAnnouncement(
            $user,
            $titre,
            $contenu,
            $participants,
            $notificationObligatoire,
            $filesData
        );
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer la liste des classes disponibles
$classes = getAvailableClasses();

// Inclure l'en-tête
include 'templates/header.php';
?>

<div class="content">
    <?php if (!empty($success)): ?>
    <div class="alert success">
        <p><?= htmlspecialchars($success) ?></p>
        <div style="margin-top: 15px">
            <a href="index.php" class="btn primary">Retour à la messagerie</a>
        </div>
    </div>
    <?php else: ?>
    
    <?php if (!empty($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="form-container">
        <div class="form-header">
            <div class="form-icon announcement">
                <i class="fas fa-bullhorn"></i>
            </div>
            <h2 class="form-title">Créer une nouvelle annonce importante</h2>
        </div>
        
        <div class="alert info">
            <i class="fas fa-info-circle"></i> Les annonces importantes sont des messages qui sont mis en évidence dans la messagerie des destinataires.
        </div>
        
        <form method="post" enctype="multipart/form-data" id="announcementForm">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="titre">Titre de l'annonce</label>
                <input type="text" name="titre" id="titre" value="<?= htmlspecialchars($titre) ?>" required maxlength="100">
                <div id="title-counter" class="text-muted small">0/100 caractères</div>
            </div>
            
            <div class="form-group">
                <label for="contenu">Message de l'annonce</label>
                <textarea name="contenu" id="contenu" required><?= htmlspecialchars($contenu) ?></textarea>
                <div id="char-counter" class="text-muted small"></div>
            </div>
            
            <div class="form-group">
                <label for="cible">Destinataires</label>
                <select name="cible" id="cible" required onchange="toggleTargetOptions()">
                <option value="">Sélectionner une cible</option>
                    <option value="tous">Tous les utilisateurs</option>
                    <option value="personnel">Personnel uniquement</option>
                    <option value="parents">Tous les parents</option>
                    <option value="eleves">Tous les élèves</option>
                    <option value="classes">Classes spécifiques</option>
                </select>
                
                <div class="target-options" id="target-classes" style="display: none;">
                    <div class="form-group">
                        <label>Sélectionner les classes</label>
                        <div class="target-classes">
                            <?php foreach ($classes as $classe): ?>
                            <div class="class-item">
                                <input type="checkbox" name="parametres[classes][]" id="classe_<?= htmlspecialchars($classe) ?>" 
                                       value="<?= htmlspecialchars($classe) ?>">
                                <label for="classe_<?= htmlspecialchars($classe) ?>"><?= htmlspecialchars($classe) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <label class="checkbox-container" style="margin-top: 10px;">
                            <input type="checkbox" name="parametres[include_parents]">
                            <span class="checkmark"></span>
                            Inclure les parents des élèves
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="attachments">Pièces jointes</label>
                <div class="file-upload">
                    <input type="file" name="attachments[]" id="attachments" multiple>
                    <label for="attachments">
                        <i class="fas fa-paperclip"></i> Sélectionner des fichiers
                    </label>
                </div>
                <div id="file-list"></div>
                <div class="text-muted small">Taille maximale: 1Mo par fichier</div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-container">
                    <input type="checkbox" name="notification_obligatoire" checked>
                    <span class="checkmark"></span>
                    Notification obligatoire (les destinataires devront lire l'annonce)
                </label>
            </div>
            
            <div class="form-footer">
                <div class="form-actions">
                    <button type="submit" class="btn warning">
                        <i class="fas fa-bullhorn"></i> Envoyer l'annonce importante
                    </button>
                    <a href="index.php" class="btn cancel">Annuler</a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php
// Inclure le pied de page
include 'templates/footer.php';
?>