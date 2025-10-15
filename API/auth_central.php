<?php
/**
 * Système d'authentification centralisé UNIQUE
 * Toutes les fonctions d'authentification pour toute l'application
 */

if (defined('AUTH_CENTRAL_INCLUDED')) return;
define('AUTH_CENTRAL_INCLUDED', true);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/core/Security.php';

// Initialiser la session de manière sécurisée
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}

/**
 * Authentifie un utilisateur
 * @param string $username Identifiant
 * @param string $password Mot de passe
 * @param string $userType Type d'utilisateur
 * @return array Résultat ['success' => bool, 'message' => string, 'user' => array|null]
 */
function authenticateUser($username, $password, $userType) {
    // Validation des entrées
    if (empty($username) || empty($password) || empty($userType)) {
        return ['success' => false, 'message' => 'Identifiants manquants'];
    }

    // Rate limiting
    $key = 'login_' . $userType . '_' . md5($username);
    if (!checkRateLimit($key, MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
        logSecurityEvent('rate_limit_exceeded', ['username' => $username, 'type' => $userType]);
        return ['success' => false, 'message' => 'Trop de tentatives. Réessayez plus tard.'];
    }

    try {
        $db = Database::getInstance();
        $tables = DB_TABLES;

        // Gestion du type "personnel"
        if ($userType === 'personnel') {
            $result = authenticateUser($username, $password, 'vie_scolaire');
            if (!$result['success']) {
                $result = authenticateUser($username, $password, 'administrateur');
            }
            return $result;
        }

        if (!isset($tables[$userType])) {
            return ['success' => false, 'message' => 'Type utilisateur invalide'];
        }

        $table = $tables[$userType];
        $sql = "SELECT * FROM `{$table}` WHERE identifiant = ? AND actif = 1 LIMIT 1";
        $user = $db->fetchOne($sql, [$username]);

        if (!$user || !password_verify($password, $user['mot_de_passe'])) {
            logSecurityEvent('login_failed', ['username' => $username, 'type' => $userType]);
            return ['success' => false, 'message' => 'Identifiants incorrects'];
        }

        // Préparer les données de session
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'identifiant' => $user['identifiant'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'mail' => $user['mail'],
            'profil' => $userType,
            'classe' => $user['classe'] ?? null,
            'actif' => true
        ];

        $_SESSION['auth_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        session_regenerate_id(true);

        // Mettre à jour la dernière connexion
        $db->execute("UPDATE `{$table}` SET last_login = NOW() WHERE id = ?", [$user['id']]);

        logSecurityEvent('login_success', ['user_id' => $user['id'], 'type' => $userType]);

        return ['success' => true, 'user' => $_SESSION['user']];

    } catch (Exception $e) {
        logError('Authentication error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur système'];
    }
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return false;
    }

    // Vérifier l'expiration
    if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time']) > SESSION_LIFETIME) {
        session_destroy();
        return false;
    }

    // Vérifier l'IP
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Récupère l'utilisateur actuel
 */
function getCurrentUser() {
    return isLoggedIn() ? $_SESSION['user'] : null;
}

/**
 * Récupère le rôle de l'utilisateur
 */
function getUserRole() {
    $user = getCurrentUser();
    return $user['profil'] ?? null;
}

/**
 * Exige une authentification
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . LOGIN_URL);
        exit;
    }
}

/**
 * Déconnecte l'utilisateur
 */
function logout() {
    if (isset($_SESSION['user'])) {
        logSecurityEvent('logout', ['user_id' => $_SESSION['user']['id']]);
    }
    
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], 
            $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// Fonctions de vérification de rôles
function isAdmin() { return getUserRole() === USER_TYPE_ADMIN; }
function isTeacher() { return getUserRole() === USER_TYPE_TEACHER; }
function isStudent() { return getUserRole() === USER_TYPE_STUDENT; }
function isParent() { return getUserRole() === USER_TYPE_PARENT; }
function isVieScolaire() { return getUserRole() === USER_TYPE_STAFF; }

// Fonctions de permissions
function canManageNotes() {
    return in_array(getUserRole(), [USER_TYPE_ADMIN, USER_TYPE_TEACHER, USER_TYPE_STAFF]);
}

function canManageAbsences() {
    return in_array(getUserRole(), [USER_TYPE_ADMIN, USER_TYPE_TEACHER, USER_TYPE_STAFF]);
}

function canManageDevoirs() {
    return in_array(getUserRole(), [USER_TYPE_ADMIN, USER_TYPE_TEACHER]);
}

function getUserFullName() {
    $user = getCurrentUser();
    return $user ? ($user['prenom'] . ' ' . $user['nom']) : '';
}

function getUserInitials() {
    $user = getCurrentUser();
    if (!$user) return '??';
    return strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
}
