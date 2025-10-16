<?php
/**
 * Session Guard - Authentification par session
 */

namespace Pronote\Auth;

class SessionGuard {
    protected $provider;
    protected $app;
    protected $user;
    protected $sessionKey = 'user';
    
    public function __construct(UserProvider $provider, $app) {
        $this->provider = $provider;
        $this->app = $app;
    }
    
    /**
     * Tente une authentification
     */
    public function attempt(array $credentials, $remember = false) {
        $user = $this->provider->retrieveByCredentials($credentials);
        
        if (!$user) {
            return false;
        }
        
        if (!$this->provider->validateCredentials($user, $credentials)) {
            $this->logFailedAttempt($credentials);
            return false;
        }
        
        $this->login($user, $remember);
        $this->logSuccessfulLogin($user);
        
        return true;
    }
    
    /**
     * Connecte un utilisateur
     */
    public function login(array $user, $remember = false) {
        $this->updateSession($user);
        $this->user = $user;
        
        if ($remember) {
            $this->createRememberToken($user);
        }
        
        // Régénérer l'ID de session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    /**
     * Met à jour la session
     */
    protected function updateSession(array $user) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION[$this->sessionKey] = [
            'id' => (int)$user['id'],
            'identifiant' => $user['identifiant'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'mail' => $user['mail'],
            'profil' => $user['profil'],
            'classe' => $user['classe'] ?? null,
            'actif' => true
        ];
        
        $_SESSION['auth_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function check() {
        if (!is_null($this->user)) {
            return true;
        }
        
        if (!$this->hasValidSession()) {
            return false;
        }
        
        $this->user = $_SESSION[$this->sessionKey];
        
        return true;
    }
    
    /**
     * Vérifie si la session est valide
     */
    protected function hasValidSession() {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        
        if (!isset($_SESSION[$this->sessionKey])) {
            return false;
        }
        
        // Vérifier l'expiration
        $lifetime = $this->app->config('security.session_lifetime', 7200);
        if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time']) > $lifetime) {
            $this->logout();
            return false;
        }
        
        // Vérifier l'IP (optionnel)
        $checkIp = $this->app->config('security.check_ip', true);
        if ($checkIp && isset($_SESSION['user_ip'])) {
            if ($_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
                $this->logout();
                return false;
            }
        }
        
        // Mettre à jour l'activité
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Récupère l'utilisateur actuel
     */
    public function user() {
        if ($this->check()) {
            return $this->user;
        }
        
        return null;
    }
    
    /**
     * Récupère l'ID de l'utilisateur
     */
    public function id() {
        $user = $this->user();
        return $user ? $user['id'] : null;
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        $this->user = null;
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            session_destroy();
        }
    }
    
    /**
     * Vérifie le rôle
     */
    public function hasRole($role) {
        $user = $this->user();
        return $user && $user['profil'] === $role;
    }
    
    /**
     * Exige une authentification
     */
    public function requireAuth() {
        if (!$this->check()) {
            $loginUrl = $this->app->config('paths.base', '') . '/login/public/index.php';
            header("Location: {$loginUrl}");
            exit;
        }
    }
    
    /**
     * Log d'échec de connexion
     */
    protected function logFailedAttempt(array $credentials) {
        // TODO: Implémenter avec LogService
        error_log("Failed login attempt: " . ($credentials['identifiant'] ?? 'unknown'));
    }
    
    /**
     * Log de connexion réussie
     */
    protected function logSuccessfulLogin(array $user) {
        // TODO: Implémenter avec LogService
        error_log("Successful login: user_id=" . $user['id']);
    }
    
    /**
     * Crée un token "Remember Me"
     */
    protected function createRememberToken(array $user) {
        // TODO: Implémenter si nécessaire
    }
}
