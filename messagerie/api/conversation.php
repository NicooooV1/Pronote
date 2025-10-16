<?php
/**
 * API REST pour la gestion des conversations
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../models/conversation.php';

// Vérifier l'authentification
$user = checkAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Récupérer l'action demandée
$action = $_GET['action'] ?? '';
$convId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Définir le type de contenu JSON
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'mark_read':
            // Marquer une conversation comme lue
            if (!$convId) {
                throw new Exception('ID de conversation manquant');
            }
            
            $result = markConversationAsRead($convId, $user['id'], $user['type']);
            echo json_encode(['success' => $result]);
            break;
            
        case 'mark_unread':
            // Marquer une conversation comme non lue
            if (!$convId) {
                throw new Exception('ID de conversation manquant');
            }
            
            // Réinitialiser la dernière lecture
            global $pdo;
            $stmt = $pdo->prepare("
                UPDATE conversation_participants 
                SET last_read_at = NULL, unread_count = (
                    SELECT COUNT(*) FROM messages 
                    WHERE conversation_id = ?
                )
                WHERE conversation_id = ? AND user_id = ? AND user_type = ?
            ");
            $result = $stmt->execute([$convId, $convId, $user['id'], $user['type']]);
            
            echo json_encode(['success' => $result]);
            break;
            
        case 'bulk':
            // Actions en masse
            $data = json_decode(file_get_contents('php://input'), true);
            $bulkAction = $data['action'] ?? '';
            $ids = $data['ids'] ?? [];
            
            if (empty($ids)) {
                throw new Exception('Aucune conversation sélectionnée');
            }
            
            $count = 0;
            
            switch ($bulkAction) {
                case 'mark_read':
                    foreach ($ids as $id) {
                        if (markConversationAsRead($id, $user['id'], $user['type'])) {
                            $count++;
                        }
                    }
                    break;
                    
                case 'mark_unread':
                    global $pdo;
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("
                        UPDATE conversation_participants 
                        SET last_read_at = NULL, unread_count = (
                            SELECT COUNT(*) FROM messages 
                            WHERE conversation_id = conversation_participants.conversation_id
                        )
                        WHERE conversation_id IN ($placeholders) 
                        AND user_id = ? AND user_type = ?
                    ");
                    $params = array_merge($ids, [$user['id'], $user['type']]);
                    $stmt->execute($params);
                    $count = $stmt->rowCount();
                    break;
                    
                case 'archive':
                    foreach ($ids as $id) {
                        if (archiveConversation($id, $user['id'], $user['type'])) {
                            $count++;
                        }
                    }
                    break;
                    
                case 'delete':
                    foreach ($ids as $id) {
                        if (deleteConversation($id, $user['id'], $user['type'])) {
                            $count++;
                        }
                    }
                    break;
                    
                case 'restore':
                    foreach ($ids as $id) {
                        if (restoreConversation($id, $user['id'], $user['type'])) {
                            $count++;
                        }
                    }
                    break;
                    
                case 'delete_permanently':
                    $count = deleteMultipleConversations($ids, $user['id'], $user['type']);
                    break;
                    
                default:
                    throw new Exception('Action non reconnue');
            }
            
            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => "Action effectuée sur $count conversation(s)"
            ]);
            break;
            
        default:
            throw new Exception('Action non spécifiée');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}