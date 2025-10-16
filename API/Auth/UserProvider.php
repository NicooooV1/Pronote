<?php
namespace API\Auth;

use PDO;

/**
 * Fournisseur d'utilisateurs
 */
class UserProvider
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère un utilisateur par son ID
     */
    public function retrieveById($userId, $userType)
    {
        $table = $this->getTableForUserType($userType);
        
        if (!$table) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, mail AS email, '{$userType}' as type 
            FROM {$table} WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un utilisateur par ses identifiants
     */
    public function retrieveByCredentials($credentials)
    {
        // Accept either 'login' or 'email' as input field
        $login = $credentials['login'] ?? $credentials['email'] ?? null;
        $userType = $credentials['type'] ?? null;

        if (!$login || !$userType) {
            return null;
        }

        $table = $this->getTableForUserType($userType);
        if (!$table) {
            return null;
        }

        // Lookup by email OR identifiant
        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, mail AS email, mot_de_passe, '{$userType}' as type 
            FROM {$table} 
            WHERE mail = ? OR identifiant = ?
            LIMIT 1
        ");
        $stmt->execute([$login, $login]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Valide les identifiants d'un utilisateur
     */
    public function validateCredentials($user, $credentials)
    {
        $password = $credentials['password'] ?? null;
        
        if (!$password || !isset($user['mot_de_passe'])) {
            return false;
        }

        return password_verify($password, $user['mot_de_passe']);
    }

    /**
     * Retourne la table correspondant au type d'utilisateur
     */
    protected function getTableForUserType($userType)
    {
        $tables = [
            'eleve' => 'eleves',
            'parent' => 'parents',
            'professeur' => 'professeurs',
            'vie_scolaire' => 'vie_scolaire',
            'administrateur' => 'administrateurs'
        ];

        return $tables[$userType] ?? null;
    }
}
