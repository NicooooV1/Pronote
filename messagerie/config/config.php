<?php
/**
 * Configuration du module messagerie - Utilise l'API centralisée
 */

// Définir ABSPATH pour la sécurité
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__FILE__)));
}

// Charger le CSRF messagerie AVANT l'API centralisée.
// L'API utilise des gardes function_exists() : l'implémentation session-unique
// de la messagerie (compatible AJAX) prend donc le dessus automatiquement.
require_once dirname(__DIR__) . '/core/csrf.php';

// Charger l'API centralisée
require_once dirname(dirname(__DIR__)) . '/API/core.php';

// Récupérer la connexion PDO depuis l'API
$pdo = getPDO();
$GLOBALS['pdo'] = $pdo;

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