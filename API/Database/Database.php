<?php
/**
 * Database Connection Manager
 */

namespace API\Database;

use PDO;
use PDOException;

class Database
{
    protected $connection;
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Établit la connexion
     */
    public function connect()
    {
        if ($this->connection) {
            return $this->connection;
        }

        try {
            // Forcer TCP/IP: "localhost" utilise le socket Unix, "127.0.0.1" force TCP
            $host = $this->config['host'];
            if (strtolower($host) === 'localhost') {
                $host = '127.0.0.1';
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                $this->config['port'] ?? 3306,
                $this->config['database'],
                $this->config['charset'] ?? 'utf8mb4'
            );

            $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ];

            // Persistent connections in production
            if (($this->config['persistent'] ?? false) || (getenv('APP_ENV') === 'production' && getenv('DB_PERSISTENT') === 'true')) {
                $options[PDO::ATTR_PERSISTENT] = true;
            }

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );

            return $this->connection;
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "Erreur de connexion à la base de données: " . $e->getMessage()
            );
        }
    }

    /**
     * Retourne la connexion
     */
    public function getConnection()
    {
        if (!$this->connection) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Crée un query builder
     */
    public function table($table)
    {
        return new QueryBuilder($this->getConnection(), $table);
    }
}
