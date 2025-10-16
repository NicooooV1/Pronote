<?php
/**
 * Affichage et gestion d'une conversation
 */

// Charger la configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/utils.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/models/conversation.php';
require_once __DIR__ . '/models/message.php';
require_once __DIR__ . '/models/participant.php';
require_once __DIR__ . '/controllers/conversation.php';
require_once __DIR__ . '/controllers/message.php';
require_once __DIR__ . '/controllers/participant.php';

// Vérifier l'authentification
$user = requireAuth();

// Récupérer l'ID de la conversation
$convId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$convId) {
    redirect('index.php');
}

$error = '';
$success = '';
$messageContent = '';

try {
    // Vérifier l'accès à la conversation
    $conversation = getConversationInfo($convId);
    
    if (!$conversation) {
        throw new Exception("La conversation n'existe pas");
    }
    
    // Vérifier les permissions
    $participantInfo = getParticipantInfo($convId, $user['id'], $user['type']);
    
    if (!$participantInfo) {
        throw new Exception("Vous n'êtes pas autorisé à accéder à cette conversation");
    }
    
    $isDeleted = $participantInfo['is_deleted'] == 1;
    $isAdmin = $participantInfo['is_admin'] == 1;
    $isModerator = $participantInfo['is_moderator'] == 1 || $isAdmin;
    
    // Récupérer les messages et participants
    if (!$isDeleted) {
        $messages = getMessages($convId, $user['id'], $user['type']);
        $participants = getParticipants($convId);
    } else {
        $messages = getMessagesEvenIfDeleted($convId, $user['id'], $user['type']);
        $participants = getParticipants($convId);
    }
    
    $canReply = !$isDeleted && canReplyToAnnouncement($user['id'], $user['type'], $convId, $conversation['type']);
    
    $pageTitle = 'Conversation - ' . $conversation['titre'];
    
    // Traitement des actions POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !$isDeleted) {
        switch ($_POST['action']) {
            case 'send_message':
                $result = handleSendMessage(
                    $convId,
                    $user,
                    $_POST['contenu'],
                    isset($_POST['importance']) ? $_POST['importance'] : 'normal',
                    isset($_POST['parent_message_id']) && !empty($_POST['parent_message_id']) ? (int)$_POST['parent_message_id'] : null,
                    isset($_FILES['attachments']) ? $_FILES['attachments'] : []
                );
                
                if ($result['success']) {
                    // Redirection pour éviter les soumissions multiples
                    redirect("conversation.php?id=$convId");
                } else {
                    $error = $result['message'];
                    $messageContent = $_POST['contenu']; // Conserver le contenu en cas d'erreur
                }
                break;
                
            case 'add_participant':
                $result = handleAddParticipant(
                    $convId,
                    $_POST['participant_id'],
                    $_POST['participant_type'],
                    $user
                );
                
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'promote_moderator':
                $result = handlePromoteToModerator(
                    $convId,
                    $_POST['participant_id'],
                    $user
                );
                
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'demote_moderator':
                $result = handleDemoteFromModerator(
                    $convId,
                    $_POST['participant_id'],
                    $user
                );
                
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'remove_participant':
                $result = handleRemoveParticipant(
                    $convId,
                    $_POST['participant_id'],
                    $user
                );
                
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'archive_conversation':
                $result = handleArchiveConversation($convId, $user);
                
                if ($result['success']) {
                    redirect($result['redirect']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'delete_conversation':
                $result = handleDeleteConversation($convId, $user);
                
                if ($result['success']) {
                    redirect($result['redirect']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'restore_conversation':
                $result = handleRestoreConversation($convId, $user);
                
                if ($result['success']) {
                    redirect($result['redirect']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'unarchive':
                $result = handleUnarchiveConversation($convId, $user);
                
                if ($result['success']) {
                    redirect($result['redirect']);
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $conversation = null;
    $messages = [];
    $participants = [];
    $isDeleted = false;
    $isAdmin = false;
    $isModerator = false;
    $canReply = false;
}

include 'templates/header.php';
?>

<div class="content conversation-page" data-sidebar-collapsed="false">
    <?php if (isset($error) && !empty($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php elseif (isset($success) && !empty($success)): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if (isset($conversation)): ?>
    
    <aside class="conversation-sidebar">
        <div class="conversation-info">
            <h3>
                Participants 
                <?php if (!$isDeleted && $isModerator): ?>
                <button id="add-participant-btn" class="btn-icon"><i class="fas fa-plus-circle"></i></button>
                <?php endif; ?>
            </h3>
            <?php include 'templates/components/participant-list.php'; ?>
        </div>
        
        <div class="conversation-actions">
            <?php if ($isDeleted): ?>
            <a href="#" class="action-button" id="restore-btn">
                <i class="fas fa-trash-restore"></i> Restaurer la conversation
            </a>
            <?php else: ?>
            <a href="#" class="action-button" id="archive-btn">
                <i class="fas fa-archive"></i> Archiver la conversation
            </a>
            <a href="#" class="action-button" id="delete-btn">
                <i class="fas fa-trash"></i> Supprimer la conversation
            </a>
            <?php endif; ?>
        </div>
    </aside>
    
    <main class="conversation-main">
        <div class="messages-container">
            <?php foreach ($messages as $message): ?>
                <?php include 'templates/components/message-item.php'; ?>
            <?php endforeach; ?>
        </div>
        
<?php if ($isDeleted): ?>
<div class="conversation-deleted">
    <p>Cette conversation a été déplacée dans la corbeille. Vous ne pouvez plus y répondre.</p>
    <form method="post" action="" id="restoreForm">
        <input type="hidden" name="action" value="restore_conversation">
        <button type="submit" class="btn primary">Restaurer la conversation</button>
    </form>
</div>
<?php elseif ($canReply): ?>
<div class="reply-box">
    <form method="post" enctype="multipart/form-data" id="messageForm">
        <input type="hidden" name="action" value="send_message">
        <input type="hidden" name="parent_message_id" id="parent-message-id" value="">
        
        <!-- Interface de réponse (cachée par défaut) -->
        <div id="reply-interface" style="display: none;">
            <div class="reply-header">
                <span id="reply-to" class="reply-to"></span>
                <button type="button" class="btn-icon" onclick="cancelReply()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <?php if (canSetMessageImportance($user['type'])): ?>
        <div class="reply-options">
            <select name="importance" class="importance-select">
                <option value="normal">Normal</option>
                <option value="important">Important</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>
        <?php endif; ?>
        
        <textarea name="contenu" rows="4" placeholder="Envoyer un message..." required><?= htmlspecialchars($messageContent) ?></textarea>
        
        <div class="form-footer">
            <div class="file-upload">
                <input type="file" name="attachments[]" id="attachments" multiple>
                <label for="attachments">
                    <i class="fas fa-paperclip"></i> Sélect. fichiers
                </label>
                <div id="file-list"></div>
            </div>
            
            <button type="submit" class="btn primary">
                <i class="fas fa-paper-plane"></i> Envoyer
            </button>
        </div>
    </form>
</div>
<?php elseif (!$isDeleted && $conversation['type'] === 'annonce'): ?>
<div class="conversation-deleted">
    <p>Cette annonce est en lecture seule. Vous ne pouvez pas y répondre.</p>
</div>
<?php endif; ?>
    </main>
    <?php endif; ?>
</div>

<!-- Modal pour ajouter des participants -->
<div class="modal" id="addParticipantModal">
    <div class="modal-content">
        <span class="close" onclick="closeAddParticipantModal()">&times;</span>
        <h3>Ajouter des participants</h3>
        <form method="post" id="addParticipantForm">
            <input type="hidden" name="action" value="add_participant">
            
            <div class="form-group">
                <label for="participant_type">Type de participant</label>
                <select name="participant_type" id="participant_type" onchange="loadParticipants()" required>
                    <option value="">Sélectionner un type</option>
                    <option value="eleve">Élève</option>
                    <option value="parent">Parent</option>
                    <option value="professeur">Professeur</option>
                    <option value="vie_scolaire">Vie scolaire</option>
                    <option value="administrateur">Administrateur</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="participant_id">Participant</label>
                <select name="participant_id" id="participant_id" required>
                    <option value="">Choisissez d'abord un type</option>
                </select>
            </div>
            
            <button type="submit" class="btn primary">Ajouter</button>
        </form>
    </div>
</div>

<!-- Formulaires cachés pour les actions -->
<form id="archiveForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="archive_conversation">
</form>

<form id="deleteForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_conversation">
</form>

<form id="promoteForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="promote_moderator">
    <input type="hidden" name="participant_id" id="promote_participant_id" value="">
</form>

<form id="demoteForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="demote_moderator">
    <input type="hidden" name="participant_id" id="demote_participant_id" value="">
</form>

<form id="removeForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="remove_participant">
    <input type="hidden" name="participant_id" id="remove_participant_id" value="">
</form>

<?php
// Inclure le pied de page
include 'templates/footer.php';
?>