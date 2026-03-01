<?php
/**
 * Modèle pour la gestion des notifications
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/utils.php';

/**
 * Récupère les préférences de notification d'un utilisateur
 * @param int $userId
 * @param string $userType
 * @return array
 */
function getUserNotificationPreferences($userId, $userType) {
    global $pdo;
    
    // Vérifier si les préférences existent déjà
    $stmt = $pdo->prepare("
        SELECT id, user_id, user_type, email_notifications, browser_notifications,
               notification_sound, mention_notifications, reply_notifications,
               important_notifications, digest_frequency
        FROM user_notification_preferences
        WHERE user_id = ? AND user_type = ?
    ");
    $stmt->execute([$userId, $userType]);
    $preferences = $stmt->fetch();
    
    // Si les préférences n'existent pas, les créer avec les valeurs par défaut
    if (!$preferences) {
        $stmt = $pdo->prepare("
            INSERT INTO user_notification_preferences
            (user_id, user_type, email_notifications, browser_notifications, 
             notification_sound, mention_notifications, reply_notifications, 
             important_notifications, digest_frequency)
            VALUES (?, ?, 0, 1, 1, 1, 1, 1, 'never')
        ");
        $stmt->execute([$userId, $userType]);
        
        // Récupérer les préférences nouvellement créées
        return [
            'email_notifications' => false,
            'browser_notifications' => true,
            'notification_sound' => true,
            'mention_notifications' => true,
            'reply_notifications' => true,
            'important_notifications' => true,
            'digest_frequency' => 'never'
        ];
    }
    
    return $preferences;
}

/**
 * Met à jour les préférences de notification d'un utilisateur
 * @param int $userId
 * @param string $userType
 * @param array $preferences
 * @return bool
 */
function updateUserNotificationPreferences($userId, $userType, $preferences) {
    global $pdo;
    
    // Vérifier si les préférences existent déjà
    $stmt = $pdo->prepare("
        SELECT id FROM user_notification_preferences
        WHERE user_id = ? AND user_type = ?
    ");
    $stmt->execute([$userId, $userType]);
    $existing = $stmt->fetch();
    
    // Préparer les données à mettre à jour
    $data = [
        'email_notifications' => isset($preferences['email_notifications']) && $preferences['email_notifications'] ? 1 : 0,
        'browser_notifications' => isset($preferences['browser_notifications']) && $preferences['browser_notifications'] ? 1 : 0,
        'notification_sound' => isset($preferences['notification_sound']) && $preferences['notification_sound'] ? 1 : 0,
        'mention_notifications' => isset($preferences['mention_notifications']) && $preferences['mention_notifications'] ? 1 : 0,
        'reply_notifications' => isset($preferences['reply_notifications']) && $preferences['reply_notifications'] ? 1 : 0,
        'important_notifications' => isset($preferences['important_notifications']) && $preferences['important_notifications'] ? 1 : 0,
        'digest_frequency' => isset($preferences['digest_frequency']) ? $preferences['digest_frequency'] : 'never'
    ];
    
    if ($existing) {
        // Mettre à jour les préférences existantes
        $stmt = $pdo->prepare("
            UPDATE user_notification_preferences
            SET email_notifications = ?,
                browser_notifications = ?,
                notification_sound = ?,
                mention_notifications = ?,
                reply_notifications = ?,
                important_notifications = ?,
                digest_frequency = ?
            WHERE user_id = ? AND user_type = ?
        ");
        
        return $stmt->execute([
            $data['email_notifications'],
            $data['browser_notifications'],
            $data['notification_sound'],
            $data['mention_notifications'],
            $data['reply_notifications'],
            $data['important_notifications'],
            $data['digest_frequency'],
            $userId,
            $userType
        ]);
    } else {
        // Insérer de nouvelles préférences
        $stmt = $pdo->prepare("
            INSERT INTO user_notification_preferences
            (user_id, user_type, email_notifications, browser_notifications, 
             notification_sound, mention_notifications, reply_notifications, 
             important_notifications, digest_frequency)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $userType,
            $data['email_notifications'],
            $data['browser_notifications'],
            $data['notification_sound'],
            $data['mention_notifications'],
            $data['reply_notifications'],
            $data['important_notifications'],
            $data['digest_frequency']
        ]);
    }
}

/**
 * Compte le nombre de notifications non lues pour un utilisateur
 * REMARQUE: Utiliser la fonction du même nom depuis core/auth.php si elle existe déjà
 * @param int $userId
 * @param string $userType
 * @return int
 */
if (!function_exists('countUnreadNotifications')) {
    function countUnreadNotifications($userId, $userType) {
        global $pdo;
        if (!isset($pdo)) {
            return 0; // Si pas de connexion à la BDD, retourner 0
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND user_type = ? AND is_read = 0
            ");
            $stmt->execute([$userId, $userType]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            // Journaliser l'erreur mais ne pas interrompre le flux
            error_log('Error in countUnreadNotifications: ' . $e->getMessage());
            return 0; // En cas d'erreur, retourner 0
        }
    }
}

/**
 * Récupère les notifications d'un utilisateur
 * @param int $userId
 * @param string $userType
 * @param array $options Options (unread_only, limit)
 * @return array
 */
function getUserNotifications($userId, $userType, $options = []) {
    global $pdo;
    
    $unreadOnly = isset($options['unread_only']) && $options['unread_only'];
    $limit = isset($options['limit']) ? (int)$options['limit'] : 20;
    
    $sql = "
        SELECT n.*, c.title as conversation_title
        FROM notifications n
        LEFT JOIN conversations c ON n.conversation_id = c.id
        WHERE n.user_id = ? AND n.user_type = ?
    ";
    
    if ($unreadOnly) {
        $sql .= " AND n.is_read = 0";
    }
    
    $sql .= " ORDER BY n.created_at DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT " . $limit;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error in getUserNotifications: ' . $e->getMessage());
        return [];
    }
}

// Autres fonctions de notification...