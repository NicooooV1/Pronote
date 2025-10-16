<?php
/**
 * Point d'entrée principal de l'API Pronote
 */

// Charger le bootstrap
require_once __DIR__ . '/bootstrap.php';

// Démarrer l'application
$app->boot();

/**
 * Fonction helper pour récupérer l'utilisateur actuel
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        return app('auth')->user();
    }
}

/**
 * Fonction helper pour vérifier l'authentification
 */
if (!function_exists('requireAuth')) {
    function requireAuth() {
        $user = getCurrentUser();
        
        if (!$user) {
            $loginUrl = defined('LOGIN_URL') ? LOGIN_URL : '/login/public/index.php';
            header('Location: ' . $loginUrl);
            exit;
        }
        
        return $user;
    }
}

/**
 * Fonction helper pour récupérer la connexion à la base de données
 */
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        return app('db')->getConnection();
    }
}

/**
 * Fonction helper pour logger des erreurs
 */
if (!function_exists('logError')) {
    function logError($message, $context = []) {
        error_log($message . (!empty($context) ? ' ' . json_encode($context) : ''));
    }
}

/**
 * Fonction helper pour valider des données
 */
if (!function_exists('validate')) {
    function validate($data, $rules) {
        $validator = app('validator');
        return $validator->validate($data, $rules);
    }
}

/**
 * Fonction helper pour générer un token CSRF
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        return app('csrf')->getToken();
    }
}

/**
 * Fonction helper pour générer un champ CSRF
 */
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return app('csrf')->field();
    }
}
