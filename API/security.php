<?php
/**
 * Wrapper de sécurité - Redirige vers le module de sécurité principal
 * Conservé pour compatibilité avec l'ancien code
 */

// Charger le module de sécurité principal
require_once __DIR__ . '/core/Security.php';

// Wrapper functions pour compatibilité
if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

if (!function_exists('cleanExpiredSessions')) {
    function cleanExpiredSessions() {
        if (session_status() === PHP_SESSION_NONE) return;
        
        // Nettoyer les sessions expirées si la fonction existe dans core/Security.php
        if (function_exists('\\Pronote\\Security\\cleanExpiredSessions')) {
            return \Pronote\Security\cleanExpiredSessions();
        }
        
        return true;
    }
}
