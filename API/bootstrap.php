<?php
/**
 * Bootstrap - Point d'entrée unique de l'API
 * À inclure dans CHAQUE page de l'application
 */

if (defined('PRONOTE_BOOTSTRAP_LOADED')) return;
define('PRONOTE_BOOTSTRAP_LOADED', true);

// Chargement dans l'ordre strict
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth_central.php';
require_once __DIR__ . '/core/Security.php';
require_once __DIR__ . '/core/utils.php';

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nettoyage des sessions expirées
cleanExpiredSessions();

// Configuration des erreurs selon l'environnement
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR | E_WARNING);
}
