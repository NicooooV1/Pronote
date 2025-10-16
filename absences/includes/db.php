<?php
/**
 * Module de connexion à la base de données pour le module Absences
 * (bridge vers API/bootstrap.php)
 */

// Essayer d'inclure le bootstrap central avec chemin absolu
$bootstrapPath = __DIR__ . '/../../API/bootstrap.php';

// Si le fichier bootstrap existe, l'inclure
if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
} else {
    // Fallback : essayer le fichier core.php
    $corePath = __DIR__ . '/../../API/core.php';
    if (file_exists($corePath)) {
        require_once $corePath;
    }
}

// Si la variable $pdo n'est pas disponible, essayer de créer une connexion directement
if (!isset($pdo)) {
    try {
        // Essayer de charger le fichier de configuration avec chemin absolu
        $configPath = __DIR__ . '/../../API/config/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }

        // Définir les valeurs par défaut si non définies dans config.php
        if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
        if (!defined('DB_NAME')) define('DB_NAME', 'db_MASSE');
        if (!defined('DB_USER')) define('DB_USER', '22405372');
        if (!defined('DB_PASS')) define('DB_PASS', '807014');
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Rendre la connexion disponible globalement
        $GLOBALS['pdo'] = $pdo;
        
        // Journaliser le succès de la connexion
        error_log("Connexion à la base de données établie avec succès dans absences/includes/db.php");
    } catch (PDOException $e) {
        // Journaliser l'erreur
        error_log("Erreur de connexion à la base de données dans absences/includes/db.php: " . $e->getMessage());
        
        // Créer une variable pour indiquer le problème
        $dbConnectionFailed = true;
    }
}

// Vérifier si la connexion est établie
if (!isset($pdo) && !isset($dbConnectionFailed)) {
    error_log("La variable PDO n'est pas définie et aucune erreur de connexion n'a été enregistrée");
}
?>
