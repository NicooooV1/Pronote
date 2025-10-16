<?php
namespace API\Security;

/**
 * Limiteur de taux de requêtes
 */
class RateLimiter
{
    protected $maxAttempts = 5;
    protected $decayMinutes = 1;

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
        $cacheKey = $this->getCacheKey($key);
        
        if (!isset($_SESSION[$cacheKey])) {
            $_SESSION[$cacheKey] = [
                'count' => 0,
                'expires_at' => time() + ($this->decayMinutes * 60)
            ];
        }

        $_SESSION[$cacheKey]['count']++;
    }

    /**
     * Retourne le nombre de tentatives
     */
    public function attempts($key)
    {
        $cacheKey = $this->getCacheKey($key);
        
        if (!isset($_SESSION[$cacheKey])) {
            return 0;
        }

        $data = $_SESSION[$cacheKey];
        
        // Vérifier si expiré
        if ($data['expires_at'] < time()) {
            unset($_SESSION[$cacheKey]);
            return 0;
        }

        return $data['count'];
    }

    /**
     * Réinitialise les tentatives
     */
    public function clear($key)
    {
        $cacheKey = $this->getCacheKey($key);
        unset($_SESSION[$cacheKey]);
    }

    /**
     * Génère une clé de cache
     */
    protected function getCacheKey($key)
    {
        return 'rate_limit_' . md5($key);
    }
}
