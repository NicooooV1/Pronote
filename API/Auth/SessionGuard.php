<?php
/**
 * Session Guard - Authentification par session
 */

namespace API\Auth;

class SessionGuard {
    protected $user;
    protected $userProvider;

    public function __construct(UserProvider $userProvider) {
        $this->userProvider = $userProvider;
    }

    /**
     * Vérifie si un utilisateur est authentifié
     */
    public function check() {
        return !is_null($this->user());
    }

    /**
     * Retourne l'utilisateur actuel
     */
    public function user() {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $userType = $_SESSION['user_type'] ?? null;

        if ($userId && $userType) {
            $this->user = $this->userProvider->retrieveById($userId, $userType);
        }

        return $this->user;
    }

    /**
     * Connecte un utilisateur
     */
    public function login($user) {
        // Régénérer l'ID de session pour prévenir la fixation de session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Ne jamais stocker le hash du mot de passe en session
        $safeUser = $user;
        unset($safeUser['mot_de_passe']);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['type'];
        $_SESSION['user'] = $safeUser;

        $this->user = $safeUser;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        $this->user = null;

        // Vider toutes les données de session
        $_SESSION = [];

        // Supprimer le cookie de session
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

        // Détruire la session côté serveur
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
