<?php
namespace API\Security;

use PDO;

/**
 * Limiteur de taux de requêtes (stockage en base de données)
 */
class RateLimiter
{
    protected $pdo;
    protected $maxAttempts = 5;
    protected $decayMinutes = 1;
    protected $tableName = 'rate_limits';

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }

    /**
     * Configure max attempts
     */
    public function setMaxAttempts($max)
    {
        $this->maxAttempts = $max;
        return $this;
    }

    /**
     * Configure decay time
     */
    public function setDecayMinutes($minutes)
    {
        $this->decayMinutes = $minutes;
        return $this;
    }

    /**
     * Vérifie si trop de tentatives ont été effectuées
     */
    public function tooManyAttempts($key)
    {
        $attempts = $this->attempts($key);
        return $attempts >= $this->maxAttempts;
    }

    /**
     * Incrémente le nombre de tentatives
     */
    public function hit($key)
    {
        $identifier = $this->getIdentifier($key);
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->decayMinutes * 60));

        try {
            $pdo = $this->getPDO();
            
            // Vérifier si l'entrée existe
            $stmt = $pdo->prepare("
                SELECT id, attempts FROM {$this->tableName} 
                WHERE identifier = ? AND expires_at > NOW()
            ");
            $stmt->execute([$identifier]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Incrémenter
                $stmt = $pdo->prepare("
                    UPDATE {$this->tableName} 
                    SET attempts = attempts + 1, expires_at = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$expiresAt, $existing['id']]);
            } else {
                // Créer nouvelle entrée
                $stmt = $pdo->prepare("
                    INSERT INTO {$this->tableName} (identifier, attempts, expires_at) 
                    VALUES (?, 1, ?)
                ");
                $stmt->execute([$identifier, $expiresAt]);
            }
        } catch (\PDOException $e) {
            error_log("RateLimiter::hit error: " . $e->getMessage());
        }
    }

    /**
     * Retourne le nombre de tentatives
     */
    public function attempts($key)
    {
        $identifier = $this->getIdentifier($key);

        try {
            $pdo = $this->getPDO();
            $stmt = $pdo->prepare("
                SELECT attempts FROM {$this->tableName} 
                WHERE identifier = ? AND expires_at > NOW()
            ");
            $stmt->execute([$identifier]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int)$result['attempts'] : 0;
        } catch (\PDOException $e) {
            error_log("RateLimiter::attempts error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Réinitialise les tentatives
     */
    public function clear($key)
    {
        $identifier = $this->getIdentifier($key);

        try {
            $pdo = $this->getPDO();
            $stmt = $pdo->prepare("DELETE FROM {$this->tableName} WHERE identifier = ?");
            $stmt->execute([$identifier]);
        } catch (\PDOException $e) {
            error_log("RateLimiter::clear error: " . $e->getMessage());
        }
    }

    /**
     * Nettoie les entrées expirées
     */
    public function cleanup()
    {
        try {
            $pdo = $this->getPDO();
            $stmt = $pdo->prepare("DELETE FROM {$this->tableName} WHERE expires_at <= NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log("RateLimiter::cleanup error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Génère un identifiant unique basé sur l'IP et la clé
     */
    protected function getIdentifier($key)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return hash('sha256', $key . '|' . $ip);
    }

    /**
     * Récupère la connexion PDO
     */
    protected function getPDO()
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        // Fallback via container
        if (function_exists('app')) {
            $db = app('db');
            if ($db && method_exists($db, 'getConnection')) {
                $this->pdo = $db->getConnection();
                return $this->pdo;
            }
        }

        throw new \RuntimeException('PDO connection not available for RateLimiter');
    }

    /**
     * S'assure que la table existe
     */
    protected function ensureTableExists()
    {
        try {
            $pdo = $this->getPDO();
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->tableName} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    identifier VARCHAR(64) NOT NULL,
                    attempts INT NOT NULL DEFAULT 1,
                    expires_at DATETIME NOT NULL,
                    INDEX idx_identifier (identifier),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\PDOException $e) {
            error_log("RateLimiter table creation error: " . $e->getMessage());
        }
    }
}
