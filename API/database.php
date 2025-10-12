<?php
/**
 * Gestionnaire de connexion à la base de données
 * Connexion centralisée et sécurisée avec pool de connexions
 */

if (!defined('PRONOTE_DATABASE_LOADED')) {
    define('PRONOTE_DATABASE_LOADED', true);
}

// Inclure la configuration
require_once __DIR__ . '/config/env.php';

// Variable globale pour la connexion
$GLOBALS['db_connection'] = null;

/**
 * Obtient une connexion à la base de données
 * @return PDO Instance PDO sécurisée
 * @throws Exception En cas d'erreur de connexion
 */
function getDBConnection() {
    // Réutiliser la connexion existante si disponible
    if ($GLOBALS['db_connection'] instanceof PDO) {
        try {
            // Tester la connexion
            $GLOBALS['db_connection']->query('SELECT 1');
            return $GLOBALS['db_connection'];
        } catch (PDOException $e) {
            // Connexion fermée, en créer une nouvelle
            $GLOBALS['db_connection'] = null;
        }
    }
    
    try {
        // Configuration de la connexion
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname = defined('DB_NAME') ? DB_NAME : 'pronote';
        $username = defined('DB_USER') ? DB_USER : 'root';
        $password = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        
        // Options PDO sécurisées
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ];
        
        // Créer la connexion
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Configuration de sécurité MySQL
        $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        
        // Stocker la connexion
        $GLOBALS['db_connection'] = $pdo;
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Logger l'erreur sans exposer les détails
        error_log("Erreur de connexion à la base de données: " . $e->getMessage());
        throw new Exception("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
    }
}

/**
 * Exécute une requête préparée de manière sécurisée
 * @param string $query Requête SQL
 * @param array $params Paramètres de la requête
 * @return PDOStatement
 */
function executeQuery($query, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Récupère un seul enregistrement
 * @param string $query Requête SQL
 * @param array $params Paramètres de la requête
 * @return array|false
 */
function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetch();
}

/**
 * Récupère tous les enregistrements
 * @param string $query Requête SQL
 * @param array $params Paramètres de la requête
 * @return array
 */
function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchAll();
}

/**
 * Récupère une seule valeur
 * @param string $query Requête SQL
 * @param array $params Paramètres de la requête
 * @return mixed
 */
function fetchColumn($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchColumn();
}

/**
 * Commence une transaction
 */
function beginTransaction() {
    $pdo = getDBConnection();
    return $pdo->beginTransaction();
}

/**
 * Valide une transaction
 */
function commit() {
    $pdo = getDBConnection();
    return $pdo->commit();
}

/**
 * Annule une transaction
 */
function rollback() {
    $pdo = getDBConnection();
    return $pdo->rollback();
}

/**
 * Obtient le dernier ID inséré
 * @return string
 */
function lastInsertId() {
    $pdo = getDBConnection();
    return $pdo->lastInsertId();
}

/**
 * Ferme la connexion à la base de données
 */
function closeConnection() {
    $GLOBALS['db_connection'] = null;
}

/**
 * Vérifie la santé de la base de données
 * @return array Statut de la base de données
 */
function checkDatabaseHealth() {
    try {
        $pdo = getDBConnection();
        
        // Tester la connexion
        $stmt = $pdo->query('SELECT 1');
        $connectionOk = $stmt !== false;
        
        // Vérifier les tables essentielles
        $tables = ['administrateurs', 'eleves', 'professeurs', 'notes', 'matieres'];
        $tablesExist = [];
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            $tablesExist[$table] = $stmt->rowCount() > 0;
        }
        
        // Vérifier les performances
        $start = microtime(true);
        $pdo->query('SELECT COUNT(*) FROM notes');
        $queryTime = microtime(true) - $start;
        
        return [
            'status' => 'healthy',
            'connection' => $connectionOk,
            'tables' => $tablesExist,
            'query_time' => round($queryTime * 1000, 2) . 'ms',
            'checked_at' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'error' => $e->getMessage(),
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }
}
