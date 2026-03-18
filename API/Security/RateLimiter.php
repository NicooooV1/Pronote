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
    protected $tableName = 'api_rate_limits';

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo;
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
     * Retourne l'IP cliente, en tenant compte des proxies de confiance.
     * Si APP_ENV=production et TRUSTED_PROXIES est défini dans .env,
     * les headers X-Forwarded-For / X-Real-IP sont acceptés.
     * Sinon, seul REMOTE_ADDR est utilisé pour éviter le header spoofing.
     */
    protected function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // N'accepter les headers proxy que si des proxies de confiance sont configurés
        $trustedProxies = function_exists('env') ? env('TRUSTED_PROXIES', '') : ($_ENV['TRUSTED_PROXIES'] ?? '');

        if (empty($trustedProxies)) {
            return $remoteAddr;
        }

        // Vérifier que REMOTE_ADDR est bien un proxy de confiance
        $trusted = array_map('trim', explode(',', $trustedProxies));
        if (!in_array($remoteAddr, $trusted, true)) {
            return $remoteAddr;
        }

        // Le proxy est de confiance : lire X-Forwarded-For
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $clientIp = $ips[0]; // Première IP = client originel
            if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                return $clientIp;
            }
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $realIp = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }

        return $remoteAddr;
    }

    /**
     * Génère un identifiant unique basé sur l'IP et la clé
     */
    protected function getIdentifier($key)
    {
        $ip = $this->getClientIp();
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

}
