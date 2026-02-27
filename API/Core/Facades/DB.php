<?php
declare(strict_types=1);

namespace API\Core\Facades;

use API\Core\Facade;
use PDO;

/**
 * Facade DB - Proxy statique vers le service 'db' du container
 * Toutes les connexions passent par API\Database\Database (centralisé).
 */
class DB extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'db';
    }

    /**
     * Retourne l'instance PDO centralisée
     * @return PDO
     */
    public static function getPDO(): PDO
    {
        return app('db')->getConnection();
    }

    /**
     * Exécute une requête préparée
     * @param string $query
     * @param array  $params
     * @return \PDOStatement
     */
    public static function query(string $query, array $params = []): \PDOStatement
    {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne un QueryBuilder pour une table
     * @param string $table
     * @return \API\Database\QueryBuilder
     */
    public static function table(string $table)
    {
        return app('db')->table($table);
    }
}
