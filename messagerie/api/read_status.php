<?php
/**
 * API REST pour le statut de lecture des messages
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../models/message.php';

// Vérifier l'authentification
$user = checkAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Définir le type de contenu JSON
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$convId = isset($_GET['conv_id']) ? (int)$_GET['conv_id'] : 0;

try {
    switch ($action) {
        case 'read':
            // Marquer un message comme lu
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $messageId = $data['messageId'] ?? 0;
            
            if (!$messageId) {
                throw new Exception('ID de message manquant');
            }
            
            $result = markMessageAsRead($messageId, $user['id'], $user['type']);
            
            if ($result) {
                $readStatus = getMessageReadStatus($messageId);
                echo json_encode([
                    'success' => true,
                    'readStatus' => $readStatus
                ]);
            } else {
                throw new Exception('Échec du marquage comme lu');
            }
            break;
            
        case 'read-polling':
            // Polling pour les mises à jour de statut de lecture
            $version = isset($_GET['version']) ? (int)$_GET['version'] : 0;
            $sinceMessageId = isset($_GET['since']) ? (int)$_GET['since'] : 0;
            
            global $pdo;
            
            // Vérifier s'il y a des changements de version
            $stmt = $pdo->prepare("
                SELECT SUM(version) as current_version
                FROM conversation_participants
                WHERE conversation_id = ?
            ");
            $stmt->execute([$convId]);
            $result = $stmt->fetch();
            $currentVersion = (int)$result['current_version'];
            
            // Si la version a changé, récupérer les statuts mis à jour
            if ($currentVersion !== $version) {
                // Récupérer les messages de l'utilisateur qui ont été lus
                $stmt = $pdo->prepare("
                    SELECT m.id as message_id
                    FROM messages m
                    WHERE m.conversation_id = ? 
                    AND m.sender_id = ? 
                    AND m.sender_type = ?
                    AND m.id >= ?
                    ORDER BY m.id ASC
                ");
                $stmt->execute([$convId, $user['id'], $user['type'], $sinceMessageId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $updates = [];
                foreach ($messages as $msg) {
                    $readStatus = getMessageReadStatus($msg['message_id']);
                    $updates[] = $readStatus;
                }
                
                echo json_encode([
                    'success' => true,
                    'has_updates' => true,
                    'version' => $currentVersion,
                    'updates' => $updates
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'has_updates' => false,
                    'version' => $currentVersion
                ]);
            }
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
?>