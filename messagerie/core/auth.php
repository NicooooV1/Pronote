<?php
/**
 * Module d'authentification pour la messagerie
 * Charge l'API centralisée (Bridge fournit : requireAuth, getCurrentUser, checkAuth, etc.)
 */

require_once __DIR__ . '/../../API/core.php';

// checkAuth() est fourni par l'API (Bridge) — alias de getCurrentUser()

/**
 * Compte les notifications non lues pour un utilisateur
 */
if (!function_exists('countUnreadNotifications')) {
    function countUnreadNotifications($userId, $userType) {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM message_notifications 
                WHERE user_id = ? AND user_type = ? AND is_read = 0
            ");
            $stmt->execute([$userId, $userType]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('Error counting notifications: ' . $e->getMessage());
            return 0;
        }
    }
}