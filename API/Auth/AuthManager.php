<?php
namespace API\Auth;

/**
 * Gestionnaire d'authentification
 */
class AuthManager
{
    protected $guard;
    protected $userProvider;

    public function __construct(SessionGuard $guard, UserProvider $userProvider)
    {
        $this->guard = $guard;
        $this->userProvider = $userProvider;
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public function check()
    {
        return $this->guard->check();
    }

    /**
     * Retourne l'utilisateur actuel
     */
    public function user()
    {
        return $this->guard->user();
    }

    /**
     * Authentifie un utilisateur
     */
    public function login($userId, $userType)
    {
        $user = $this->userProvider->retrieveById($userId, $userType);
        
        if ($user) {
            $this->guard->login($user);
            return true;
        }
        
        return false;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout()
    {
        $this->guard->logout();
    }

    /**
     * Tente une authentification avec identifiants
     */
    public function attempt($credentials)
    {
        $user = $this->userProvider->retrieveByCredentials($credentials);
        
        if ($user && $this->userProvider->validateCredentials($user, $credentials)) {
            $this->guard->login($user);
            return true;
        }
        
        return false;
    }
}
