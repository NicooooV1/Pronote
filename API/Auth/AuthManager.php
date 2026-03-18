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

    /**
     * Valide des identifiants SANS créer de session (utilisé avant vérification 2FA).
     * Si $credentials contient 'type', cherche dans la table correspondante uniquement.
     * Sinon, essaie toutes les tables dans l'ordre.
     * Retourne l'utilisateur si valide, null sinon.
     * Si plusieurs comptes correspondent au login (identifiant partagé entre tables), retourne un tableau.
     */
    public function attemptAndGetUser(array $credentials): array|null
    {
        $login    = $credentials['login'] ?? $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;
        $type     = $credentials['type'] ?? null;

        if (!$login || !$password) {
            return null;
        }

        if ($type) {
            $user = $this->userProvider->retrieveByCredentials($credentials);
            if ($user && $this->userProvider->validateCredentials($user, $credentials)) {
                return $user;
            }
            return null;
        }

        // Multi-type : chercher dans toutes les tables
        $candidates = $this->userProvider->findByLoginAllTypes($login);
        $valid = [];
        foreach ($candidates as $user) {
            if ($this->userProvider->validateCredentials($user, $credentials)) {
                $valid[] = $user;
            }
        }

        if (count($valid) === 1) {
            return $valid[0];
        }
        if (count($valid) > 1) {
            // Plusieurs comptes → retourner le tableau pour que le login gère le choix
            return $valid;
        }

        return null;
    }

    /**
     * Crée la session pour un utilisateur déjà validé (appelé après 2FA ou après credential check).
     */
    public function loginUser(array $user): void
    {
        $this->guard->login($user);
    }
}
