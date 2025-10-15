<?php
/**
 * Point d'entrée UNIQUE de l'API Pronote
 * À inclure dans CHAQUE page de l'application
 * 
 * Usage: require_once __DIR__ . '/../API/init.php';
 */

if (defined('PRONOTE_API_LOADED')) return;
define('PRONOTE_API_LOADED', true);

// Configuration de base PHP
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// Chargement dans l'ordre STRICT
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/core/constants.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Security.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Logger.php';
require_once __DIR__ . '/core/Utils.php';
require_once __DIR__ . '/core/Validator.php';

// Initialisation de la session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}

// Configuration des erreurs selon l'environnement
if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR | E_WARNING);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Fonction globale d'accès à la base de données
function db() {
    return Database::getInstance();
}

// Fonction globale de logging
function logMessage($message, $level = 'info', $context = []) {
    return Logger::log($message, $level, $context);
}
