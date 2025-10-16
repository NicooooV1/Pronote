<?php
/**
 * Database Connection Manager - Singleton Pattern
 */

namespace Pronote\Database;

class Database {
    private static $instance = null;
    private $pdo;
    private $config;
    
    private function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \Exception("Database config required for first initialization");
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    private function connect() {
        $host = $this->config['host'] ?? 'localhost';
        $name = $this->config['name'] ?? '';
        $user = $this->config['user'] ?? 'root';
        $pass = $this->config['pass'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';

        // Ajout d'un support pour unix_socket si défini dans la config
        $dsn = '';
        if (!empty($this->config['unix_socket'])) {
            $dsn = "mysql:unix_socket={$this->config['unix_socket']};dbname={$name};charset={$charset}";
        } else {
            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        }

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
        ];

        try {
            $this->pdo = new \PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            // Ajout d'un message d'aide si "No such file or directory"
            if (strpos($msg, 'No such file or directory') !== false) {
                $msg .= " | Astuce: Essayez d'utiliser '127.0.0.1' au lieu de 'localhost' pour DB_HOST ou configurez correctement unix_socket.";
            }
            throw new \Exception("Database connection failed: " . $msg);
        }
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    public function query() {
        return new QueryBuilder($this->pdo);
    }
    
    public function table($table) {
        return $this->query()->table($table);
    }
    
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}
