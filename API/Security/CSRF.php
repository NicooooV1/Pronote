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
        
        // La session doit être démarrée par le bootstrap avant instanciation du CSRF
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
     * Valide le token CSRF depuis la requête courante.
     * Cherche dans POST (csrf_token, _csrf_token), header X-CSRF-Token, et body JSON.
     *
     * @return bool true si un token valide est trouvé
     */
    public function validateFromRequest(): bool {
        $token = $_POST['csrf_token'] ?? $_POST['_csrf_token'] ?? null;

        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if ($token === null) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
                $token = $input['csrf_token'] ?? $input['_csrf_token'] ?? null;
            }
        }

        if ($token === null) {
            return false;
        }

        return $this->validate($token);
    }

    /**
     * Valide le token CSRF et arrête l'exécution si invalide.
     * À appeler en début de traitement POST/PUT/DELETE.
     */
    public function verifyOrFail(): void {
        if (!$this->validateFromRequest()) {
            http_response_code(403);
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Token CSRF invalide. Veuillez rafraîchir la page.']);
            } else {
                echo 'Erreur de sécurité : token CSRF invalide. <a href="javascript:location.reload()">Rafraîchir</a>';
            }
            exit;
        }
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
