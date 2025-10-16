<?php
// src/User.php
require_once __DIR__ . '/password_generator.php';

class User {
    private $pdo;
    private $tableMap = [
        'eleve'        => 'eleves',
        'parent'       => 'parents',
        'professeur'   => 'professeurs',
        'vie_scolaire' => 'vie_scolaire',
        'administrateur' => 'administrateurs',
    ];
    private $generatedPassword;
    private $generatedIdentifier;
    private $errorMessage = '';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Crée un nouvel utilisateur
     * 
     * @param string $profil Type de profil (eleve, parent, professeur, etc.)
     * @param array $data Données de l'utilisateur
     * @return bool True si la création réussit, false sinon
     */
    public function create($profil, array $data) {
        if (!isset($this->tableMap[$profil])) {
            $this->errorMessage = "Type de profil '$profil' invalide.";
            return false;
        }
        
        // Vérifier si la création de compte administrateur est autorisée
        if ($profil === 'administrateur') {
            $adminLockFile = __DIR__ . '/../../admin.lock';
            if (file_exists($adminLockFile)) {
                $this->errorMessage = "La création de nouveaux comptes administrateurs est désactivée. Contactez l'administrateur principal.";
                return false;
            }
        }
        
        $table = $this->tableMap[$profil];

        // Validation des données
        if (!$this->validateData($data, $profil)) {
            // Le message d'erreur est déjà défini dans validateData
            return false;
        }

        // Génération de l'identifiant au format nom.prenom
        if (empty($data['identifiant'])) {
            $data['identifiant'] = $this->generateIdentifier($data['nom'], $data['prenom'], $table);
        }
        
        // Stocker l'identifiant généré pour pouvoir le récupérer plus tard
        $this->generatedIdentifier = $data['identifiant'];

        // Vérifier unicité identifiant et email
        $stmt = $this->pdo->prepare(
            "SELECT id, identifiant, mail FROM `$table` WHERE identifiant = :id OR mail = :mail LIMIT 1"
        );
        $stmt->execute([
            'id'   => $data['identifiant'],
            'mail' => $data['mail'],
        ]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            if ($existingUser['identifiant'] === $data['identifiant']) {
                $this->errorMessage = "L'identifiant '{$data['identifiant']}' est déjà utilisé.";
            } elseif ($existingUser['mail'] === $data['mail']) {
                $this->errorMessage = "L'adresse email '{$data['mail']}' est déjà utilisée.";
            } else {
                $this->errorMessage = "Un utilisateur avec des informations similaires existe déjà.";
            }
            return false;
        }

        // Génération d'un mot de passe aléatoire
        $this->generatedPassword = PasswordGenerator::generate(12);
        $data['mot_de_passe'] = password_hash($this->generatedPassword, PASSWORD_DEFAULT);

        $cols = array_keys($data);
        $sqlCols = implode(', ', $cols);
        $sqlVals = ':' . implode(', :', $cols);
        $sql = "INSERT INTO `$table` ($sqlCols) VALUES ($sqlVals)";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($data);
        } catch (PDOException $e) {
            $this->errorMessage = "Erreur lors de l'enregistrement dans la base de données: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Renvoie le dernier message d'erreur
     * 
     * @return string Message d'erreur
     */
    public function getErrorMessage() {
        return $this->errorMessage;
    }
    
    /**
     * Renvoie le dernier mot de passe généré
     * 
     * @return string Mot de passe généré
     */
    public function getGeneratedPassword() {
        return $this->generatedPassword;
    }
    
    /**
     * Renvoie l'identifiant généré lors de la création
     * 
     * @return string Identifiant généré
     */
    public function getGeneratedIdentifier() {
        return $this->generatedIdentifier;
    }
    
    /**
     * Retourne le nom de la table correspondant au profil
     * 
     * @param string $profil Type de profil
     * @return string|null Nom de la table ou null si le profil est invalide
     */
    public function getTableName($profil) {
        return isset($this->tableMap[$profil]) ? $this->tableMap[$profil] : null;
    }

    /**
     * Valide les données utilisateur
     * 
     * @param array $data Données à valider
     * @param string $profil Type de profil
     * @return bool True si les données sont valides, false sinon
     */
    private function validateData(array &$data, string $profil) {
        // Validation de l'email
        if (!filter_var($data['mail'], FILTER_VALIDATE_EMAIL)) {
            $this->errorMessage = "L'adresse email '{$data['mail']}' n'est pas valide.";
            return false;
        }

        // Validation du téléphone (format XX XX XX XX XX)
        if (!empty($data['telephone'])) {
            // Supprime tout ce qui n'est pas un chiffre
            $tel = preg_replace('/[^0-9]/', '', $data['telephone']);
            
            // Vérifie que le numéro fait bien 10 chiffres
            if (strlen($tel) !== 10) {
                $this->errorMessage = "Le numéro de téléphone doit contenir 10 chiffres.";
                return false;
            }
            
            // Reformat au format XX XX XX XX XX
            $data['telephone'] = implode(' ', str_split($tel, 2));
        }
        
        // Validation de la date de naissance (pas dans le futur)
        if (isset($data['date_naissance'])) {
            $dateNaissance = new DateTime($data['date_naissance']);
            $now = new DateTime();
            
            if ($dateNaissance > $now) {
                $this->errorMessage = "La date de naissance ne peut pas être dans le futur.";
                return false;
            }
        }
        
        // Validation du format de classe
        if (isset($data['classe'])) {
            // Chargement des classes depuis le fichier JSON
            $classesValid = $this->validateClasseFromJson($data['classe']);
            if (!$classesValid) {
                $this->errorMessage = "La classe '{$data['classe']}' n'est pas valide.";
                return false;
            }
        }
        
        // Validation de la matière enseignée
        if (isset($data['matiere'])) {
            // Chargement des matières depuis le fichier JSON
            $matiereValid = $this->validateMatiereFromJson($data['matiere']);
            if (!$matiereValid) {
                $this->errorMessage = "La matière '{$data['matiere']}' n'est pas valide.";
                return false;
            }
        }

        // Valeurs par défaut selon le profil
        switch ($profil) {
            case 'professeur':
                if (empty($data['professeur_principal'])) {
                    $data['professeur_principal'] = 'non';
                }
                break;
            case 'parent':
                if (!isset($data['est_parent_eleve'])) {
                    $data['est_parent_eleve'] = 'non';
                }
                break;
            case 'vie_scolaire':
                if (!isset($data['est_CPE'])) {
                    $data['est_CPE'] = 'non';
                }
                if (!isset($data['est_infirmerie'])) {
                    $data['est_infirmerie'] = 'non';
                }
                break;
            case 'administrateur':
                // Valeurs par défaut pour administrateur
                if (!isset($data['role'])) {
                    $data['role'] = 'standard';
                }
                break;
        }

        return true;
    }
    
    /**
     * Valide une classe à partir du fichier JSON des classes
     * 
     * @param string $classe Classe à valider
     * @return bool True si la classe est valide
     */
    private function validateClasseFromJson($classe) {
        $jsonFile = __DIR__ . '/../data/etablissement.json';
        
        if (!file_exists($jsonFile)) {
            // Si le fichier n'existe pas, on utilise une validation basique
            $pattern = '/^[654321T][A-Za-z0-9]+$/';
            return preg_match($pattern, $classe) === 1;
        }
        
        $jsonData = json_decode(file_get_contents($jsonFile), true);
        
        if (!isset($jsonData['classes'])) {
            return false;
        }
        
        // Parcourir toutes les classes pour vérifier si la classe existe
        foreach ($jsonData['classes'] as $niveau) {
            foreach ($niveau as $classes) {
                if (in_array($classe, $classes)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Valide une matière à partir du fichier JSON des matières
     * 
     * @param string $matiere Matière à valider
     * @return bool True si la matière est valide
     */
    private function validateMatiereFromJson($matiere) {
        $jsonFile = __DIR__ . '/../data/etablissement.json';
        
        if (!file_exists($jsonFile)) {
            // Si le fichier n'existe pas, on accepte toutes les matières
            return true;
        }
        
        $jsonData = json_decode(file_get_contents($jsonFile), true);
        
        if (!isset($jsonData['matieres'])) {
            return true;
        }
        
        // Parcourir toutes les matières pour vérifier si la matière existe
        foreach ($jsonData['matieres'] as $matiereItem) {
            if ($matiereItem['nom'] === $matiere || $matiereItem['code'] === $matiere) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Génère un identifiant unique au format nom.prenom[XX]
     * 
     * @param string $nom Nom de l'utilisateur
     * @param string $prenom Prénom de l'utilisateur
     * @param string $table Table de l'utilisateur
     * @return string Identifiant généré
     */
    public function generateIdentifier($nom, $prenom, $table) {
        // Nettoyage des caractères spéciaux et accents
        $nom = $this->normalizeString($nom);
        $prenom = $this->normalizeString($prenom);
        
        // Format de base: nom.prenom
        $baseIdentifier = strtolower($nom . '.' . $prenom);
        
        // Vérifier si l'identifiant existe déjà
        $stmt = $this->pdo->prepare("SELECT identifiant FROM `$table` WHERE identifiant LIKE :pattern");
        $stmt->execute(['pattern' => $baseIdentifier . '%']);
        $existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($existingIds)) {
            return $baseIdentifier;
        }
        
        // Si l'identifiant existe déjà, on ajoute un numéro
        if (!in_array($baseIdentifier, $existingIds)) {
            return $baseIdentifier;
        }
        
        // Trouver le prochain numéro disponible
        $i = 1;
        $identifier = $baseIdentifier . sprintf("%02d", $i);
        
        while (in_array($identifier, $existingIds)) {
            $i++;
            $identifier = $baseIdentifier . sprintf("%02d", $i);
        }
        
        return $identifier;
    }
    
    /**
     * Normalise une chaîne (supprime les accents et caractères spéciaux)
     * 
     * @param string $string Chaîne à normaliser
     * @return string Chaîne normalisée
     */
    private function normalizeString($string) {
        // Convertir les caractères accentués en caractères non accentués
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        
        // Supprime tout ce qui n'est pas une lettre ou un chiffre
        $string = preg_replace('/[^a-zA-Z0-9]/', '', $string);
        
        return $string;
    }
    
    /**
     * Récupère un utilisateur par son ID
     * 
     * @param int $id ID de l'utilisateur
     * @param string $profil Type de profil
     * @return array|false Données de l'utilisateur ou false
     */
    public function getById($id, $profil) {
        if (!isset($this->tableMap[$profil])) {
            $this->errorMessage = "Type de profil '$profil' invalide.";
            return false;
        }
        
        $table = $this->tableMap[$profil];
        $stmt = $this->pdo->prepare("SELECT * FROM `$table` WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Charge les données des classes et matières depuis le fichier JSON
     * 
     * @return array Données des classes et matières
     */
    public function getEtablissementData() {
        $jsonFile = __DIR__ . '/../data/etablissement.json';
        
        if (!file_exists($jsonFile)) {
            return [
                'classes' => [],
                'matieres' => []
            ];
        }
        
        $jsonData = json_decode(file_get_contents($jsonFile), true);
        return $jsonData;
    }
    
    /**
     * Met à jour un utilisateur existant
     * @param string $profil Type de profil (eleve, parent, professeur, etc.)
     * @param int $id ID de l'utilisateur
     * @param array $data Données à mettre à jour
     * @return bool True si la mise à jour a réussi
     */
    public function update($profil, $id, array $data) {
        if (!isset($this->tableMap[$profil])) {
            $this->errorMessage = "Type de profil '$profil' invalide.";
            return false;
        }
        
        // Gestion spéciale pour les comptes administrateur
        if ($profil === 'administrateur') {
            // Toujours permettre la mise à jour des administrateurs existants
            require_once __DIR__ . '/../../API/config/admin_config.php';
            if (!isAdminManagementAllowed()) {
                $this->errorMessage = "La modification des comptes administrateurs n'est pas autorisée.";
                return false;
            }
            
            // Vérification de mot de passe fort si changement de mot de passe
            if (!empty($data['mot_de_passe'])) {
                $validation = validateStrongPassword($data['mot_de_passe']);
                if (!$validation['valid']) {
                    $this->errorMessage = implode('. ', $validation['errors']);
                    return false;
                }
            }
        }
        
        $table = $this->tableMap[$profil];
        
        // Validation des données
        if (!$this->validateData($data, $profil)) {
            // Le message d'erreur est déjà défini dans validateData
            return false;
        }

        // Génération de l'identifiant au format nom.prenom
        if (empty($data['identifiant'])) {
            $data['identifiant'] = $this->generateIdentifier($data['nom'], $data['prenom'], $table);
        }
        
        // Stocker l'identifiant généré pour pouvoir le récupérer plus tard
        $this->generatedIdentifier = $data['identifiant'];

        // Vérifier unicité identifiant et email
        $stmt = $this->pdo->prepare(
            "SELECT id, identifiant, mail FROM `$table` WHERE (identifiant = :id OR mail = :mail) AND id != :userId LIMIT 1"
        );
        $stmt->execute([
            'id'      => $data['identifiant'],
            'mail'    => $data['mail'],
            'userId'  => $id,
        ]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            if ($existingUser['identifiant'] === $data['identifiant']) {
                $this->errorMessage = "L'identifiant '{$data['identifiant']}' est déjà utilisé.";
            } elseif ($existingUser['mail'] === $data['mail']) {
                $this->errorMessage = "L'adresse email '{$data['mail']}' est déjà utilisée.";
            } else {
                $this->errorMessage = "Un utilisateur avec des informations similaires existe déjà.";
            }
            return false;
        }

        $cols = array_keys($data);
        $sqlCols = implode(', ', $cols);
        $sqlVals = ':' . implode(', :', $cols);
        $sql = "UPDATE `$table` SET $sqlCols = $sqlVals WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $data['id'] = $id; // Ajouter l'ID à la liste des données
            return $stmt->execute($data);
        } catch (PDOException $e) {
            $this->errorMessage = "Erreur lors de la mise à jour dans la base de données: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Supprime un utilisateur
     * @param string $profil Type de profil (eleve, parent, professeur, etc.)
     * @param int $id ID de l'utilisateur
     * @return bool True si la suppression a réussi
     */
    public function delete($profil, $id) {
        if (!isset($this->tableMap[$profil])) {
            $this->errorMessage = "Type de profil '$profil' invalide.";
            return false;
        }
        
        $table = $this->tableMap[$profil];
        
        // Vérification supplémentaire pour les administrateurs
        if ($profil === 'administrateur') {
            require_once __DIR__ . '/../../API/config/admin_config.php';
            if (!isAdminManagementAllowed()) {
                $this->errorMessage = "La suppression des comptes administrateurs n'est pas autorisée.";
                return false;
            }
            
            // Assurez-vous qu'il reste au moins un administrateur actif
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE actif = 1");
                $stmt->execute();
                $adminCount = (int)$stmt->fetchColumn();
                
                if ($adminCount <= 1) {
                    $this->errorMessage = "Impossible de supprimer le dernier compte administrateur actif.";
                    return false;
                }
            } catch (PDOException $e) {
                $this->errorMessage = "Erreur lors de la vérification du nombre d'administrateurs: " . $e->getMessage();
                return false;
            }
        }
        
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `$table` WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            $this->errorMessage = "Erreur lors de la suppression: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Change le mot de passe d'un utilisateur
     * @param string $profil Type de profil (eleve, parent, professeur, etc.)
     * @param int $id ID de l'utilisateur
     * @param string $newPassword Nouveau mot de passe
     * @return bool True si le changement a réussi
     */
    public function changePassword($profil, $id, $newPassword) {
        if (!isset($this->tableMap[$profil])) {
            $this->errorMessage = "Type de profil '$profil' invalide.";
            return false;
        }
        
        // Vérification supplémentaire pour les administrateurs
        if ($profil === 'administrateur') {
            require_once __DIR__ . '/../../API/config/admin_config.php';
            $validation = validateStrongPassword($newPassword);
            if (!$validation['valid']) {
                $this->errorMessage = implode('. ', $validation['errors']);
                return false;
            }
        }
        
        $table = $this->tableMap[$profil];
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->pdo->prepare("UPDATE `$table` SET mot_de_passe = ? WHERE id = ?");
            return $stmt->execute([$passwordHash, $id]);
        } catch (PDOException $e) {
            $this->errorMessage = "Erreur lors du changement de mot de passe: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Vérifie si un utilisateur existe déjà avec les mêmes informations
     * 
     * @param string $profil Type de profil (eleve, parent, professeur, etc.)
     * @param array $data Données de l'utilisateur
     * @return bool True si l'utilisateur existe déjà
     */
    public function checkUserExists($profil, array $data) {
        if (!isset($this->tableMap[$profil])) {
            $this->errorMessage = "Type de profil '$profil' invalide.";
            return true; // Retourner true empêche la création
        }
        
        $table = $this->tableMap[$profil];
        
        // Vérifier par email et/ou identifiant si fourni
        $conditions = [];
        $params = [];
        
        if (!empty($data['mail'])) {
            $conditions[] = "mail = :mail";
            $params[':mail'] = $data['mail'];
        }
        
        if (!empty($data['identifiant'])) {
            $conditions[] = "identifiant = :identifiant";
            $params[':identifiant'] = $data['identifiant'];
        }
        
        if (empty($conditions)) {
            // Si pas de condition, on considère qu'on ne peut pas vérifier
            return false;
        }
        
        $sql = "SELECT id FROM `$table` WHERE " . implode(' OR ', $conditions) . " LIMIT 1";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->errorMessage = "Erreur lors de la vérification de l'existence de l'utilisateur: " . $e->getMessage();
            return true; // En cas d'erreur, empêcher la création
        }
    }
    
    /**
     * Crée un nouvel utilisateur avec les données fournies
     * 
     * @param string $profil Type de profil (eleve, parent, professeur, etc.)
     * @param array $data Données de l'utilisateur
     * @return array Résultat de l'opération ['success' => bool, 'message' => string, 'password' => string, 'identifiant' => string]
     */
    public function createUser($profil, array $data) {
        // Essayer de créer l'utilisateur
        if ($this->create($profil, $data)) {
            return [
                'success' => true,
                'message' => "Utilisateur créé avec succès.",
                'password' => $this->getGeneratedPassword(),
                'identifiant' => $this->getGeneratedIdentifier()
            ];
        } else {
            return [
                'success' => false,
                'message' => $this->getErrorMessage()
            ];
        }
    }
    
    /**
     * Récupère tous les utilisateurs de la base de données
     * 
     * @param int $limit Limite de résultats (0 = pas de limite)
     * @return array Liste des utilisateurs
     */
    public function getAllUsers($limit = 0) {
        $allUsers = [];
        
        try {
            foreach ($this->tableMap as $profil => $table) {
                // Skip administrator accounts for this method
                if ($profil === 'administrateur') continue;
                
                // Check if 'actif' column exists and include it if it does
                try {
                    $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'actif'");
                    $stmt->execute();
                    $actifExists = $stmt->fetch() !== false;
                    
                    $fieldsToSelect = "id, identifiant, nom, prenom, mail";
                    if ($actifExists) {
                        $fieldsToSelect .= ", actif";
                    }
                    
                    $stmt = $this->pdo->query("SELECT $fieldsToSelect FROM `$table`");
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Ajouter le profil à chaque utilisateur
                    foreach ($users as &$user) {
                        $user['profil'] = $profil;
                        // Set default value for actif if not present
                        if (!isset($user['actif'])) {
                            $user['actif'] = 1;
                        }
                    }
                    
                    $allUsers = array_merge($allUsers, $users);
                } catch (PDOException $e) {
                    // Silently handle the error for this table and continue with others
                    error_log("Error getting users from table $table: " . $e->getMessage());
                }
            }
            
            // Trier par nom
            usort($allUsers, function($a, $b) {
                return strcasecmp($a['nom'], $b['nom']);
            });
            
            // Apply limit if set
            if ($limit > 0 && count($allUsers) > $limit) {
                $allUsers = array_slice($allUsers, 0, $limit);
            }
            
            return $allUsers;
        } catch (PDOException $e) {
            $this->errorMessage = "Erreur lors de la récupération des utilisateurs: " . $e->getMessage();
            return [];
        }
    }
    
    /**
     * Recherche des utilisateurs selon des critères
     * 
     * @param string $searchTerm Terme de recherche
     * @param string $userType Type d'utilisateur (facultatif)
     * @return array Liste des utilisateurs correspondants
     */
    public function searchUsers($searchTerm, $userType = '') {
        $results = [];
        $searchTerm = '%' . $searchTerm . '%';
        
        try {
            // Si un type d'utilisateur est spécifié, rechercher uniquement dans la table correspondante
            if (!empty($userType) && isset($this->tableMap[$userType])) {
                $table = $this->tableMap[$userType];
                $stmt = $this->pdo->prepare(
                    "SELECT id, identifiant, nom, prenom, mail FROM `$table` 
                     WHERE nom LIKE ? OR prenom LIKE ? OR identifiant LIKE ? OR mail LIKE ?"
                );
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Ajouter le profil à chaque utilisateur
                foreach ($users as &$user) {
                    $user['profil'] = $userType;
                }
                
                $results = $users;
            } else {
                // Sinon, rechercher dans toutes les tables
                foreach ($this->tableMap as $profil => $table) {
                    $stmt = $this->pdo->prepare(
                        "SELECT id, identifiant, nom, prenom, mail FROM `$table` 
                         WHERE nom LIKE ? OR prenom LIKE ? OR identifiant LIKE ? OR mail LIKE ?"
                    );
                    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Ajouter le profil à chaque utilisateur
                    foreach ($users as &$user) {
                        $user['profil'] = $profil;
                    }
                    
                    $results = array_merge($results, $users);
                }
            }
            
            // Trier par nom
            usort($results, function($a, $b) {
                return strcasecmp($a['nom'], $b['nom']);
            });
            
            return $results;
        } catch (PDOException $e) {
            $this->errorMessage = "Erreur lors de la recherche d'utilisateurs: " . $e->getMessage();
            return [];
        }
    }
    
    /**
     * Récupère un utilisateur par son ID
     * 
     * @param int $id ID de l'utilisateur
     * @return array|false Informations sur l'utilisateur ou false s'il n'existe pas
     */
    public function getUserById($id) {
        try {
            foreach ($this->tableMap as $profil => $table) {
                $stmt = $this->pdo->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $user['profil'] = $profil;
                    return $user;
                }
            }
            
            $this->errorMessage = "Utilisateur non trouvé.";
            return false;
        } catch (PDOException $e) {
            $this->errorMessage = "Erreur lors de la récupération de l'utilisateur: " . $e->getMessage();
            return false;
        }
    }
}

// Ce fichier n'est plus utilisé pour la gestion des utilisateurs. Utilisez l'API centralisée.
exit;