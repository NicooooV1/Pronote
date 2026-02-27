<?php
/**
 * API REST étendue — Messagerie Pronote
 * Point d'entrée unique pour toutes les opérations messagerie.
 * Conçu pour être réutilisé par d'autres modules.
 *
 * Usage :
 *   GET/POST  api/v2.php?resource=<resource>&action=<action>
 *
 * Resources : conversations, messages, participants, notifications, search, reactions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../core/rate_limiter.php';
require_once __DIR__ . '/../models/conversation.php';
require_once __DIR__ . '/../models/message.php';
require_once __DIR__ . '/../models/participant.php';
require_once __DIR__ . '/../models/notification.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Authentification ──
$user = checkAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// ── CSRF pour les requêtes mutantes ──
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    csrf_verify();
}

// ── Rate limiting global ──
RateLimiter::enforce($user['id'], $user['type'], 'api_request');

// ── Routing ──
$resource = $_GET['resource'] ?? '';
$action   = $_GET['action'] ?? '';

try {
    switch ($resource) {
        // ═══════════════════════════════════════════════
        // CONVERSATIONS
        // ═══════════════════════════════════════════════
        case 'conversations':
            handleConversations($action, $user);
            break;

        // ═══════════════════════════════════════════════
        // MESSAGES
        // ═══════════════════════════════════════════════
        case 'messages':
            handleMessages($action, $user);
            break;

        // ═══════════════════════════════════════════════
        // PARTICIPANTS
        // ═══════════════════════════════════════════════
        case 'participants':
            handleParticipants($action, $user);
            break;

        // ═══════════════════════════════════════════════
        // NOTIFICATIONS
        // ═══════════════════════════════════════════════
        case 'notifications':
            handleNotifications($action, $user);
            break;

        // ═══════════════════════════════════════════════
        // RECHERCHE
        // ═══════════════════════════════════════════════
        case 'search':
            handleSearch($user);
            break;

        // ═══════════════════════════════════════════════
        // RÉACTIONS
        // ═══════════════════════════════════════════════
        case 'reactions':
            handleReactions($action, $user);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => "Ressource '{$resource}' inconnue"]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════
// HANDLERS
// ═══════════════════════════════════════════════════════

function handleConversations(string $action, array $user): void {
    global $pdo;
    
    switch ($action) {
        case 'list':
            $folder = Validator::folder($_GET['folder'] ?? null);
            $limit  = Validator::limit($_GET['limit'] ?? 20);
            $offset = Validator::positiveInt($_GET['offset'] ?? 0);
            
            $result = getConversations($user['id'], $user['type'], $folder, $limit, $offset);
            echo json_encode([
                'success'       => true,
                'conversations' => $result['conversations'],
                'total'         => $result['total'],
                'has_more'      => $result['has_more'],
                'csrf_token'    => csrf_token()
            ]);
            break;

        case 'get':
            $convId = Validator::id($_GET['id'] ?? null);
            if (!$convId) throw new Exception('ID de conversation invalide');
            
            $conv = getConversationInfo($convId);
            if (!$conv) throw new Exception('Conversation introuvable');
            
            $participants = getParticipants($convId);
            echo json_encode(['success' => true, 'conversation' => $conv, 'participants' => $participants]);
            break;

        case 'create':
            requirePost();
            $data = getJsonBody();
            
            $titre = Validator::subject($data['titre'] ?? null);
            if (!$titre) throw new Exception('Titre de conversation invalide');
            
            $participants = Validator::participants($data['participants'] ?? []);
            if (empty($participants)) throw new Exception('Au moins un participant est requis');
            
            $type = $data['type'] ?? 'standard';
            $convId = createConversation($titre, $type, $user['id'], $user['type'], $participants);
            
            echo json_encode(['success' => true, 'conversation_id' => $convId]);
            break;

        case 'mark_read':
            $convId = Validator::id($_GET['id'] ?? null);
            if (!$convId) throw new Exception('ID de conversation invalide');
            
            $result = markConversationAsRead($convId, $user['id'], $user['type']);
            echo json_encode(['success' => $result]);
            break;

        case 'mark_unread':
            $convId = Validator::id($_GET['id'] ?? null);
            if (!$convId) throw new Exception('ID de conversation invalide');
            
            $stmt = $pdo->prepare("
                UPDATE conversation_participants 
                SET last_read_at = NULL, unread_count = (SELECT COUNT(*) FROM messages WHERE conversation_id = ?)
                WHERE conversation_id = ? AND user_id = ? AND user_type = ?
            ");
            $result = $stmt->execute([$convId, $convId, $user['id'], $user['type']]);
            echo json_encode(['success' => $result]);
            break;

        case 'archive':
            $convId = Validator::id($_GET['id'] ?? getJsonBody()['id'] ?? null);
            if (!$convId) throw new Exception('ID invalide');
            echo json_encode(['success' => archiveConversation($convId, $user['id'], $user['type'])]);
            break;

        case 'unarchive':
            $convId = Validator::id($_GET['id'] ?? getJsonBody()['id'] ?? null);
            if (!$convId) throw new Exception('ID invalide');
            echo json_encode(['success' => unarchiveConversation($convId, $user['id'], $user['type'])]);
            break;

        case 'delete':
            $convId = Validator::id($_GET['id'] ?? getJsonBody()['id'] ?? null);
            if (!$convId) throw new Exception('ID invalide');
            echo json_encode(['success' => deleteConversation($convId, $user['id'], $user['type'])]);
            break;

        case 'restore':
            $convId = Validator::id($_GET['id'] ?? getJsonBody()['id'] ?? null);
            if (!$convId) throw new Exception('ID invalide');
            echo json_encode(['success' => restoreConversation($convId, $user['id'], $user['type'])]);
            break;

        case 'bulk':
            requirePost();
            $data = getJsonBody();
            $bulkAction = $data['action'] ?? '';
            $ids = Validator::ids($data['ids'] ?? []);
            
            if (empty($ids)) throw new Exception('Aucune conversation sélectionnée');
            
            $count = 0;
            foreach ($ids as $id) {
                switch ($bulkAction) {
                    case 'mark_read':
                        if (markConversationAsRead($id, $user['id'], $user['type'])) $count++;
                        break;
                    case 'archive':
                        if (archiveConversation($id, $user['id'], $user['type'])) $count++;
                        break;
                    case 'delete':
                        if (deleteConversation($id, $user['id'], $user['type'])) $count++;
                        break;
                    case 'restore':
                        if (restoreConversation($id, $user['id'], $user['type'])) $count++;
                        break;
                    case 'delete_permanently':
                        if (deletePermanently($id, $user['id'], $user['type'])) $count++;
                        break;
                }
            }
            
            if ($bulkAction === 'mark_unread') {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("
                    UPDATE conversation_participants 
                    SET last_read_at = NULL, unread_count = (
                        SELECT COUNT(*) FROM messages WHERE conversation_id = conversation_participants.conversation_id
                    ) WHERE conversation_id IN ($ph) AND user_id = ? AND user_type = ?
                ");
                $stmt->execute(array_merge($ids, [$user['id'], $user['type']]));
                $count = $stmt->rowCount();
            }
            
            echo json_encode(['success' => true, 'count' => $count]);
            break;

        default:
            throw new Exception("Action '{$action}' inconnue pour les conversations");
    }
}

function handleMessages(string $action, array $user): void {
    global $pdo;
    $convId = Validator::id($_GET['conv_id'] ?? $_POST['conv_id'] ?? null);
    
    switch ($action) {
        case 'list':
            if (!$convId) throw new Exception('conv_id requis');
            $limit  = Validator::limit($_GET['limit'] ?? 50, 50, 200);
            $before = Validator::positiveInt($_GET['before'] ?? 0);
            
            $result = getMessages($convId, $user['id'], $user['type'], $limit, $before);
            echo json_encode([
                'success'  => true,
                'messages' => $result['messages'],
                'has_more' => $result['has_more'],
                'pinned'   => $result['pinned']
            ]);
            break;

        case 'send':
        case 'send_message':
            requirePost();
            if (!$convId) throw new Exception('conv_id requis');
            
            RateLimiter::enforce($user['id'], $user['type'], 'send_message');
            
            $contenu = Validator::messageBody($_POST['contenu'] ?? null);
            if (!$contenu) throw new Exception('Contenu du message invalide');
            
            $importance = Validator::importance($_POST['importance'] ?? 'normal');
            $parentId = Validator::id($_POST['parent_message_id'] ?? null);
            
            require_once __DIR__ . '/../controllers/message.php';
            $result = handleSendMessage($convId, $user, $contenu, $importance, $parentId, $_FILES['attachments'] ?? []);
            
            if ($result['success']) {
                $message = getMessageById($result['messageId']);
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                throw new Exception($result['message']);
            }
            break;

        case 'edit':
            requirePost();
            $data = getJsonBody();
            $messageId = Validator::id($data['message_id'] ?? null);
            $newBody = Validator::messageBody($data['body'] ?? null);
            if (!$messageId || !$newBody) throw new Exception('Paramètres invalides');
            
            editMessage($messageId, $user['id'], $user['type'], $newBody);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            requirePost();
            $data = getJsonBody();
            $messageId = Validator::id($data['message_id'] ?? null);
            if (!$messageId) throw new Exception('ID de message invalide');
            
            $result = deleteMessage($messageId, $user['id'], $user['type']);
            echo json_encode(['success' => $result]);
            break;

        case 'pin':
            requirePost();
            $data = getJsonBody();
            $messageId = Validator::id($data['message_id'] ?? null);
            if (!$messageId) throw new Exception('ID de message invalide');
            
            $result = togglePinMessage($messageId, $user['id'], $user['type']);
            echo json_encode(array_merge(['success' => true], $result));
            break;

        case 'check_updates':
            if (!$convId) throw new Exception('conv_id requis');
            $lastTs = Validator::timestamp($_GET['last_timestamp'] ?? 0);
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as new_count FROM messages
                WHERE conversation_id = ? AND UNIX_TIMESTAMP(created_at) > ?
                AND sender_id != ? AND sender_type != ? AND deleted_at IS NULL
            ");
            $stmt->execute([$convId, $lastTs, $user['id'], $user['type']]);
            $r = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'has_updates' => $r['new_count'] > 0,
                'new_count' => (int)$r['new_count']
            ]);
            break;

        case 'get_new':
            if (!$convId) throw new Exception('conv_id requis');
            $lastTs = Validator::timestamp($_GET['last_timestamp'] ?? 0);
            $nameCase = getUserNameCaseSQL('m.sender_id', 'm.sender_type');
            
            $stmt = $pdo->prepare("
                SELECT m.id, m.conversation_id, m.sender_id, m.sender_type, m.body, 
                       m.status, m.parent_message_id, m.edited_at, m.is_pinned,
                       m.deleted_at,
                       CASE WHEN m.sender_id = ? AND m.sender_type = ? THEN 1 ELSE 0 END as is_self,
                       {$nameCase} as expediteur_nom,
                       UNIX_TIMESTAMP(m.created_at) as timestamp
                FROM messages m
                WHERE m.conversation_id = ? AND UNIX_TIMESTAMP(m.created_at) > ?
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$user['id'], $user['type'], $convId, $lastTs]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Batch pièces jointes
            if (!empty($messages)) {
                $ids = array_column($messages, 'id');
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $aStmt = $pdo->prepare("SELECT id, message_id, file_name as nom_fichier, file_path as chemin FROM message_attachments WHERE message_id IN ($ph)");
                $aStmt->execute($ids);
                $byMsg = [];
                foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $a) $byMsg[$a['message_id']][] = $a;
                foreach ($messages as &$msg) $msg['pieces_jointes'] = $byMsg[$msg['id']] ?? [];
                unset($msg);
            }
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;

        case 'read_status':
            $convId_ = Validator::id($_GET['conv_id'] ?? null);
            $version = Validator::positiveInt($_GET['version'] ?? 0);
            $since = Validator::positiveInt($_GET['since'] ?? 0);
            
            if (!$convId_) throw new Exception('conv_id requis');
            
            $stmt = $pdo->prepare("SELECT SUM(version) as v FROM conversation_participants WHERE conversation_id = ?");
            $stmt->execute([$convId_]);
            $currentVersion = (int) $stmt->fetch()['v'];
            
            if ($currentVersion !== $version) {
                $stmt = $pdo->prepare("
                    SELECT m.id as message_id FROM messages m
                    WHERE m.conversation_id = ? AND m.sender_id = ? AND m.sender_type = ? AND m.id >= ?
                    ORDER BY m.id ASC
                ");
                $stmt->execute([$convId_, $user['id'], $user['type'], $since]);
                $updates = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $msg) {
                    $updates[] = getMessageReadStatus($msg['message_id']);
                }
                echo json_encode(['success' => true, 'has_updates' => true, 'version' => $currentVersion, 'updates' => $updates]);
            } else {
                echo json_encode(['success' => true, 'has_updates' => false, 'version' => $currentVersion]);
            }
            break;

        case 'mark_read':
            requirePost();
            $data = getJsonBody();
            $messageId = Validator::id($data['messageId'] ?? null);
            if (!$messageId) throw new Exception('ID de message invalide');
            
            $result = markMessageAsRead($messageId, $user['id'], $user['type']);
            if ($result) {
                $readStatus = getMessageReadStatus($messageId);
                echo json_encode(['success' => true, 'readStatus' => $readStatus]);
            } else {
                throw new Exception('Échec du marquage');
            }
            break;

        default:
            throw new Exception("Action '{$action}' inconnue pour les messages");
    }
}

function handleParticipants(string $action, array $user): void {
    $convId = Validator::id($_GET['conv_id'] ?? null);
    
    switch ($action) {
        case 'list':
        case 'get_list':
            if (!$convId) throw new Exception('conv_id requis');
            $participants = getParticipants($convId);
            echo json_encode(['success' => true, 'participants' => $participants]);
            break;

        case 'available':
            if (!$convId) throw new Exception('conv_id requis');
            $type = Validator::userType($_GET['type'] ?? null);
            if (!$type) throw new Exception('Type invalide');
            $available = getAvailableParticipants($convId, $type);
            echo json_encode(['success' => true, 'participants' => $available]);
            break;

        case 'add':
            requirePost();
            $data = getJsonBody();
            $cId = Validator::id($data['conversation_id'] ?? null);
            $pId = Validator::id($data['user_id'] ?? null);
            $pType = Validator::userType($data['user_type'] ?? null);
            if (!$cId || !$pId || !$pType) throw new Exception('Paramètres invalides');
            
            addParticipantToConversation($cId, $pId, $pType, $user['id'], $user['type']);
            echo json_encode(['success' => true]);
            break;

        case 'remove':
            requirePost();
            $data = getJsonBody();
            $cId = Validator::id($data['conversation_id'] ?? null);
            $partId = Validator::id($data['participant_id'] ?? null);
            if (!$cId || !$partId) throw new Exception('Paramètres invalides');
            
            removeParticipant($partId, $user['id'], $user['type'], $cId);
            echo json_encode(['success' => true]);
            break;

        case 'promote':
            requirePost();
            $data = getJsonBody();
            $cId = Validator::id($data['conversation_id'] ?? null);
            $partId = Validator::id($data['participant_id'] ?? null);
            if (!$cId || !$partId) throw new Exception('Paramètres invalides');
            
            promoteToModerator($partId, $user['id'], $user['type'], $cId);
            echo json_encode(['success' => true]);
            break;

        case 'demote':
            requirePost();
            $data = getJsonBody();
            $cId = Validator::id($data['conversation_id'] ?? null);
            $partId = Validator::id($data['participant_id'] ?? null);
            if (!$cId || !$partId) throw new Exception('Paramètres invalides');
            
            demoteFromModerator($partId, $user['id'], $user['type'], $cId);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Action '{$action}' inconnue pour les participants");
    }
}

function handleNotifications(string $action, array $user): void {
    switch ($action) {
        case 'count':
            $count = countUnreadNotifications($user['id'], $user['type']);
            echo json_encode(['success' => true, 'count' => $count]);
            break;

        case 'preferences':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = getJsonBody();
                updateUserNotificationPreferences($user['id'], $user['type'], $data);
                echo json_encode(['success' => true]);
            } else {
                $prefs = getUserNotificationPreferences($user['id'], $user['type']);
                echo json_encode(['success' => true, 'preferences' => $prefs]);
            }
            break;

        default:
            throw new Exception("Action '{$action}' inconnue pour les notifications");
    }
}

function handleSearch(array $user): void {
    $query = Validator::searchQuery($_GET['q'] ?? null);
    if (!$query) throw new Exception('Requête de recherche invalide (min 2 caractères)');
    
    RateLimiter::enforce($user['id'], $user['type'], 'search');
    
    $limit  = Validator::limit($_GET['limit'] ?? 20);
    $offset = Validator::positiveInt($_GET['offset'] ?? 0);
    
    $results = searchConversations($user['id'], $user['type'], $query, $limit, $offset);
    echo json_encode(['success' => true, 'results' => $results, 'query' => $query]);
}

function handleReactions(string $action, array $user): void {
    switch ($action) {
        case 'toggle':
            requirePost();
            $data = getJsonBody();
            $messageId = Validator::id($data['message_id'] ?? null);
            $reaction = Validator::reaction($data['reaction'] ?? null);
            if (!$messageId || !$reaction) throw new Exception('Paramètres invalides');
            
            $result = toggleReaction($messageId, $user['id'], $user['type'], $reaction);
            echo json_encode(array_merge(['success' => true], $result));
            break;

        default:
            throw new Exception("Action '{$action}' inconnue pour les réactions");
    }
}

// ═══════════════════════════════════════════════════════
// UTILITAIRES
// ═══════════════════════════════════════════════════════

function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode POST requise']);
        exit;
    }
}

function getJsonBody(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $body = json_decode(file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }
    return $_POST;
}
