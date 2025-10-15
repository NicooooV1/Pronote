<?php
/**
 * Module de sécurité centralisé
 */

if (!defined('PRONOTE_SECURITY_LOADED')) {
    define('PRONOTE_SECURITY_LOADED', true);
}

/**
 * Génère un token CSRF sécurisé
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    // Nettoyer les tokens expirés
    $now = time();
    foreach ($_SESSION['csrf_tokens'] as $token => $timestamp) {
        if ($now - $timestamp > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION['csrf_tokens'][$token]);
        }
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$token] = $now;
    
    return $token;
}

/**
 * Valide un token CSRF
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($token) || !isset($_SESSION['csrf_tokens'][$token])) {
        logSecurityEvent('csrf_token_invalid', ['token' => substr($token, 0, 8) . '...']);
        http_response_code(403);
        die('Token de sécurité invalide');
    }
    
    // Vérifier l'expiration
    if (time() - $_SESSION['csrf_tokens'][$token] > 3600) {
        unset($_SESSION['csrf_tokens'][$token]);
        logSecurityEvent('csrf_token_expired', ['token' => substr($token, 0, 8) . '...']);
        http_response_code(403);
        die('Token de sécurité expiré');
    }
    
    // Token valide, le supprimer pour usage unique
    unset($_SESSION['csrf_tokens'][$token]);
    
    return true;
}

/**
 * Validation d'email sécurisée
 */
function validateEmail($email) {
    if (empty($email)) {
        return false;
    }
    
    // Validation de base
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Vérifications supplémentaires
    if (strlen($email) > 254) {
        return false;
    }
    
    // Vérifier les domaines suspects
    $suspiciousDomains = ['tempmail.', 'guerrillamail.', '10minutemail.'];
    foreach ($suspiciousDomains as $domain) {
        if (strpos($email, $domain) !== false) {
            return false;
        }
    }
    
    return true;
}

/**
 * Validation de mot de passe sécurisée
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = "Le mot de passe doit contenir au moins 12 caractères";
    }
    
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
    
    // Vérifier les mots de passe communs
    $commonPasswords = ['password123', 'admin123', 'azerty123', 'qwerty123'];
    if (in_array(strtolower($password), $commonPasswords)) {
        $errors[] = "Ce mot de passe est trop commun";
    }
    
    return empty($errors) ? true : $errors;
}

/**
 * Validation de date
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Nettoyage sécurisé des entrées
 */
function sanitizeInput($input, $type = 'string') {
    if ($input === null) {
        return null;
    }
    
    switch ($type) {
        case 'string':
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Logging sécurisé des événements
 */
function logSecurityEvent($event, $data = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'user_id' => $_SESSION['user']['id'] ?? 'anonymous',
        'data' => $data
    ];
    
    $logLine = json_encode($logEntry) . "\n";
    
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Logging des erreurs sécurisé
 */
function logError($message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ERROR',
        'message' => $message,
        'context' => $context,
        'file' => debug_backtrace()[1]['file'] ?? 'unknown',
        'line' => debug_backtrace()[1]['line'] ?? 'unknown'
    ];
    
    $logLine = json_encode($logEntry) . "\n";
    
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Protection contre les attaques par force brute
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
    if (session_status() === PHP_SESSION_NONE) {
        return true;
    }
    
    $key = 'rate_limit_' . hash('sha256', $identifier);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    if ($now - $data['start'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    $_SESSION[$key]['count']++;
    
    if ($data['count'] >= $maxAttempts) {
        logSecurityEvent('rate_limit_exceeded', ['identifier' => $identifier]);
        return false;
    }
    
    return true;
}

/**
 * Redirection sécurisée
 */
function redirectTo($url, $message = null, $type = 'info') {
    if ($message) {
        setFlashMessage($type, $message);
    }
    
    // Validation de l'URL pour éviter les redirections ouvertes
    if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^[a-zA-Z0-9\/_\-\.]+\.php(\?.*)?$/', $url)) {
        $url = '/';
    }
    
    header('Location: ' . $url);
    exit;
}

/**
 * Gestion des messages flash
 */
function setFlashMessage($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Récupération des messages flash
 */
function getFlashMessages() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    
    return $messages;
}

/**
 * Formatage sécurisé des dates
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Nettoyage des sessions expirées
 */
function cleanExpiredSessions() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $now = time();
    $sessionLifetime = ini_get('session.gc_maxlifetime') ?: 1440;
    
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $now;
        return true;
    }
    
    if ($now - $_SESSION['last_activity'] > $sessionLifetime) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = $now;
    return true;
}
