<?php
/**
 * API REST pour la gestion des messages
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../models/message.php';
require_once __DIR__ . '/../controllers/message.php';

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
        case 'send_message':
            // Envoi d'un message via AJAX
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée');
            }
            
            $contenu = $_POST['contenu'] ?? '';
            $importance = $_POST['importance'] ?? 'normal';
            $parentMessageId = !empty($_POST['parent_message_id']) ? (int)$_POST['parent_message_id'] : null;
            
            $result = handleSendMessage(
                $convId,
                $user,
                $contenu,
                $importance,
                $parentMessageId,
                $_FILES['attachments'] ?? []
            );
            
            if ($result['success']) {
                // Récupérer le message envoyé
                $message = getMessageById($result['messageId']);
                echo json_encode([
                    'success' => true,
                    'message' => $message
                ]);
            } else {
                throw new Exception($result['message']);
            }
            break;
            
        case 'check_updates':
            // Vérifier les nouvelles mises à jour
            $lastTimestamp = isset($_GET['last_timestamp']) ? (int)$_GET['last_timestamp'] : 0;
            
            global $pdo;
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as new_count
                FROM messages
                WHERE conversation_id = ? 
                AND UNIX_TIMESTAMP(created_at) > ?
                AND sender_id != ? 
                AND sender_type != ?
            ");
            $stmt->execute([$convId, $lastTimestamp, $user['id'], $user['type']]);
            $result = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'has_updates' => $result['new_count'] > 0,
                'new_count' => (int)$result['new_count']
            ]);
            break;
            
        case 'get_new':
            // Récupérer les nouveaux messages
            $lastTimestamp = isset($_GET['last_timestamp']) ? (int)$_GET['last_timestamp'] : 0;
            
            global $pdo;
            $stmt = $pdo->prepare("
                SELECT m.*, 
                    CASE 
                        WHEN m.sender_id = ? AND m.sender_type = ? THEN 1
                        ELSE 0
                    END as is_self,
                    CASE 
                        WHEN m.sender_type = 'eleve' THEN 
                            (SELECT CONCAT(e.prenom, ' ', e.nom) FROM eleves e WHERE e.id = m.sender_id)
                        WHEN m.sender_type = 'parent' THEN 
                            (SELECT CONCAT(p.prenom, ' ', p.nom) FROM parents p WHERE p.id = m.sender_id)
                        WHEN m.sender_type = 'professeur' THEN 
                            (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = m.sender_id)
                        WHEN m.sender_type = 'vie_scolaire' THEN 
                            (SELECT CONCAT(v.prenom, ' ', v.nom) FROM vie_scolaire v WHERE v.id = m.sender_id)
                        WHEN m.sender_type = 'administrateur' THEN 
                            (SELECT CONCAT(a.prenom, ' ', a.nom) FROM administrateurs a WHERE a.id = m.sender_id)
                        ELSE 'Inconnu'
                    END as expediteur_nom,
                    UNIX_TIMESTAMP(m.created_at) as timestamp
                FROM messages m
                WHERE m.conversation_id = ? 
                AND UNIX_TIMESTAMP(m.created_at) > ?
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$user['id'], $user['type'], $convId, $lastTimestamp]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les pièces jointes pour chaque message
            foreach ($messages as &$message) {
                $attachStmt = $pdo->prepare("
                    SELECT id, file_name as nom_fichier, file_path as chemin
                    FROM message_attachments WHERE message_id = ?
                ");
                $attachStmt->execute([$message['id']]);
                $message['pieces_jointes'] = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode([
                'success' => true,
                'messages' => $messages
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