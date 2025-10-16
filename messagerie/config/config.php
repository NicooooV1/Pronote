<?php
/**
 * Configuration du module messagerie - Utilise l'API centralisée
 */

// Définir ABSPATH pour la sécurité
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__FILE__)));
}

// Charger l'API centralisée
$apiPath = dirname(dirname(__DIR__)) . '/API/core.php';
if (!file_exists($apiPath)) {
    die("Impossible de charger l'API. Vérifiez l'installation.");
}
require_once $apiPath;

// Utiliser la session de l'API
if (session_status() === PHP_SESSION_NONE) {
    session_name('pronote_session');
    session_start();
}

// Récupérer la connexion PDO depuis l'API
try {
    $pdo = getDatabaseConnection();
    $GLOBALS['pdo'] = $pdo;
} catch (Exception $e) {
    error_log("Erreur de connexion messagerie: " . $e->getMessage());
    die("Erreur de connexion à la base de données");
}

// Définir les chemins
if (!defined('BASE_URL')) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $baseUrl = rtrim($scriptDir, '/') . '/';
    define('BASE_URL', $baseUrl);
}

// Définir le chemin des logs
if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', __DIR__ . '/../logs');
}

// Créer le dossier de logs s'il n'existe pas
if (!is_dir(LOGS_PATH)) {
    @mkdir(LOGS_PATH, 0755, true);
}

/**
 * Fonction de journalisation simplifiée
 */
if (!function_exists('logMessage')) {
    function logMessage($message, $type = 'info') {
        $logFile = LOGS_PATH . '/app_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$type] $message\n";
        @file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }
}

/**
 * Fonction pour journaliser les uploads
 */
if (!function_exists('logUpload')) {
    function logUpload($message, $data = null) {
        $logFile = LOGS_PATH . '/uploads_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] $message";
        
        if ($data !== null) {
            $formattedMessage .= " - " . json_encode($data);
        }
        
        @file_put_contents($logFile, $formattedMessage . "\n", FILE_APPEND);
    }
}
?>