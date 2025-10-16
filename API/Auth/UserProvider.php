<?php
/**
 * User Provider - Data layer pour l'authentification
 */

namespace Pronote\Auth;

class UserProvider {
    protected $db;
    
    /**
     * Tables utilisateurs
     */
    protected $tables = [
        'eleve' => 'eleves',
        'parent' => 'parents',
        'professeur' => 'professeurs',
        'vie_scolaire' => 'vie_scolaire',
        'administrateur' => 'administrateurs',
        'personnel' => ['vie_scolaire', 'administrateur'] // Multi-table
    ];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Récupère un utilisateur par ses credentials
     */
    public function retrieveByCredentials(array $credentials) {
        if (!isset($credentials['identifiant']) || !isset($credentials['profil'])) {
            return null;
        }
        
        $profil = $credentials['profil'];
        $identifiant = $credentials['identifiant'];
        
        // Gestion du type "personnel" (multi-table)
        if ($profil === 'personnel') {
            $user = $this->retrieveFromTable('vie_scolaire', $identifiant);
            if (!$user) {
                $user = $this->retrieveFromTable('administrateur', $identifiant);
            }
            return $user;
        }
        
        if (!isset($this->tables[$profil])) {
            return null;
        }
        
        return $this->retrieveFromTable($profil, $identifiant);
    }
    
    /**
     * Récupère depuis une table spécifique
     */
    protected function retrieveFromTable($profil, $identifiant) {
        $table = is_array($this->tables[$profil]) 
            ? $this->tables[$profil][0] 
            : $this->tables[$profil];
        
        $sql = "SELECT * FROM `{$table}` WHERE identifiant = ? AND actif = 1 LIMIT 1";
        
        try {
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$identifiant]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($user) {
                $user['profil'] = $profil;
                $user['table'] = $table;
            }
            
            return $user ?: null;
        } catch (\PDOException $e) {
            error_log("UserProvider error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Valide les credentials
     */
    public function validateCredentials(array $user, array $credentials) {
        if (!isset($credentials['password']) || !isset($user['mot_de_passe'])) {
            return false;
        }
        
        return password_verify($credentials['password'], $user['mot_de_passe']);
    }
    
    /**
     * Récupère un utilisateur par ID
     */
    public function retrieveById($id, $profil) {
        if (!isset($this->tables[$profil])) {
            return null;
        }
        
        $table = is_array($this->tables[$profil]) 
            ? $this->tables[$profil][0] 
            : $this->tables[$profil];
        
        $sql = "SELECT * FROM `{$table}` WHERE id = ? LIMIT 1";
        
        try {
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($user) {
                $user['profil'] = $profil;
                $user['table'] = $table;
            }
            
            return $user ?: null;
        } catch (\PDOException $e) {
            error_log("UserProvider error: " . $e->getMessage());
            return null;
        }
    }
}
