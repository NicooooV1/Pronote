<?php
declare(strict_types=1);

namespace API\Core\Facades;

use PDO;
use PDOException;

class DB
{
    private static $pdo = null;

    /**
     * Get PDO instance
     * @return PDO
     */
    public static function getPDO()
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

    /**
     * Initialize database connection
     */
    private static function connect()
    {
        $config_path = dirname(dirname(__DIR__)) . '/config/database.php';
        
        if (!file_exists($config_path)) {
            throw new \Exception("Le fichier de configuration database.php est introuvable");
        }

        $config = require $config_path;

        try {
            self::$pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur de connexion DB: " . $e->getMessage());
            throw new \Exception("Erreur de connexion à la base de données");
        }
    }

    /**
     * Execute a query
     * @param string $query
     * @param array $params
     * @return \PDOStatement
     */
    public static function query($query, $params = [])
    {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }
}
