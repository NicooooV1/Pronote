<?php
/**
 * CSRF Token Manager
 * Implémente le pattern Token Bucket avec rotation
 */

namespace API\Security;

class CSRF {
    const SESSION_KEY = 'csrf_tokens';
    
    protected $tokenName = 'csrf_token';
    private $lifetime;
    private $maxTokens;
    
    public function __construct($lifetime = 3600, $maxTokens = 10) {
        $this->lifetime = $lifetime;
        $this->maxTokens = $maxTokens;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }
    
    /**
     * Initialise le système CSRF
     */
    public function init() {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }
    
    /**
     * Génère un nouveau token
     */
    public function generate() {
        $this->cleanup();
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY][$token] = time();
        
        // Limiter le nombre de tokens
        if (count($_SESSION[self::SESSION_KEY]) > $this->maxTokens) {
            array_shift($_SESSION[self::SESSION_KEY]);
        }
        
        return $token;
    }
    
    /**
     * Alias pour generate()
     */
    public function getToken() {
        return $this->generate();
    }
    
    /**
     * Valide un token (usage unique)
     */
    public function validate($token) {
        if (empty($token)) {
            return false;
        }
        
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        
        // Token n'existe pas
        if (!isset($tokens[$token])) {
            return false;
        }
        
        // Token expiré
        if (time() - $tokens[$token] > $this->lifetime) {
            unset($_SESSION[self::SESSION_KEY][$token]);
            return false;
        }
        
        // Token valide - le supprimer (usage unique)
        unset($_SESSION[self::SESSION_KEY][$token]);
        
        return true;
    }
    
    /**
     * Vérifie sans supprimer (pour tests)
     */
    public function check($token) {
        if (empty($token)) {
            return false;
        }
        
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        
        if (!isset($tokens[$token])) {
            return false;
        }
        
        return (time() - $tokens[$token]) <= $this->lifetime;
    }
    
    /**
     * Nettoie les tokens expirés
     */
    public function cleanup() {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }
        
        $now = time();
        $tokens = $_SESSION[self::SESSION_KEY];
        
        $_SESSION[self::SESSION_KEY] = array_filter(
            $tokens,
            fn($timestamp) => ($now - $timestamp) <= $this->lifetime
        );
    }
    
    /**
     * Supprime tous les tokens
     */
    public function flush() {
        $_SESSION[self::SESSION_KEY] = [];
    }
    
    /**
     * Génère un champ HTML caché
     */
    public function field($name = 'csrf_token') {
        $token = $this->generate();
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
    
    /**
     * Génère une meta tag
     */
    public function meta() {
        $token = $this->generate();
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
}
