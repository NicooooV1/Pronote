<?php
/**
 * Système d'authentification centralisé et sécurisé
 * Version 2.0 - Sécurité renforcée
 */

// Prévenir l'inclusion multiple
if (defined('PRONOTE_AUTH_LOADED')) {
    return;
}
define('PRONOTE_AUTH_LOADED', true);

// Inclure les dépendances
require_once __DIR__ . '/core/security.php';

// Configuration sécurisée des sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Nettoyer les sessions expirées
cleanExpiredSessions();

/**
 * Vérification de connexion sécurisée
 */
function isLoggedIn() {
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return false;
    }
    
    // Vérifier l'intégrité de la session
    if (!isset($_SESSION['user']['id'], $_SESSION['user']['profil'], $_SESSION['auth_time'])) {
        return false;
    }
    
    // Vérifier l'expiration de session
    $sessionLifetime = 3600; // 1 heure
    if (time() - $_SESSION['auth_time'] > $sessionLifetime) {
        session_destroy();
        return false;
    }
    
    // Renouveler l'ID de session périodiquement
    if (!isset($_SESSION['last_regeneration']) || 
        time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    return true;
}

/**
 * Authentification requise avec redirection
 */
function requireAuth() {
    if (!isLoggedIn()) {
        logSecurityEvent('unauthorized_access_attempt', [
            'requested_url' => $_SERVER['REQUEST_URI'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ]);
        
        redirectTo(LOGIN_URL ?? '../login/public/index.php', 'Connexion requise');
    }
}

/**
 * Récupération sécurisée de l'utilisateur courant
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return $_SESSION['user'];
}

/**
 * Nom complet de l'utilisateur
 */
function getUserFullName() {
    $user = getCurrentUser();
    if (!$user) {
        return 'Utilisateur inconnu';
    }
    
    return trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
}

/**
 * Initiales de l'utilisateur
 */
function getUserInitials() {
    $user = getCurrentUser();
    if (!$user) {
        return '??';
    }
    
    $prenom = $user['prenom'] ?? '';
    $nom = $user['nom'] ?? '';
    
    return strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
}

/**
 * Rôle de l'utilisateur
 */
function getUserRole() {
    $user = getCurrentUser();
    return $user['profil'] ?? 'guest';
}

/**
 * Vérifications de rôles
 */
function isAdmin() {
    return getUserRole() === 'administrateur';
}

function isTeacher() {
    return getUserRole() === 'professeur';
}

function isStudent() {
    return getUserRole() === 'eleve';
}

function isParent() {
    return getUserRole() === 'parent';
}

function isVieScolaire() {
    return getUserRole() === 'vie_scolaire';
}

/**
 * Permissions pour gérer les notes
 */
function canManageNotes() {
    $role = getUserRole();
    return in_array($role, ['administrateur', 'professeur', 'vie_scolaire']);
}

/**
 * Permissions pour gérer les absences
 */
function canManageAbsences() {
    $role = getUserRole();
    return in_array($role, ['administrateur', 'vie_scolaire', 'professeur']);
}

/**
 * Permissions pour gérer les devoirs
 */
function canManageDevoirs() {
    $role = getUserRole();
    return in_array($role, ['administrateur', 'professeur']);
}

/**
 * Permissions pour gérer les événements
 */
function canManageEvents() {
    $role = getUserRole();
    return in_array($role, ['administrateur', 'professeur', 'vie_scolaire']);
}

/**
 * Permissions pour la messagerie
 */
function canUseMessaging() {
    $role = getUserRole();
    return in_array($role, ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent']);
}

/**
 * Connexion sécurisée
 */
function loginUser($userType, $userData, $remember = false) {
    // Validation des données
    if (!$userData || !is_array($userData) || !isset($userData['id'])) {
        return false;
    }
    
    // Régénérer l'ID de session pour éviter la fixation
    session_regenerate_id(true);
    
    // Stocker les données utilisateur
    $_SESSION['user'] = [
        'id' => $userData['id'],
        'nom' => $userData['nom'] ?? '',
        'prenom' => $userData['prenom'] ?? '',
        'mail' => $userData['mail'] ?? '',
        'profil' => $userType,
        'identifiant' => $userData['identifiant'] ?? ''
    ];
    
    $_SESSION['auth_time'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Logging de sécurité
    logSecurityEvent('user_login', [
        'user_id' => $userData['id'],
        'user_type' => $userType,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Gestion du "Se souvenir de moi" (optionnel)
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', 
                  isset($_SERVER['HTTPS']), true);
        
        // Stocker le token en base de données (à implémenter)
        // storeRememberToken($userData['id'], $userType, $token);
    }
    
    return true;
}

/**
 * Déconnexion sécurisée
 */
function logoutUser() {
    $user = getCurrentUser();
    
    if ($user) {
        logSecurityEvent('user_logout', [
            'user_id' => $user['id'],
            'user_type' => $user['profil']
        ]);
    }
    
    // Détruire toutes les données de session
    $_SESSION = [];
    
    // Détruire le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Détruire le cookie "remember me"
    setcookie('remember_token', '', time() - 3600, '/');
    
    // Détruire la session
    session_destroy();
    
    return true;
}

/**
 * Vérification de permission spécifique
 */
function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    $permissions = [
        'manage_users' => ['administrateur'],
        'manage_notes' => ['administrateur', 'professeur', 'vie_scolaire'],
        'manage_absences' => ['administrateur', 'vie_scolaire', 'professeur'],
        'manage_devoirs' => ['administrateur', 'professeur'],
        'manage_events' => ['administrateur', 'professeur', 'vie_scolaire'],
        'use_messaging' => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'view_all_notes' => ['administrateur', 'vie_scolaire'],
        'view_own_notes' => ['professeur', 'eleve', 'parent'],
        'export_data' => ['administrateur', 'vie_scolaire']
    ];
    
    if (!isset($permissions[$permission])) {
        return false;
    }
    
    return in_array($user['profil'], $permissions[$permission]);
}

/**
 * Middleware de vérification de permission
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        logSecurityEvent('permission_denied', [
            'permission' => $permission,
            'user_role' => getUserRole()
        ]);
        
        http_response_code(403);
        die('Accès refusé - Permission insuffisante');
    }
}

/**
 * Obtenir l'URL de redirection après connexion
 */
function getRedirectURL() {
    $role = getUserRole();
    
    $redirects = [
        'administrateur' => '/admin/dashboard.php',
        'professeur' => '/accueil/accueil.php',
        'vie_scolaire' => '/absences/absences.php',
        'eleve' => '/accueil/accueil.php',
        'parent' => '/accueil/accueil.php'
    ];
    
    return $redirects[$role] ?? '/accueil/accueil.php';
}

/**
 * Vérification de la sécurité du mot de passe
 */
function checkPasswordSecurity($password, $username = '') {
    $errors = [];
    
    // Longueur minimale
    if (strlen($password) < 12) {
        $errors[] = "Le mot de passe doit contenir au moins 12 caractères";
    }
    
    // Complexité
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une majuscule";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une minuscule";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
    }
    
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
    }
    
    // Vérifier qu'il ne contient pas le nom d'utilisateur
    if ($username && stripos($password, $username) !== false) {
        $errors[] = "Le mot de passe ne doit pas contenir le nom d'utilisateur";
    }
    
    return empty($errors) ? true : $errors;
}

/**
 * Hachage sécurisé des mots de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,       // 4 iterations
        'threads' => 3          // 3 threads
    ]);
}

/**
 * Vérification des mots de passe
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
