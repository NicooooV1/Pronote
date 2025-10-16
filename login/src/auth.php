<?php
/**
 * Classe d'authentification pour Pronote
 */
require_once __DIR__ . '/../../API/core/Security.php';

class Auth {
    private $errorMessage = '';
    
    /**
     * Authentifie un utilisateur
     * 
     * @param string $profil Type de profil (élève, parent, professeur, administrateur)
     * @param string $identifiant Identifiant de l'utilisateur
     * @param string $password Mot de passe en clair
     * @return array Résultat de l'authentification ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function login($profil, $identifiant, $password) {
        global $pdo;
        
        // Vérifier que le profil est valide
        $validProfiles = ['eleve', 'parent', 'professeur', 'administrateur', 'vie_scolaire'];
        if (!in_array($profil, $validProfiles)) {
            $this->errorMessage = "Type de profil non valide.";
            return [
                'success' => false,
                'message' => $this->errorMessage,
                'user' => null
            ];
        }
        
        try {
            // Établir la connexion à la base de données si ce n'est pas déjà fait
            if (!isset($pdo)) {
                require_once __DIR__ . '/../../API/database.php';
                $pdo = getDBConnection();
            }
            
            // Requête selon le type de profil
            $table = $this->getTableName($profil);
            
            // Utilisation de requêtes préparées pour éviter les injections SQL
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE identifiant = ? LIMIT 1");
            $stmt->execute([$identifiant]);
            $user = $stmt->fetch();
            
            // Vérifier si l'utilisateur existe et si le mot de passe correspond
            if ($user && \Pronote\Security\verify_password($password, $user['mot_de_passe'])) {
                // Préparer les données utilisateur
                $userData = [
                    'id' => $user['id'],
                    'nom' => $user['nom'],
                    'prenom' => $user['prenom'],
                    'profil' => $profil,
                    'mail' => $user['mail'],
                    'identifiant' => $user['identifiant']
                ];
                
                // Ajout d'informations spécifiques selon le profil
                if ($profil === 'eleve') {
                    $userData['classe'] = $user['classe'];
                    $userData['date_naissance'] = $user['date_naissance'];
                } elseif ($profil === 'professeur') {
                    $userData['matiere'] = $user['matiere'];
                    $userData['est_pp'] = $user['professeur_principal'];
                }
                
                // Enregistrement du temps d'authentification
                $_SESSION['auth_time'] = time();
                
                // Journaliser la connexion
                $this->logLogin($user['id'], $profil, true);
                
                return [
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'user' => $userData
                ];
            } else {
                // Journaliser la tentative échouée
                $this->logLogin(0, $profil, false, $identifiant);
                $this->errorMessage = "Identifiant ou mot de passe incorrect.";
                
                return [
                    'success' => false,
                    'message' => $this->errorMessage,
                    'user' => null
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur d'authentification: " . $e->getMessage());
            $this->errorMessage = "Une erreur est survenue lors de l'authentification.";
            
            return [
                'success' => false,
                'message' => $this->errorMessage,
                'user' => null
            ];
        }
    }
    
    /**
     * Obtient le nom de la table correspondant au profil
     * 
     * @param string $profil Type de profil
     * @return string Nom de la table
     */
    private function getTableName($profil) {
        switch ($profil) {
            case 'eleve':
                return 'eleves';
            case 'parent':
                return 'parents';
            case 'professeur':
                return 'professeurs';
            case 'vie_scolaire':
                return 'vie_scolaire';
            case 'administrateur':
                return 'administrateurs';
            default:
                return '';
        }
    }
    
    /**
     * Journalise une tentative de connexion
     * 
     * @param int $userId ID de l'utilisateur (0 si échec)
     * @param string $profil Type de profil
     * @param bool $success True si la connexion est réussie
     * @param string $identifiant Identifiant utilisé (uniquement en cas d'échec)
     * @return void
     */
    private function logLogin($userId, $profil, $success, $identifiant = '') {
        $message = $success 
            ? "Connexion réussie: Utilisateur ID=$userId, Profil=$profil" 
            : "Échec de connexion: Profil=$profil" . ($identifiant ? ", Identifiant=$identifiant" : "");
        
        error_log($message);
        
        // Utiliser le système de journalisation central si disponible
        if (function_exists('\\Pronote\\Logging\\authAction')) {
            \Pronote\Logging\authAction('login', $identifiant, $success);
        }
    }
    
    /**
     * Récupère le message d'erreur
     * 
     * @return string Message d'erreur
     */
    public function getErrorMessage() {
        return $this->errorMessage;
    }
    
    /**
     * Trouve un utilisateur par son nom d'utilisateur
     * 
     * @param string $username Nom d'utilisateur
     * @param string $userType Type d'utilisateur (eleve, parent, professeur, etc.)
     * @return array|false Informations sur l'utilisateur ou false s'il n'existe pas
     */
    public function findUserByUsername($username, $userType) {
        global $pdo;
        
        try {
            // Déterminer la table en fonction du type d'utilisateur
            $table = $this->getTableName($userType);
            
            if (empty($table)) {
                $this->errorMessage = "Type d'utilisateur non valide.";
                return false;
            }
            
            // Rechercher l'utilisateur dans la base de données
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE identifiant = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                return $user;
            } else {
                $this->errorMessage = "Aucun utilisateur trouvé avec cet identifiant.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la recherche de l'utilisateur: " . $e->getMessage());
            $this->errorMessage = "Une erreur est survenue lors de la recherche de l'utilisateur.";
            return false;
        }
    }
    
    /**
     * Trouve un utilisateur par son identifiant, email et téléphone
     * 
     * @param string $username Nom d'utilisateur
     * @param string $email Adresse email
     * @param string $phone Numéro de téléphone
     * @param string $userType Type d'utilisateur (eleve, parent, professeur, etc.)
     * @return array|false Informations sur l'utilisateur ou false s'il n'existe pas
     */
    public function findUserByCredentials($username, $email, $phone, $userType) {
        global $pdo;
        
        try {
            // Déterminer la table en fonction du type d'utilisateur
            $table = $this->getTableName($userType);
            
            if (empty($table)) {
                $this->errorMessage = "Type d'utilisateur non valide.";
                return false;
            }
            
            // Rechercher l'utilisateur dans la base de données en vérifiant tous les critères
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE identifiant = ? AND mail = ? AND telephone = ? LIMIT 1");
            $stmt->execute([$username, $email, $phone]);
            $user = $stmt->fetch();
            
            if ($user) {
                return $user;
            } else {
                $this->errorMessage = "Aucun utilisateur ne correspond à ces informations.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la recherche de l'utilisateur: " . $e->getMessage());
            $this->errorMessage = "Une erreur est survenue lors de la recherche de l'utilisateur.";
            return false;
        }
    }
    
    /**
     * Génère un code de réinitialisation pour un utilisateur
     * 
     * @param int $userId ID de l'utilisateur
     * @return string Code de réinitialisation
     */
    public function generateResetCode($userId) {
        // Générer un code de 6 chiffres
        $code = sprintf("%06d", mt_rand(1, 999999));
        
        // Stocker le code dans la base de données ou dans une table temporaire
        // Pour la démonstration, on retourne simplement le code généré
        return $code;
    }
    
    /**
     * Change le mot de passe d'un utilisateur
     * 
     * @param int $userId ID de l'utilisateur
     * @param string $newPassword Nouveau mot de passe
     * @return array Résultat de l'opération ['success' => bool, 'message' => string]
     */
    public function changePassword($userId, $newPassword) {
        global $pdo;
        
        // Utilisation de la classe User pour changer le mot de passe
        require_once __DIR__ . '/user.php';
        $user = new User($pdo);
        
        // Trouver le profil de l'utilisateur
        try {
            // Vérifier dans quelle table se trouve l'utilisateur
            $tables = ['eleves', 'parents', 'professeurs', 'vie_scolaire', 'administrateurs'];
            $userProfile = null;
            $foundUser = false;
            
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("SELECT id FROM $table WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                if ($stmt->fetch()) {
                    $foundUser = true;
                    $userProfile = $this->getProfileFromTable($table);
                    break;
                }
            }
            
            if (!$foundUser) {
                return [
                    'success' => false,
                    'message' => "Utilisateur non trouvé."
                ];
            }
            
            // Changer le mot de passe
            if ($user->changePassword($userProfile, $userId, $newPassword)) {
                return [
                    'success' => true,
                    'message' => "Mot de passe changé avec succès."
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $user->getErrorMessage()
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors du changement de mot de passe: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Une erreur est survenue lors du changement de mot de passe."
            ];
        }
    }
    
    /**
     * Crée une demande de réinitialisation de mot de passe
     * 
     * @param int $userId ID de l'utilisateur
     * @param string $userType Type d'utilisateur
     * @return bool True si la demande a été créée avec succès
     */
    public function createResetRequest($userId, $userType) {
        global $pdo;
        
        try {
            // Vérifier si une demande existe déjà
            $stmt = $pdo->prepare("SELECT id FROM demandes_reinitialisation WHERE user_id = ? AND user_type = ? AND status = 'pending' LIMIT 1");
            $stmt->execute([$userId, $userType]);
            
            if ($stmt->fetch()) {
                // Une demande en attente existe déjà
                $this->errorMessage = "Une demande de réinitialisation est déjà en attente pour cet utilisateur.";
                return false;
            }
            
            // Créer une nouvelle demande
            $stmt = $pdo->prepare("INSERT INTO demandes_reinitialisation (user_id, user_type, date_demande, status) VALUES (?, ?, NOW(), 'pending')");
            $result = $stmt->execute([$userId, $userType]);
            
            if ($result) {
                return true;
            } else {
                $this->errorMessage = "Impossible de créer la demande de réinitialisation.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la création de la demande de réinitialisation: " . $e->getMessage());
            $this->errorMessage = "Une erreur est survenue lors de la création de la demande.";
            return false;
        }
    }
    
    /**
     * Obtient le profil à partir du nom de la table
     * 
     * @param string $table Nom de la table
     * @return string Nom du profil
     */
    private function getProfileFromTable($table) {
        switch ($table) {
            case 'eleves':
                return 'eleve';
            case 'parents':
                return 'parent';
            case 'professeurs':
                return 'professeur';
            case 'vie_scolaire':
                return 'vie_scolaire';
            case 'administrateurs':
                return 'administrateur';
            default:
                return '';
        }
    }
}

// Ce fichier n'est plus utilisé pour l'authentification. Utilisez l'API centralisée.
exit;