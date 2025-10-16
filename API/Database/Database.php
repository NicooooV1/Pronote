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
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['database']
            );

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
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
