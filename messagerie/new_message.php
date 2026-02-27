<?php
/**
 * /new_message.php - Création d'un nouveau message
 */

// Démarrer la mise en mémoire tampon
ob_start();

// Inclure les fichiers nécessaires
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/utils.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/csrf.php';
require_once __DIR__ . '/core/validator.php';
require_once __DIR__ . '/models/participant.php';
require_once __DIR__ . '/models/conversation.php';
require_once __DIR__ . '/controllers/conversation.php';
require_once __DIR__ . '/models/message.php';

// Vérifier l'authentification
$user = requireAuth();

// Définir le titre de la page
$pageTitle = 'Nouveau message';

$error = '';
$success = '';

// Variables pour conserver les données du formulaire en cas d'erreur
$destinataires = isset($_POST['destinataires']) ? $_POST['destinataires'] : [];
$titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
$contenu = isset($_POST['contenu']) ? trim($_POST['contenu']) : '';
$importance = isset($_POST['importance']) ? $_POST['importance'] : 'normal';

// Traitement du formulaire d'envoi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $errors = [];
    
    try {
        if (empty($destinataires)) {
            $errors[] = "Veuillez sélectionner au moins un destinataire.";
        }
        
        if (empty($titre)) {
            $errors[] = "Le titre est obligatoire";
        }
        
        // Vérifier la longueur du titre
        if (mb_strlen($titre) > 100) {
            $errors[] = "Le titre ne peut pas dépasser 100 caractères";
        }
        
        if (empty($contenu)) {
            $errors[] = "Le message ne peut pas être vide";
        }
        
        // Vérifier la longueur maximale du message
        $maxLength = 10000;
        if (mb_strlen($contenu) > $maxLength) {
            $errors[] = "Votre message est trop long (maximum $maxLength caractères)";
        }
        
        // Traitement des destinataires
        $participants = [];
        foreach ($destinataires as $dest) {
            list($destType, $destId) = explode('_', $dest);
            
            // Vérification pour éviter l'envoi à soi-même
            if ($destId == $user['id'] && $destType == $user['type']) {
                $errors[] = "Vous ne pouvez pas vous envoyer un message à vous-même";
            }
            
            $participants[] = ['id' => $destId, 'type' => $destType];
        }
        
        if (empty($errors)) {
            // Création de la conversation
            $result = handleCreateConversation(
                $titre, 
                count($participants) > 1 ? 'groupe' : 'individuelle',
                $user,
                $participants
            );
            
            if ($result['success']) {
                $convId = $result['convId'];
                
                // Envoi du message
                $filesData = isset($_FILES['attachments']) ? $_FILES['attachments'] : [];
                
                // Vérifier si l'utilisateur peut définir l'importance
                if (!canSetMessageImportance($user['type'])) {
                    $importance = 'normal';
                }
                
                $result = handleSendMessage(
                    $convId,
                    $user,
                    $contenu,
                    $importance,
                    null, // Parent message ID
                    $filesData
                );
                
                if ($result['success']) {
                    $success = "Votre message a été envoyé avec succès";
                    
                    // Réinitialiser les variables pour un nouveau message
                    $destinataires = [];
                    $titre = '';
                    $contenu = '';
                    $importance = 'normal';
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = $result['message'];
            }
        } else {
            $error = implode('<br>', $errors);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Ne pas réinitialiser les variables pour conserver les données saisies
    }
}

// Récupérer la liste des destinataires potentiels
$destinataires_disponibles = [];

// Élèves (en excluant l'utilisateur actuel)
$query = $pdo->prepare("SELECT id, CONCAT(prenom, ' ', nom, ' (', classe, ')') as nom_complet 
                        FROM eleves 
                        WHERE NOT (id = ? AND ? = 'eleve')
                        ORDER BY nom");
$query->execute([$user['id'], $user['type']]);
if ($query) {
    $destinataires_disponibles['eleve'] = $query->fetchAll();
}

// Parents (en excluant l'utilisateur actuel)
$query = $pdo->prepare("SELECT id, CONCAT(prenom, ' ', nom) as nom_complet 
                        FROM parents 
                        WHERE NOT (id = ? AND ? = 'parent')
                        ORDER BY nom");
$query->execute([$user['id'], $user['type']]);
if ($query) {
    $destinataires_disponibles['parent'] = $query->fetchAll();
}

// Professeurs (en excluant l'utilisateur actuel)
$query = $pdo->prepare("SELECT id, CONCAT(prenom, ' ', nom, ' (', matiere, ')') as nom_complet 
                        FROM professeurs 
                        WHERE NOT (id = ? AND ? = 'professeur')
                        ORDER BY nom");
$query->execute([$user['id'], $user['type']]);
if ($query) {
    $destinataires_disponibles['professeur'] = $query->fetchAll();
}

// Vie scolaire (en excluant l'utilisateur actuel)
$query = $pdo->prepare("SELECT id, CONCAT(prenom, ' ', nom) as nom_complet 
                        FROM vie_scolaire 
                        WHERE NOT (id = ? AND ? = 'vie_scolaire')
                        ORDER BY nom");
$query->execute([$user['id'], $user['type']]);
if ($query) {
    $destinataires_disponibles['vie_scolaire'] = $query->fetchAll();
}

// Administrateurs (en excluant l'utilisateur actuel)
$query = $pdo->prepare("SELECT id, CONCAT(prenom, ' ', nom) as nom_complet 
                        FROM administrateurs 
                        WHERE NOT (id = ? AND ? = 'administrateur')
                        ORDER BY nom");
$query->execute([$user['id'], $user['type']]);
if ($query) {
    $destinataires_disponibles['administrateur'] = $query->fetchAll();
}

/**
 * Obtient le label du type de destinataire
 * @param string $type Type de destinataire
 * @return string Label formaté
 */
function getRecipientTypeLabel($type) {
    $labels = [
        'eleve' => 'Élèves',
        'parent' => 'Parents',
        'professeur' => 'Professeurs',
        'vie_scolaire' => 'Vie scolaire',
        'administrateur' => 'Administrateurs'
    ];
    return $labels[$type] ?? ucfirst($type);
}

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
            <div class="form-icon message">
                <i class="fas fa-envelope"></i>
            </div>
            <h2 class="form-title">Composer un nouveau message</h2>
        </div>
        
        <form method="post" enctype="multipart/form-data" id="messageForm">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="destinataires">Destinataires</label>
                <div class="multiselect-container">
                    <div class="search-box">
                        <input type="text" id="search-recipients" placeholder="Rechercher des destinataires..." aria-label="Rechercher des destinataires">
                    </div>
                    
                    <div class="recipient-list">
                        <div id="no-results-message" style="display: none; text-align: center; padding: 15px; color: #6c757d;">
                            Aucun résultat pour cette recherche
                        </div>
                        
                        <?php foreach ($destinataires_disponibles as $type => $liste): ?>
                        <?php if (!empty($liste)): ?>
                        <div class="recipient-category" id="category-<?= $type ?>" data-type="<?= $type ?>">
                            <div class="category-title">
                                <?= getRecipientTypeLabel($type) ?> 
                                <span class="category-count">(<?= count($liste) ?>)</span>
                                <div class="category-actions">
                                    <a href="javascript:void(0)" onclick="selectAllInCategory('category-<?= $type ?>')">Tout sélectionner</a>
                                    <a href="javascript:void(0)" onclick="deselectAllInCategory('category-<?= $type ?>')">Tout désélectionner</a>
                                </div>
                            </div>
                            <div class="recipient-items">
                                <?php foreach ($liste as $dest): ?>
                                    <div class="recipient-item">
                                        <input type="checkbox" name="destinataires[]" id="dest_<?= $type ?>_<?= $dest['id'] ?>" 
                                            value="<?= $type ?>_<?= $dest['id'] ?>" 
                                            onchange="updateSelectedRecipients()"
                                            <?= in_array($type.'_'.$dest['id'], $destinataires) ? 'checked' : '' ?>>
                                        <label for="dest_<?= $type ?>_<?= $dest['id'] ?>"><?= htmlspecialchars($dest['nom_complet']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="selected-recipients" id="selected-recipients-container">
                        <!-- Les tags de destinataires sélectionnés apparaîtront ici -->
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="titre">Titre</label>
                <input type="text" name="titre" id="titre" value="<?= htmlspecialchars($titre) ?>" required maxlength="100">
                <div id="title-counter" class="text-muted small">0/100 caractères</div>
            </div>
            
            <div class="form-group">
                <label for="contenu">Message</label>
                <textarea name="contenu" id="contenu" required><?= htmlspecialchars($contenu) ?></textarea>
                <div id="char-counter" class="text-muted small"></div>
            </div>
            
            <div class="options-group">
                <?php if (canSetMessageImportance($user['type'])): ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="importance">Importance</label>
                    <select name="importance" id="importance">
                        <option value="normal" <?= $importance === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="important" <?= $importance === 'important' ? 'selected' : '' ?>>Important</option>
                        <option value="urgent" <?= $importance === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </div>
                <?php endif; ?>
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
            
            <div class="form-footer">
                <div class="form-actions">
                    <button type="submit" class="btn primary">Envoyer</button>
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