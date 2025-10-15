<?php
/**
 * Modèle pour la gestion des utilisateurs
 */

require_once __DIR__ . '/../core/Model.php';

class UserModel extends Model {
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $fillable = ['nom', 'prenom', 'email', 'password', 'profil', 'actif'];
    
    protected $validationRules = [
        'nom' => [
            'required' => true,
            'min' => 2,
            'max' => 50
        ],
        'prenom' => [
            'required' => true,
            'min' => 2,
            'max' => 50
        ],
        'email' => [
            'required' => true,
            'email' => true,
            'max' => 100
        ],
        'password' => [
            'required' => ['message' => 'Le mot de passe est obligatoire'],
            'min' => [
                'value' => 8,
                'message' => 'Le mot de passe doit contenir au moins 8 caractères'
            ]
        ],
        'profil' => [
            'required' => true,
            'in' => ['eleve', 'professeur', 'administrateur', 'parent', 'vie_scolaire']
        ]
    ];
    
    /**
     * Authentifie un utilisateur
     * 
     * @param string $email    Email de l'utilisateur
     * @param string $password Mot de passe
     * @return array|bool Données de l'utilisateur ou false
     */
    public function authenticate($email, $password) {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? AND actif = 1";
        $user = $this->db->fetchOne($sql, [$email]);
        
        if (!$user || !password_verify($password, $user['mot_de_passe'])) {
            return false;
        }
        
        // Mettre à jour dernière connexion
        $this->db->execute(
            "UPDATE {$this->table} SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        unset($user['mot_de_passe']);
        return $user;
    }
    
    /**
     * Met à jour la date de dernière connexion
     * 
     * @param int $id ID de l'utilisateur
     * @return bool Succès de l'opération
     */
    public function updateLastLogin($id)
    {
        $sql = "UPDATE {$this->table} SET derniere_connexion = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Trouve un utilisateur par son email
     * 
     * @param string $email Email de l'utilisateur
     * @return array|bool Données de l'utilisateur ou false
     */
    public function findByEmail($email)
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    /**
     * Vérifie si un email existe déjà
     * 
     * @param string $email     Email à vérifier
     * @param int    $excludeId ID de l'utilisateur à exclure
     * @return bool True si l'email existe
     */
    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND {$this->primaryKey} != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn() > 0;
    }
    
    /**
     * Récupère les utilisateurs par type de profil
     * 
     * @param string $profil Type de profil
     * @return array Liste d'utilisateurs
     */
    public function getByProfile($profil)
    {
        return $this->where(['profil' => $profil], 'AND', 'nom');
    }
    
    /**
     * Crée un nouvel utilisateur
     * 
     * @param array $data Données de l'utilisateur
     * @return int|bool ID du nouvel utilisateur ou false
     */
    public function createUser(array $data)
    {
        // Valider les données
        $validation = $this->validate($data);
        if ($validation !== true) {
            Logger::warning('Validation failed when creating user', $validation);
            return false;
        }
        
        // Vérifier si l'email existe déjà
        if ($this->emailExists($data['email'])) {
            Logger::warning('Email already exists', ['email' => $data['email']]);
            return false;
        }
        
        // Hasher le mot de passe
        $data['password'] = Security::hashPassword($data['password']);
        
        // Ajouter la date de création
        $data['date_creation'] = date('Y-m-d H:i:s');
        
        return $this->create($data);
    }
    
    /**
     * Modifie un utilisateur
     * 
     * @param int   $id   ID de l'utilisateur
     * @param array $data Données à mettre à jour
     * @return bool Succès de l'opération
     */
    public function updateUser($id, array $data)
    {
        // Vérifier si l'utilisateur existe
        $user = $this->find($id);
        if (!$user) {
            return false;
        }
        
        // Si on change l'email, vérifier qu'il n'est pas déjà utilisé
        if (isset($data['email']) && $data['email'] !== $user['email']) {
            if ($this->emailExists($data['email'], $id)) {
                return false;
            }
        }
        
        // Si on change le mot de passe, le hasher
        if (!empty($data['password'])) {
            $data['password'] = Security::hashPassword($data['password']);
        } else {
            // Ne pas modifier le mot de passe si non fourni
            unset($data['password']);
        }
        
        return $this->update($id, $data);
    }
}
