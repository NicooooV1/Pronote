<?php
/**
 * Core API file for Pronote system
 * This file provides centralized database connection management and session handling
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define database constants if not already defined
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'db_MASSE');
if (!defined('DB_USER')) define('DB_USER', '22405372');
if (!defined('DB_PASS')) define('DB_PASS', '807014');

// Create a single PDO connection if it doesn't exist already
if (!isset($GLOBALS['pdo'])) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10
        ];
        
        $GLOBALS['pdo'] = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo = $GLOBALS['pdo']; // Alias global pour compatibilité
        
    } catch (PDOException $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Erreur de connexion DB dans API core: " . $e->getMessage());
        }
        // Ne pas faire die() ici pour permettre l'installation
    }
}

/**
 * Initialise la connexion à la base de données si pas encore fait
 * @return PDO|null
 */
function initializeDatabaseConnection() {
    global $pdo;
    
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }
    
    if (!defined('DB_HOST')) {
        return null;
    }
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $GLOBALS['pdo'] = $pdo;
        
        return $pdo;
        
    } catch (PDOException $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Erreur lors de l'initialisation de la connexion DB: " . $e->getMessage());
        }
        return null;
    }
}

/**
 * Obtient la connexion à la base de données, l'initialise si nécessaire
 * @return PDO
 * @throws Exception si la connexion échoue
 */
function getDatabaseConnection() {
    global $pdo;
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = initializeDatabaseConnection();
    }
    
    if (!$pdo) {
        throw new Exception("Impossible d'établir une connexion à la base de données");
    }
    
    return $pdo;
}

/**
 * Authentifie un utilisateur
 */
function authenticateUser($username, $password, $userType, $rememberMe = false) {
    global $pdo;
    
    try {
        // Tables de correspondance
        $tableMap = [
            'eleve' => 'eleves',
            'parent' => 'parents', 
            'professeur' => 'professeurs',
            'vie_scolaire' => 'vie_scolaire',
            'administrateur' => 'administrateurs'
        ];
        
        // Gestion spéciale pour le personnel (vie_scolaire + administrateur)
        if ($userType === 'personnel') {
            // Essayer d'abord vie_scolaire
            $vieResult = authenticateUser($username, $password, 'vie_scolaire', $rememberMe);
            if ($vieResult['success']) {
                return $vieResult;
            }
            // Puis administrateur
            return authenticateUser($username, $password, 'administrateur', $rememberMe);
        }
        
        if (!isset($tableMap[$userType])) {
            return ['success' => false, 'message' => 'Type d\'utilisateur non valide'];
        }
        
        $table = $tableMap[$userType];
        
        // Requête sécurisée
        $sql = "SELECT * FROM {$table} WHERE identifiant = ? AND actif = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur non trouvé'];
        }
        
        // Vérifier le mot de passe
        if (!password_verify($password, $user['mot_de_passe'])) {
            return ['success' => false, 'message' => 'Mot de passe incorrect'];
        }
        
        // Préparer les données utilisateur
        $userData = [
            'id' => $user['id'],
            'identifiant' => $user['identifiant'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'mail' => $user['mail'],
            'profil' => $userType,
            'actif' => $user['actif']
        ];
        
        // Ajouter des champs spécifiques selon le type
        switch ($userType) {
            case 'eleve':
                $userData['classe'] = $user['classe'] ?? '';
                $userData['date_naissance'] = $user['date_naissance'] ?? '';
                break;
            case 'professeur':
                $userData['matiere'] = $user['matiere'] ?? '';
                $userData['est_pp'] = $user['professeur_principal'] ?? 'non';
                break;
            case 'vie_scolaire':
                $userData['est_CPE'] = $user['est_CPE'] ?? 'non';
                $userData['est_infirmerie'] = $user['est_infirmerie'] ?? 'non';
                break;
        }
        
        // Mettre à jour la dernière connexion
        $updateSql = "UPDATE {$table} SET last_login = NOW() WHERE id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$user['id']]);
        
        $result = ['success' => true, 'user' => $userData];
        
        // Gestion du "Se souvenir de moi"
        if ($rememberMe) {
            $token = generateRememberToken($user['id'], $userType);
            $result['remember_token'] = $token;
        }
        
        return $result;
        
    } catch (Exception $e) {
        logError('Authentication error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur système'];
    }
}

/**
 * Déconnecte l'utilisateur
 */
function logoutUser() {
    // Démarrer la session si nécessaire
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Log de déconnexion
    if (isset($_SESSION['user'])) {
        logUserAction('logout', 'Déconnexion', [
            'user_id' => $_SESSION['user']['id'],
            'user_type' => $_SESSION['user']['profil']
        ]);
    }
    
    // Nettoyer la session
    $_SESSION = [];
    
    // Supprimer les cookies
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Supprimer le cookie "remember me"
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
    }
    
    // Détruire la session
    session_destroy();
    
    // Redirection
    redirect('/login/public/index.php');
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Récupère l'utilisateur actuel
 */
function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['user'] ?? null;
}

/**
 * Exige une authentification
 */
function requireAuth() {
    if (!isLoggedIn()) {
        redirect('/login/public/index.php');
    }
}

/**
 * Exige un rôle spécifique
 */
function requireRole($role) {
    $user = getCurrentUser();
    if (!$user || $user['profil'] !== $role) {
        redirect('/accueil/accueil.php');
    }
}

/**
 * Crée un nouvel utilisateur
 */
function createUser($type, $userData) {
    global $pdo;
    
    try {
        // Tables de correspondance
        $tableMap = [
            'eleve' => 'eleves',
            'parent' => 'parents',
            'professeur' => 'professeurs', 
            'vie_scolaire' => 'vie_scolaire',
            'administrateur' => 'administrateurs'
        ];
        
        if (!isset($tableMap[$type])) {
            return ['success' => false, 'message' => 'Type d\'utilisateur non valide'];
        }
        
        $table = $tableMap[$type];
        
        // Générer identifiant et mot de passe
        $identifiant = generateUserIdentifiant($type, $userData);
        $password = generateRandomPassword();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Construire la requête selon le type
        switch ($type) {
            case 'eleve':
                $sql = "INSERT INTO {$table} (nom, prenom, identifiant, mot_de_passe, mail, date_naissance, lieu_naissance, classe, adresse, actif) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $params = [
                    $userData['nom'], $userData['prenom'], $identifiant, $hashedPassword,
                    $userData['mail'], $userData['date_naissance'], $userData['lieu_naissance'],
                    $userData['classe'], $userData['adresse']
                ];
                break;
                
            case 'professeur':
                $sql = "INSERT INTO {$table} (nom, prenom, identifiant, mot_de_passe, mail, matiere, adresse, professeur_principal, actif) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $params = [
                    $userData['nom'], $userData['prenom'], $identifiant, $hashedPassword,
                    $userData['mail'], $userData['matiere'], $userData['adresse'],
                    $userData['est_pp'] ?? 'non'
                ];
                break;
                
            case 'parent':
                $sql = "INSERT INTO {$table} (nom, prenom, identifiant, mot_de_passe, mail, adresse, actif) 
                        VALUES (?, ?, ?, ?, ?, ?, 1)";
                $params = [
                    $userData['nom'], $userData['prenom'], $identifiant, $hashedPassword,
                    $userData['mail'], $userData['adresse']
                ];
                break;
                
            case 'vie_scolaire':
                $sql = "INSERT INTO {$table} (nom, prenom, identifiant, mot_de_passe, mail, adresse, est_CPE, est_infirmerie, actif) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $params = [
                    $userData['nom'], $userData['prenom'], $identifiant, $hashedPassword,
                    $userData['mail'], $userData['adresse'],
                    $userData['est_CPE'] ?? 'non', $userData['est_infirmerie'] ?? 'non'
                ];
                break;
                
            case 'administrateur':
                // Gestion spéciale pour les administrateurs
                $sql = "INSERT INTO {$table} (nom, prenom, identifiant, mot_de_passe, mail, adresse, role, actif) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                $params = [
                    $userData['nom'], $userData['prenom'], $identifiant, $hashedPassword,
                    $userData['mail'], $userData['adresse'], 'administrateur'
                ];
                break;
                
            default:
                return ['success' => false, 'message' => 'Type non supporté'];
        }
        
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            return [
                'success' => true,
                'identifiant' => $identifiant,
                'password' => $password,
                'user_id' => $pdo->lastInsertId()
            ];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la création'];
        }
        
    } catch (Exception $e) {
        logError('User creation error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur système'];
    }
}

/**
 * Génère un identifiant utilisateur unique
 */
function generateUserIdentifiant($type, $userData) {
    global $pdo;
    
    $base = strtolower(substr($userData['prenom'], 0, 1) . $userData['nom']);
    $base = preg_replace('/[^a-z0-9]/', '', $base);
    $base = substr($base, 0, 8);
    
    // Ajouter un suffixe selon le type
    $typePrefix = [
        'eleve' => 'e',
        'parent' => 'p', 
        'professeur' => 'prof',
        'vie_scolaire' => 'vs',
        'administrateur' => 'admin'
    ];
    
    $identifiant = $typePrefix[$type] . $base;
    
    // Vérifier l'unicité
    $tables = ['eleves', 'parents', 'professeurs', 'vie_scolaire', 'administrateurs'];
    $counter = 1;
    $originalIdentifiant = $identifiant;
    
    do {
        $exists = false;
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE identifiant = ?");
                $stmt->execute([$identifiant]);
                if ($stmt->fetchColumn() > 0) {
                    $exists = true;
                    break;
                }
            } catch (Exception $e) {
                // Ignorer les erreurs de tables qui n'existent pas encore
                continue;
            }
        }
        
        if ($exists) {
            $identifiant = $originalIdentifiant . $counter;
            $counter++;
        }
    } while ($exists && $counter < 1000); // Éviter les boucles infinies
    
    return $identifiant;
}

/**
 * Génère un mot de passe aléatoire
 */
function generateRandomPassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $password;
}

/**
 * Génère un token "Se souvenir de moi"
 */
function generateRememberToken($userId, $userType) {
    try {
        $token = bin2hex(random_bytes(32));
        // Ici vous pourriez stocker le token en base pour validation ultérieure
        return $token;
    } catch (Exception $e) {
        return hash('sha256', uniqid($userId . $userType, true));
    }
}

/**
 * Récupère les données d'établissement
 */
function getEtablissementData() {
    global $pdo;
    
    try {
        $data = ['classes' => [], 'matieres' => []];
        
        // Récupérer les classes
        $stmt = $pdo->query("SELECT nom, niveau FROM classes WHERE actif = 1 ORDER BY niveau, nom");
        $classes = $stmt->fetchAll();
        
        foreach ($classes as $classe) {
            $data['classes'][$classe['niveau']][] = $classe['nom'];
        }
        
        // Récupérer les matières
        $stmt = $pdo->query("SELECT nom, code FROM matieres WHERE actif = 1 ORDER BY nom");
        $data['matieres'] = $stmt->fetchAll();
        
        return $data;
        
    } catch (Exception $e) {
        logError('Error getting establishment data: ' . $e->getMessage());
        return ['classes' => [], 'matieres' => []];
    }
}

/**
 * Redirection sécurisée
 */
function redirect($path) {
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    $url = $baseUrl . $path;
    header("Location: " . $url);
    exit;
}
?>
