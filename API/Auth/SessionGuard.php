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

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['type'];
        $_SESSION['user'] = $user;
        
        $this->user = $user;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_type']);
        unset($_SESSION['user']);
        
        $this->user = null;
    }
}
