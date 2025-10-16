<?php
/**
 * API REST pour la gestion des notifications
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../models/notification.php';
require_once __DIR__ . '/../controllers/notification.php';

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

try {
    switch ($action) {
        case 'check':
            // Vérifier le nombre de notifications non lues
            $count = countUnreadNotifications($user['id'], $user['type']);
            
            // Récupérer la dernière notification
            $notifications = getUserNotifications($user['id'], $user['type'], [
                'unread_only' => true,
                'limit' => 1
            ]);
            
            $latestNotification = !empty($notifications) ? $notifications[0] : null;
            
            echo json_encode([
                'success' => true,
                'count' => $count,
                'latest_notification' => $latestNotification
            ]);
            break;
            
        case 'mark_read':
            // Marquer une notification comme lue
            $notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if (!$notificationId) {
                throw new Exception('ID de notification manquant');
            }
            
            $result = handleMarkNotificationRead($notificationId, $user);
            echo json_encode($result);
            break;
            
        case 'update_preferences':
            // Mettre à jour les préférences de notification
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée');
            }
            
            $preferences = $_POST['preferences'] ?? [];
            $result = handleUpdateNotificationPreferences($user['id'], $user['type'], $preferences);
            echo json_encode($result);
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