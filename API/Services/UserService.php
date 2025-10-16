<?php
namespace API\Services;

use PDO;

/**
 * Service de gestion des utilisateurs
 */
class UserService
{
    protected $pdo;
    protected $tableMap = [
        'eleve' => 'eleves',
        'parent' => 'parents',
        'professeur' => 'professeurs',
        'vie_scolaire' => 'vie_scolaire',
        'administrateur' => 'administrateurs'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crée un nouvel utilisateur
     */
    public function create($profil, $userData)
    {
        if (!isset($this->tableMap[$profil])) {
            return [
                'success' => false,
                'message' => 'Type de profil invalide'
            ];
        }

        $table = $this->tableMap[$profil];

        // Générer l'identifiant
        $identifiant = $this->generateIdentifier(
            $userData['nom'],
            $userData['prenom'],
            $table
        );

        // Générer un mot de passe aléatoire
        $password = $this->generatePassword();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Préparer les données
        $data = [
            'identifiant' => $identifiant,
            'nom' => $userData['nom'],
            'prenom' => $userData['prenom'],
            'mail' => $userData['mail'],
            'mot_de_passe' => $hashedPassword
        ];

        // Ajouter les champs spécifiques selon le profil
        if ($profil === 'eleve') {
            $data['date_naissance'] = $userData['date_naissance'] ?? null;
            $data['lieu_naissance'] = $userData['lieu_naissance'] ?? null;
            $data['classe'] = $userData['classe'] ?? null;
            $data['adresse'] = $userData['adresse'] ?? null;
        } elseif ($profil === 'professeur') {
            $data['matiere'] = $userData['matiere'] ?? null;
            $data['professeur_principal'] = $userData['est_pp'] ?? 0;
        } elseif ($profil === 'vie_scolaire') {
            $data['est_CPE'] = $userData['est_CPE'] ?? 0;
            $data['est_infirmerie'] = $userData['est_infirmerie'] ?? 0;
        }

        // Insérer dans la base de données
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));

            return [
                'success' => true,
                'identifiant' => $identifiant,
                'password' => $password,
                'message' => 'Utilisateur créé avec succès'
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change le mot de passe d'un utilisateur
     */
    public function changePassword($userId, $newPassword)
    {
        // Trouver la table de l'utilisateur
        foreach ($this->tableMap as $profil => $table) {
            $stmt = $this->pdo->prepare("SELECT id FROM $table WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            
            if ($stmt->fetch()) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $this->pdo->prepare("UPDATE $table SET mot_de_passe = ? WHERE id = ?");
                $result = $stmt->execute([$hashedPassword, $userId]);
                
                return [
                    'success' => $result,
                    'message' => $result ? 'Mot de passe changé avec succès' : 'Erreur lors du changement'
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Utilisateur non trouvé'
        ];
    }

    /**
     * Trouve un utilisateur par ses identifiants
     */
    public function findByCredentials($username, $email, $phone, $userType)
    {
        if (!isset($this->tableMap[$userType])) {
            return null;
        }

        $table = $this->tableMap[$userType];
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM $table 
            WHERE identifiant = ? AND mail = ? AND telephone = ? 
            LIMIT 1
        ");
        
        $stmt->execute([$username, $email, $phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée une demande de réinitialisation
     */
    public function createResetRequest($userId, $userType)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO demandes_reinitialisation (user_id, user_type, date_demande, status) 
            VALUES (?, ?, NOW(), 'pending')
        ");
        
        return $stmt->execute([$userId, $userType]);
    }

    /**
     * Génère un identifiant unique
     */
    protected function generateIdentifier($nom, $prenom, $table)
    {
        $nom = $this->normalizeString($nom);
        $prenom = $this->normalizeString($prenom);
        
        $baseIdentifier = strtolower($nom . '.' . $prenom);
        
        $stmt = $this->pdo->prepare("SELECT identifiant FROM $table WHERE identifiant LIKE ?");
        $stmt->execute([$baseIdentifier . '%']);
        $existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array($baseIdentifier, $existingIds)) {
            return $baseIdentifier;
        }
        
        $i = 1;
        do {
            $identifier = $baseIdentifier . sprintf("%02d", $i++);
        } while (in_array($identifier, $existingIds));
        
        return $identifier;
    }

    /**
     * Génère un mot de passe aléatoire
     */
    protected function generatePassword($length = 12)
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghijkmnopqrstuvwxyz';
        $numbers = '23456789';
        $special = '!@#$%^&*_-+=';
        
        $password = '';
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $special[rand(0, strlen($special) - 1)];
        
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 0; $i < $length - 4; $i++) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }
        
        return str_shuffle($password);
    }

    /**
     * Normalise une chaîne
     */
    protected function normalizeString($string)
    {
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        return preg_replace('/[^a-zA-Z0-9]/', '', $string);
    }
}
