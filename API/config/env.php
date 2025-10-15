<?php
/**
 * Configuration d'environnement
 * Ce fichier devrait être différent pour chaque environnement
 */

// Base URL de l'application
if (!defined('BASE_URL')) define('BASE_URL', '/~u22405372/SAE/Pronote');

// URL absolue (pour les redirections externes si nécessaire)
if (!defined('APP_URL')) define('APP_URL', 'https://r207.borelly.net/~u22405372/SAE/Pronote');

// Environnement (development, production, test)
if (!defined('APP_ENV')) define('APP_ENV', 'development');

// Configuration de la base de données
if (!defined('DB_HOST')) define('DB_HOST', '192.168.1.250');
if (!defined('DB_NAME')) define('DB_NAME', 'db_MASSE');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '9a4Cz5LP!^i-N5Qc/?k5');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Chemin racine de l'application
if (!defined('APP_ROOT')) define('APP_ROOT', realpath(dirname(__FILE__) . '/../../'));

// URLs communes construites avec BASE_URL pour garantir le bon chemin
if (!defined('LOGIN_URL')) define('LOGIN_URL', BASE_URL . '/login/public/index.php');
if (!defined('LOGOUT_URL')) define('LOGOUT_URL', BASE_URL . '/login/public/logout.php');
if (!defined('HOME_URL')) define('HOME_URL', BASE_URL . '/accueil/accueil.php');
