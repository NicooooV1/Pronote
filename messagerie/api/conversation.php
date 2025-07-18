<?php
/**
 * API pour les actions sur les conversations
 */
// Ensure config is loaded first
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/conversation.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../models/message.php';
require_once __DIR__ . '/../controllers/message.php';

// Debug session if needed
if (defined('APP_ENV') && APP_ENV === 'development') {
    error_log('Session ID in API/conversation.php: ' . session_id());
    error_log('User in session: ' . (isset($_SESSION['user']) ? 'YES' : 'NO'));
}

// S'assurer que toute sortie avant l'entête JSON est nettoyée
if (ob_get_length()) ob_clean();

// Toujours répondre en JSON
header('Content-Type: application/json');

// Vérifier l'authentification
$user = checkAuth();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// --- IMPORTANT: Fonction pour sécuriser les transactions PDO ---
function safeTransaction($callback) {
    global $pdo;
    
    // Vérifier si une transaction est déjà active
    if ($pdo->inTransaction()) {
        try {
            // Si c'est le cas, on l'annule et on journalise l'événement
            $pdo->rollBack();
            logApiRequest("Une transaction active a été détectée et annulée avant d'en démarrer une nouvelle");
        } catch (Exception $e) {
            logApiRequest("Erreur lors de l'annulation d'une transaction active: " . $e->getMessage());
        }
    }
    
    // Démarrer une nouvelle transaction
    $pdo->beginTransaction();
    
    try {
        // Exécuter la fonction de callback
        $result = $callback();
        
        // Valider la transaction
        $pdo->commit();
        
        return $result;
    } catch (Exception $e) {
        // En cas d'erreur, annuler la transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Propager l'exception
        throw $e;
    }
}

// Point d'entrée pour les actions en masse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'bulk') {
    // Récupérer les données JSON
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false, 
            'error' => 'JSON invalide: ' . json_last_error_msg(),
            'input_received' => substr($jsonInput, 0, 100) . '...'
        ]);
        exit;
    }

    if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids']) || !isset($data['action'])) {
        echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
        exit;
    }

    $action = $data['action'];
    $convIds = $data['ids'];
    $count = 0;

    try {
        $pdo->beginTransaction();
        
        switch ($action) {
            case 'delete':
                // Supprimer les conversations
                foreach ($convIds as $convId) {
                    if (deleteConversation($convId, $user['id'], $user['type'])) {
                        $count++;
                    }
                }
                $message = "Conversations supprimées";
                break;
                
            case 'delete_permanently':
                // Supprimer définitivement les conversations
                foreach ($convIds as $convId) {
                    if (deletePermanently($convId, $user['id'], $user['type'])) {
                        $count++;
                    }
                }
                $message = "Conversations supprimées définitivement";
                break;
                
            case 'archive':
                // Archiver les conversations
                foreach ($convIds as $convId) {
                    if (archiveConversation($convId, $user['id'], $user['type'])) {
                        $count++;
                    }
                }
                $message = "Conversations archivées";
                break;
                
            case 'restore':
                // Restaurer les conversations
                foreach ($convIds as $convId) {
                    if (restoreConversation($convId, $user['id'], $user['type'])) {
                        $count++;
                    }
                }
                $message = "Conversations restaurées";
                break;
                
            case 'unarchive':
                // Désarchiver les conversations (fonctionnalité identique à restore)
                foreach ($convIds as $convId) {
                    if (unarchiveConversation($convId, $user['id'], $user['type'])) {
                        $count++;
                    }
                }
                $message = "Conversations désarchivées";
                break;
                
            case 'mark_read':
                // Marquer comme lues
                foreach ($convIds as $convId) {
                    // Vérifier que l'utilisateur est participant à la conversation
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM conversation_participants 
                        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
                    ");
                    $checkStmt->execute([$convId, $user['id'], $user['type']]);
                    
                    if ($checkStmt->fetch()) {
                        // Récupérer tous les messages non lus de cette conversation
                        $messagesStmt = $pdo->prepare("
                            SELECT m.id 
                            FROM messages m
                            LEFT JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
                            WHERE m.conversation_id = ? 
                            AND cp.user_id = ? AND cp.user_type = ?
                            AND (cp.last_read_at IS NULL OR m.created_at > cp.last_read_at)
                            AND m.sender_id != ? AND m.sender_type != ?
                        ");
                        $messagesStmt->execute([$convId, $user['id'], $user['type'], $user['id'], $user['type']]);
                        $messages = $messagesStmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        // Marquer chaque message comme lu
                        foreach ($messages as $messageId) {
                            markMessageAsRead($messageId, $user['id'], $user['type']);
                        }
                        
                        // Mettre à jour la date de dernière lecture
                        $updateStmt = $pdo->prepare("
                            UPDATE conversation_participants 
                            SET last_read_at = NOW(), unread_count = 0
                            WHERE conversation_id = ? AND user_id = ? AND user_type = ?
                        ");
                        $updateStmt->execute([$convId, $user['id'], $user['type']]);
                        
                        $count++;
                    }
                }
                $message = "Conversations marquées comme lues";
                break;
                
            case 'mark_unread':
                // Marquer comme non lues
                foreach ($convIds as $convId) {
                    // Vérifier que l'utilisateur est participant à la conversation
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM conversation_participants 
                        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
                    ");
                    $checkStmt->execute([$convId, $user['id'], $user['type']]);
                    
                    if ($checkStmt->fetch()) {
                        // Réinitialiser la date de dernière lecture
                        $updateStmt = $pdo->prepare("
                            UPDATE conversation_participants 
                            SET last_read_at = NULL
                            WHERE conversation_id = ? AND user_id = ? AND user_type = ?
                        ");
                        $updateStmt->execute([$convId, $user['id'], $user['type']]);
                        
                        // Récupérer le dernier message de la conversation qui n'est pas de l'utilisateur
                        $lastMessageStmt = $pdo->prepare("
                            SELECT id FROM messages 
                            WHERE conversation_id = ? 
                            AND sender_id != ? AND sender_type != ?
                            ORDER BY created_at DESC LIMIT 1
                        ");
                        $lastMessageStmt->execute([$convId, $user['id'], $user['type']]);
                        $lastMessageId = $lastMessageStmt->fetchColumn();
                        
                        if ($lastMessageId) {
                            // Marquer le dernier message comme non lu
                            markMessageAsUnread($lastMessageId, $user['id'], $user['type']);
                            
                            // Mettre à jour le compteur de messages non lus
                            $updateCountStmt = $pdo->prepare("
                                UPDATE conversation_participants 
                                SET unread_count = 1
                                WHERE conversation_id = ? AND user_id = ? AND user_type = ?
                            ");
                            $updateCountStmt->execute([$convId, $user['id'], $user['type']]);
                        }
                        
                        $count++;
                    }
                }
                $message = "Conversations marquées comme non lues";
                break;
                
            default:
                throw new Exception("Action non supportée");
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'count' => $count,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Action de suppression multiple
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete_multiple') {
    // Récupérer les données JSON
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
        echo json_encode(['success' => false, 'error' => 'Aucun identifiant fourni']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $result = handleMultipleDelete($data['ids'], $user);
        
        $pdo->commit();
        
        echo json_encode($result);
    } catch (Exception $e) {
        $pdo->rollBack();
        
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Actions sur une conversation unique
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && isset($_GET['action'])) {
    $convId = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if (!$convId) {
        echo json_encode(['success' => false, 'error' => 'ID de conversation invalide']);
        exit;
    }
    
    try {
        $result = null;
        
        switch ($action) {
            case 'mark_read':
                $result = safeTransaction(function() use ($pdo, $convId, $user) {
                    // Vérifier que l'utilisateur est participant à la conversation
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM conversation_participants 
                        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
                    ");
                    $checkStmt->execute([$convId, $user['id'], $user['type']]);
                    
                    if (!$checkStmt->fetch()) {
                        throw new Exception("Vous n'êtes pas autorisé à accéder à cette conversation");
                    }
                    
                    // Mettre à jour la date de dernière lecture
                    $updateStmt = $pdo->prepare("
                        UPDATE conversation_participants 
                        SET last_read_at = NOW(), unread_count = 0
                        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
                    ");
                    $updateStmt->execute([$convId, $user['id'], $user['type']]);
                    
                    return [
                        'success' => true, 
                        'message' => "Conversation marquée comme lue"
                    ];
                });
                break;
            
            case 'mark_unread':
                $result = safeTransaction(function() use ($pdo, $convId, $user) {
                    // Vérifier que l'utilisateur est participant à la conversation
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM conversation_participants 
                        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
                    ");
                    $checkStmt->execute([$convId, $user['id'], $user['type']]);
                    
                    if (!$checkStmt->fetch()) {
                        throw new Exception("Vous n'êtes pas autorisé à accéder à cette conversation");
                    }
                    
                    // Réinitialiser la date de dernière lecture et définir un message non lu
                    $updateStmt = $pdo->prepare("
                        UPDATE conversation_participants 
                        SET last_read_at = NULL, unread_count = 1
                        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
                    ");
                    $updateStmt->execute([$convId, $user['id'], $user['type']]);
                    
                    return [
                        'success' => true, 
                        'message' => "Conversation marquée comme non lue"
                    ];
                });
                break;
                
            case 'archive':
                $result = handleArchiveConversation($convId, $user);
                break;
                
            case 'delete':
                $result = handleDeleteConversation($convId, $user);
                break;
                
            case 'restore':
                $result = handleRestoreConversation($convId, $user);
                break;
                
            case 'delete_permanently':
                $result = handlePermanentDelete($convId, $user);
                break;
                
            default:
                throw new Exception("Action non supportée");
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        // Assurer qu'aucune transaction n'est laissée ouverte
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Si on arrive ici, c'est que l'action demandée n'existe pas
echo json_encode(['success' => false, 'error' => 'Action non supportée']);