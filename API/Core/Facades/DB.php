<?php
declare(strict_types=1);

namespace Pronote\Core\Facades;

use API\Core\Facade;
use PDO;

/**
 * Facade DB - Proxy statique vers la connexion PDO
 */
final class DB extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'db';
    }

    public static function getConnection(): PDO
    {
        $db = app('db');
        return $db->getConnection();
    }

    // Compat: certains appels utilisent getPDO()
    public static function getPDO(): PDO
    {
        return self::getConnection();
    }

    private static function createConnection(): PDO
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        
        if (!file_exists($envFile)) {
            throw new \RuntimeException('Fichier .env introuvable');
        }

        $config = parse_ini_file($envFile);
        
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['DB_HOST'] ?? 'localhost',
            $config['DB_PORT'] ?? 3306,
            $config['DB_NAME'] ?? '',
            $config['DB_CHARSET'] ?? 'utf8mb4'
        );

        return new PDO(
            $dsn,
            $config['DB_USER'] ?? '',
            $config['DB_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getPDO()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
