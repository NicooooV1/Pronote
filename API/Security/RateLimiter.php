<?php
/**
 * Rate Limiter - Token Bucket Algorithm
 * Stockage en session (peut être étendu à Redis)
 */

namespace Pronote\Security;

class RateLimiter {
    private const SESSION_KEY = '_rate_limits';
    private $storage;
    
    public function __construct($storage = 'session') {
        $this->storage = $storage;
        
        if ($storage === 'session' && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }
    
    /**
     * Tente une action
     * @return bool True si autorisé, false si limite atteinte
     */
    public function attempt($key, $maxAttempts = 5, $decayMinutes = 1) {
        $key = $this->resolveKey($key);
        
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }
        
        $this->hit($key, $decayMinutes * 60);
        
        return true;
    }
    
    /**
     * Vérifie si trop de tentatives
     */
    public function tooManyAttempts($key, $maxAttempts) {
        $key = $this->resolveKey($key);
        
        if ($this->attempts($key) >= $maxAttempts) {
            if ($this->hasExpired($key)) {
                $this->resetAttempts($key);
                return false;
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Incrémente le compteur
     */
    public function hit($key, $decaySeconds = 60) {
        $key = $this->resolveKey($key);
        $data = $this->get($key);
        
        if (!$data) {
            $this->set($key, [
                'count' => 1,
                'reset_at' => time() + $decaySeconds
            ]);
        } else {
            $data['count']++;
            $this->set($key, $data);
        }
    }
    
    /**
     * Nombre de tentatives
     */
    public function attempts($key) {
        $key = $this->resolveKey($key);
        $data = $this->get($key);
        
        return $data ? (int)$data['count'] : 0;
    }
    
    /**
     * Reset les tentatives
     */
    public function resetAttempts($key) {
        $key = $this->resolveKey($key);
        $this->clear($key);
    }
    
    /**
     * Temps restant avant reset (en secondes)
     */
    public function availableIn($key) {
        $key = $this->resolveKey($key);
        $data = $this->get($key);
        
        if (!$data) {
            return 0;
        }
        
        return max(0, $data['reset_at'] - time());
    }
    
    /**
     * Tentatives restantes
     */
    public function remaining($key, $maxAttempts) {
        $key = $this->resolveKey($key);
        $attempts = $this->attempts($key);
        
        return max(0, $maxAttempts - $attempts);
    }
    
    /**
     * Vérifie si expiré
     */
    protected function hasExpired($key) {
        $data = $this->get($key);
        
        return $data && time() >= $data['reset_at'];
    }
    
    /**
     * Résout la clé (hash pour sécurité)
     */
    protected function resolveKey($key) {
        return 'rate_limit:' . hash('sha256', $key);
    }
    
    /**
     * Récupère une valeur
     */
    protected function get($key) {
        if ($this->storage === 'session') {
            return $_SESSION[self::SESSION_KEY][$key] ?? null;
        }
        // TODO: Redis support
        return null;
    }
    
    /**
     * Stocke une valeur
     */
    protected function set($key, $value) {
        if ($this->storage === 'session') {
            $_SESSION[self::SESSION_KEY][$key] = $value;
        }
        // TODO: Redis support
    }
    
    /**
     * Supprime une valeur
     */
    protected function clear($key) {
        if ($this->storage === 'session') {
            unset($_SESSION[self::SESSION_KEY][$key]);
        }
        // TODO: Redis support
    }
    
    /**
     * Nettoie les entrées expirées
     */
    public function cleanup() {
        if ($this->storage !== 'session') {
            return;
        }
        
        $now = time();
        $limits = $_SESSION[self::SESSION_KEY] ?? [];
        
        foreach ($limits as $key => $data) {
            if ($now >= $data['reset_at']) {
                unset($_SESSION[self::SESSION_KEY][$key]);
            }
        }
    }
}
