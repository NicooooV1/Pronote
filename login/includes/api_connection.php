<?php
/**
 * API Connection Helper for Login Module
 * Replaces the old database.php file
 */

// Empêcher les accès directs
if (!defined('ABSPATH') && basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Accès direct non autorisé');
}

// Configuration de base de données par défaut (fallback)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'db_MASSE');
if (!defined('DB_USER')) define('DB_USER', '22405372');
if (!defined('DB_PASS')) define('DB_PASS', '807014');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Variable globale pour la connexion
global $pdo;

// Si la connexion n'existe pas déjà, essayer de la créer
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Essayer d'abord de charger la configuration depuis l'API
    $config_loaded = false;
    
    // Chemins possibles pour la configuration API
    $possible_config_paths = [
        dirname(dirname(dirname(__DIR__))) . '/API/config/env.php',
        dirname(dirname(__DIR__)) . '/API/config/env.php',
        dirname(dirname(dirname(dirname(__DIR__)))) . '/API/config/env.php'
    ];
    
    foreach ($possible_config_paths as $config_path) {
        if (file_exists($config_path)) {
            try {
                require_once $config_path;
                $config_loaded = true;
                break;
            } catch (Exception $e) {
                error_log("Erreur lors du chargement de la configuration API: " . $e->getMessage());
            }
        }
    }
    
    // Essayer de charger path_helper si la config n'a pas été trouvée
    if (!$config_loaded) {
        $path_helper_found = false;
        $possible_paths = [
            dirname(dirname(dirname(__DIR__))) . '/API/path_helper.php',
            dirname(dirname(__DIR__)) . '/API/path_helper.php',
            dirname(dirname(dirname(dirname(__DIR__)))) . '/API/path_helper.php'
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                if (!defined('ABSPATH')) define('ABSPATH', dirname(dirname(__FILE__)));
                try {
                    require_once $path;
                    
                    if (defined('API_CORE_PATH') && file_exists(API_CORE_PATH)) {
                        require_once API_CORE_PATH;
                        $path_helper_found = true;
                        break;
                    }
                } catch (Exception $e) {
                    error_log("Erreur lors du chargement de path_helper: " . $e->getMessage());
                }
            }
        }
    }
    
    // Créer la connexion PDO si elle n'existe toujours pas
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        try {
            $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
            $dbName = defined('DB_NAME') ? DB_NAME : 'db_MASSE';
            $dbUser = defined('DB_USER') ? DB_USER : '22405372';
            $dbPass = defined('DB_PASS') ? DB_PASS : '807014';
            $dbCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            
            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10
            ];
            
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            
            // Ajouter à la variable globale
            $GLOBALS['pdo'] = $pdo;
            
        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            
            // Message d'erreur détaillé pour le débogage
            $error_details = [
                'Host' => $dbHost ?? 'Non défini',
                'Database' => $dbName ?? 'Non défini', 
                'User' => $dbUser ?? 'Non défini',
                'Error' => $e->getMessage()
            ];
            
            error_log("Détails de l'erreur de connexion: " . json_encode($error_details));
            throw new Exception("Impossible de se connecter à la base de données. Vérifiez la configuration.");
        }
    }
}

/**
 * Get database connection from API or create new one
 */
function getLoginDatabaseConnection() {
    global $pdo;
    
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }
    
    throw new Exception("Connexion à la base de données non disponible");
}

/**
 * Execute query using API connection
 */
function executeQuery($sql, $params = []) {
    try {
        $pdo = getLoginDatabaseConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (Exception $e) {
        error_log("Erreur d'exécution de requête: " . $e->getMessage());
        return false;
    }
}

/**
 * Test database connection
 */
function testDatabaseConnection() {
    try {
        $pdo = getLoginDatabaseConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log("Test de connexion échoué: " . $e->getMessage());
        return false;
    }
}
?>
