<?php
/**
 * Module de sécurité CENTRALISÉ UNIQUE
 * Toutes les fonctions de sécurité de l'application
 * 
 * @version 4.0
 */

if (!defined('PRONOTE_SECURITY_LOADED')) {
    define('PRONOTE_SECURITY_LOADED', true);
}

// ==================== CSRF ====================

function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    // Nettoyer tokens expirés
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

function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (empty($token) || !isset($_SESSION['csrf_tokens'][$token])) {
        logSecurityEvent('csrf_invalid', ['token' => substr($token, 0, 8)]);
        http_response_code(403);
        die('Token de sécurité invalide');
    }
    
    if (time() - $_SESSION['csrf_tokens'][$token] > CSRF_TOKEN_LIFETIME) {
        unset($_SESSION['csrf_tokens'][$token]);
        logSecurityEvent('csrf_expired', ['token' => substr($token, 0, 8)]);
        http_response_code(403);
        die('Token de sécurité expiré');
    }
    
    unset($_SESSION['csrf_tokens'][$token]);
    return true;
}

// ==================== VALIDATION ====================

function validateEmail($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    if (strlen($email) > 254) {
        return false;
    }
    
    return true;
}

function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Le mot de passe doit contenir au moins " . PASSWORD_MIN_LENGTH . " caractères";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Au moins une majuscule requise";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Au moins une minuscule requise";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Au moins un chiffre requis";
    }
    
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Au moins un caractère spécial requis";
    }
    
    return empty($errors) ? true : $errors;
}

function sanitizeInput($input, $type = 'string') {
    if ($input === null) return null;
    
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

// ==================== RATE LIMITING ====================

function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
    if (session_status() === PHP_SESSION_NONE) return true;
    
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
    
    return $data['count'] < $maxAttempts;
}

// ==================== LOGGING ====================

function logSecurityEvent($event, $data = []) {
    $logDir = LOGS_PATH;
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    
    $user = getCurrentUser();
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $user['id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'data' => $data
    ];
    
    $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
    @file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

function logError($message, $context = []) {
    $logDir = LOGS_PATH;
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ERROR',
        'message' => $message,
        'context' => $context,
        'file' => debug_backtrace()[1]['file'] ?? 'unknown',
        'line' => debug_backtrace()[1]['line'] ?? 'unknown'
    ];
    
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    @file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}
