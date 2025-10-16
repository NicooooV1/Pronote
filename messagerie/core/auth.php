<?php
/**
 * Module d'authentification pour la messagerie - Utilise l'API
 */

// S'assurer que l'API est chargée
if (!function_exists('getCurrentUser')) {
    require_once dirname(dirname(dirname(__DIR__))) . '/API/core.php';
}

/**
 * Vérifie si l'utilisateur est authentifié
 * @return array|false Données utilisateur ou false
 */
function checkAuth() {
    return getCurrentUser();
}

/**
 * Vérifie l'authentification et redirige si nécessaire
 * @param string $redirect URL de redirection
 * @return array Données utilisateur
 */
function requireAuth($redirect = null) {
    $user = getCurrentUser();
    
    if (!$user) {
        $loginUrl = defined('LOGIN_URL') ? LOGIN_URL : '/login/public/index.php';
        
        if ($redirect === null) {
            $returnUrl = urlencode($_SERVER['REQUEST_URI']);
            $delimiter = (strpos($loginUrl, '?') === false) ? '?' : '&';
            $redirect = $loginUrl . $delimiter . 'return=' . $returnUrl;
        }
        
        header('Location: ' . $redirect);
        exit;
    }
    
    // Normaliser le type d'utilisateur
    if (!isset($user['type']) && isset($user['profil'])) {
        $user['type'] = $user['profil'];
    }
    
    return $user;
}

/**
 * Compte les notifications non lues - délégué à l'API
 */
if (!function_exists('countUnreadNotifications')) {
    function countUnreadNotifications($userId, $userType) {
        global $pdo;
        
        try {
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
?>