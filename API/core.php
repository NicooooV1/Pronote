<?php
/**
 * API Core centralisée pour Pronote - Version ultra-sécurisée
 * Gestion complète et unifiée de toutes les fonctionnalités
 * Version 3.0 - Révision sécuritaire complète
 * 
 * @author Système Pronote
 * @version 3.0
 * @since 2024
 */

// ===============================
// CONFIGURATION SÉCURITÉ GLOBALE
// ===============================

// Protection contre l'exécution directe
if (!defined('PRONOTE_CORE_LOADED')) {
    define('PRONOTE_CORE_LOADED', true);
}

// Configuration stricte des erreurs selon l'environnement
$isProduction = (($_ENV['APP_ENV'] ?? 'production') === 'production');
ini_set('display_errors', $isProduction ? '0' : '1');
error_reporting($isProduction ? E_ERROR | E_WARNING | E_PARSE : E_ALL);

// Configuration sécurisée des sessions
if (session_status() === PHP_SESSION_NONE) {
    $sessionConfig = [
        'cookie_lifetime' => 0,
        'cookie_httponly' => true,
        'cookie_secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_samesite' => 'Strict'
    ];
    
    // Appliquer la configuration session de manière sécurisée
    foreach ($sessionConfig as $key => $value) {
        ini_set("session.$key", $value);
    }
    
    // Démarrer la session avec gestion d'erreur
    try {
        session_start($sessionConfig);
    } catch (Exception $e) {
        error_log("Erreur démarrage session: " . $e->getMessage());
        // En cas d'erreur, continuer sans session mais log l'erreur
    }
}

// Protection CSRF pour toutes les requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('CSRF_DISABLED')) {
    validateCSRFToken();
}

/**
 * ===============================
 * SECTION 1: CONFIGURATION
 * ===============================
 */

/**
 * Charge la configuration de manière sécurisée
 * @throws Exception Si la configuration est manquante ou corrompue
 */
function loadConfiguration() {
    static $configLoaded = false;
    
    if ($configLoaded) return;
    
    $envFile = dirname(__DIR__) . '/.env';
    
    // Vérifications sécuritaires du fichier .env
    if (!file_exists($envFile)) {
        throw new Exception("Fichier de configuration .env introuvable. Installation requise.");
    }
    
    if (!is_readable($envFile)) {
        throw new Exception("Fichier de configuration .env illisible. Vérifiez les permissions.");
    }
    
    // Lecture sécurisée du fichier
    $envContent = file_get_contents($envFile);
    if ($envContent === false) {
        throw new Exception("Impossible de lire le fichier de configuration.");
    }
    
    // Validation du contenu
    if (empty(trim($envContent))) {
        throw new Exception("Fichier de configuration vide. Installation requise.");
    }
    
    // Parsing sécurisé des variables d'environnement
    $lines = array_filter(
        array_map('trim', explode("\n", $envContent)),
        function($line) {
            return !empty($line) && strpos($line, '#') !== 0 && strpos($line, '=') !== false;
        }
    );
    
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        
        // Validation du nom de variable
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            error_log("Configuration: nom de variable invalide ignoré: $key");
            continue;
        }
        
        // Suppression sécurisée des guillemets
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        
        // Échappement pour prévenir l'injection
        $value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
        
        if (!defined($key)) {
            define($key, $value);
        }
    }
    
    // Vérifications obligatoires avec validation
    $requiredConfig = [
        'DB_HOST' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'DB_NAME' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'DB_USER' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'DB_PASS' => FILTER_UNSAFE_RAW,
        'BASE_URL' => FILTER_SANITIZE_URL
    ];
    
    foreach ($requiredConfig as $key => $filter) {
        if (!defined($key)) {
            throw new Exception("Configuration manquante: $key");
        }
        
        $value = constant($key);
        if ($filter !== FILTER_UNSAFE_RAW && filter_var($value, $filter) !== $value) {
            throw new Exception("Configuration invalide pour: $key");
        }
    }
    
    $configLoaded = true;
}

/**
 * ===============================
 * SECTION 2: BASE DE DONNÉES
 * ===============================
 */

/**
 * Connexion sécurisée à la base de données avec retry et timeout
 * @return PDO Instance PDO sécurisée
 * @throws Exception En cas d'échec de connexion
 */
function getDatabaseConnection() {
    static $pdo = null;
    static $connectionAttempts = 0;
    static $lastAttemptTime = 0;
    
    if ($pdo !== null && $pdo instanceof PDO) {
        try {
            // Test de la connexion existante
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (PDOException $e) {
            logError("Connexion DB perdue: " . $e->getMessage());
            $pdo = null;
        }
    }
    
    // Protection contre les tentatives de reconnexion trop fréquentes
    $now = time();
    if ($connectionAttempts >= 3 && ($now - $lastAttemptTime) < 60) {
        throw new Exception("Trop de tentatives de connexion. Réessayez dans 1 minute.");
    }
    
    if (($now - $lastAttemptTime) >= 60) {
        $connectionAttempts = 0;
    }
    
    try {
        loadConfiguration();
        
        $connectionAttempts++;
        $lastAttemptTime = $now;
        
        // Configuration PDO sécurisée
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=utf8mb4;port=%d",
            DB_HOST,
            DB_NAME,
            defined('DB_PORT') ? (int)DB_PORT : 3306
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Configuration de sécurité MySQL
        $securityQueries = [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION autocommit = 1"
        ];
        
        foreach ($securityQueries as $query) {
            try {
                $pdo->exec($query);
            } catch (PDOException $e) {
                logError("Configuration sécurité MySQL: " . $e->getMessage());
            }
        }
        
        // Réinitialiser le compteur en cas de succès
        $connectionAttempts = 0;
        
        // Global pour compatibilité (mais déprécié)
        $GLOBALS['pdo'] = $pdo;
        
        return $pdo;
        
    } catch (PDOException $e) {
        logError("Erreur de connexion DB (tentative $connectionAttempts): " . $e->getMessage());
        
        if ($connectionAttempts >= 3) {
            throw new Exception("Impossible de se connecter à la base de données après 3 tentatives");
        }
        
        throw new Exception("Échec de connexion à la base de données");
    }
}

/**
 * Exécute une requête préparée de manière ultra-sécurisée
 * @param string $sql Requête SQL (sera validée)
 * @param array $params Paramètres (seront validés et échappés)
 * @param int $fetchMode Mode de récupération PDO
 * @return mixed Résultat selon le type de requête
 * @throws Exception En cas d'erreur
 */
function executeQuery($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC) {
    // Validation de la requête SQL
    if (empty(trim($sql))) {
        throw new Exception("Requête SQL vide");
    }
    
    // Détection de requêtes potentiellement dangereuses
    $dangerousPatterns = [
        '/DROP\s+TABLE/i',
        '/TRUNCATE\s+TABLE/i',
        '/DELETE\s+FROM\s+\w+\s*$/i', // DELETE sans WHERE
        '/UPDATE\s+\w+\s+SET\s+.*\s*$/i', // UPDATE sans WHERE
        '/GRANT\s+/i',
        '/REVOKE\s+/i',
        '/CREATE\s+USER/i',
        '/ALTER\s+USER/i'
    ];
    
    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $sql)) {
            logSecurityEvent('dangerous_query_attempt', ['sql' => substr($sql, 0, 100)]);
            throw new Exception("Requête non autorisée pour des raisons de sécurité");
        }
    }
    
    // Validation des paramètres
    if (!is_array($params)) {
        throw new Exception("Les paramètres doivent être un tableau");
    }
    
    // Limitation du nombre de paramètres
    if (count($params) > 100) {
        throw new Exception("Trop de paramètres dans la requête");
    }
    
    // Validation et nettoyage des paramètres
    $cleanParams = [];
    foreach ($params as $key => $value) {
        if (is_object($value) && !($value instanceof DateTime)) {
            throw new Exception("Type de paramètre non autorisé: objet");
        }
        
        if (is_array($value)) {
            throw new Exception("Type de paramètre non autorisé: tableau");
        }
        
        if (is_resource($value)) {
            throw new Exception("Type de paramètre non autorisé: ressource");
        }
        
        // Limitation de taille pour les chaînes
        if (is_string($value) && strlen($value) > 65535) {
            throw new Exception("Paramètre trop long (max 65535 caractères)");
        }
        
        $cleanParams[$key] = $value;
    }
    
    try {
        $pdo = getDatabaseConnection();
        
        // Préparation avec timeout
        $startTime = microtime(true);
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Échec de préparation de la requête");
        }
        
        // Exécution avec gestion du timeout
        $executed = $stmt->execute($cleanParams);
        $executionTime = microtime(true) - $startTime;
        
        if (!$executed) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Échec d'exécution: " . ($errorInfo[2] ?? 'Erreur inconnue'));
        }
        
        // Log des requêtes lentes
        if ($executionTime > 2.0) {
            logError("Requête lente détectée: {$executionTime}s - " . substr($sql, 0, 100));
        }
        
        // Retour selon le type de requête
        $sqlUpper = strtoupper(trim($sql));
        
        if (str_starts_with($sqlUpper, 'SELECT') || str_starts_with($sqlUpper, 'SHOW') || str_starts_with($sqlUpper, 'DESCRIBE')) {
            return $stmt->fetchAll($fetchMode);
        } elseif (str_starts_with($sqlUpper, 'INSERT')) {
            return $pdo->lastInsertId();
        } else {
            return $stmt->rowCount();
        }
        
    } catch (PDOException $e) {
        $sanitizedSql = preg_replace('/\s+/', ' ', substr($sql, 0, 200));
        logError("Erreur SQL: " . $e->getMessage() . " | Query: " . $sanitizedSql);
        throw new Exception("Erreur lors de l'exécution de la requête: " . $e->getMessage());
    }
}

/**
 * Vérifie l'existence d'une table de manière sécurisée
 * @param string $tableName Nom de la table
 * @return bool True si la table existe
 */
function tableExists($tableName) {
    // Validation du nom de table
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
        logSecurityEvent('invalid_table_name', ['table' => $tableName]);
        return false;
    }
    
    if (strlen($tableName) > 64) {
        return false;
    }
    
    try {
        $result = executeQuery("SHOW TABLES LIKE ?", [$tableName]);
        return !empty($result);
    } catch (Exception $e) {
        logError("Erreur vérification table: " . $e->getMessage());
        return false;
    }
}

/**
 * ===============================
 * SECTION 3: AUTHENTIFICATION
 * ===============================
 */

/**
 * Tables de correspondance utilisateur (validées)
 * @return array Mapping sécurisé des types utilisateur
 */
function getUserTables() {
    return [
        'eleve' => 'eleves',
        'parent' => 'parents',
        'professeur' => 'professeurs',
        'vie_scolaire' => 'vie_scolaire',
        'administrateur' => 'administrateurs'
    ];
}

/**
 * Authentifie un utilisateur avec sécurité renforcée
 * @param string $username Nom d'utilisateur
 * @param string $password Mot de passe
 * @param string $userType Type d'utilisateur
 * @param bool $rememberMe Option "se souvenir de moi"
 * @return array Résultat de l'authentification
 */
function authenticateUser($username, $password, $userType, $rememberMe = false) {
    // Validation des entrées
    if (empty($username) || empty($password) || empty($userType)) {
        logSecurityEvent('auth_empty_credentials', ['type' => $userType]);
        return ['success' => false, 'message' => 'Identifiants manquants'];
    }
    
    // Validation de la longueur
    if (strlen($username) > 50 || strlen($password) > 255) {
        logSecurityEvent('auth_invalid_length', ['username' => substr($username, 0, 10)]);
        return ['success' => false, 'message' => 'Identifiants invalides'];
    }
    
    // Protection contre les attaques par force brute
    $attemptKey = 'auth_' . $userType . '_' . md5($username . ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!checkRateLimit($attemptKey, 5, 900)) { // 5 tentatives par 15 minutes
        logSecurityEvent('auth_rate_limit', ['username' => $username, 'type' => $userType]);
        return ['success' => false, 'message' => 'Trop de tentatives. Réessayez dans 15 minutes.'];
    }
    
    try {
        $tables = getUserTables();
        
        // Gestion du type "personnel" (vie_scolaire + administrateur)
        if ($userType === 'personnel') {
            $result = authenticateUser($username, $password, 'vie_scolaire', $rememberMe);
            if ($result['success']) {
                return $result;
            }
            return authenticateUser($username, $password, 'administrateur', $rememberMe);
        }
        
        if (!isset($tables[$userType])) {
            logSecurityEvent('auth_invalid_type', ['type' => $userType]);
            return ['success' => false, 'message' => 'Type d\'utilisateur invalide'];
        }
        
        $table = $tables[$userType];
        
        if (!tableExists($table)) {
            logError("Table utilisateur manquante: $table");
            return ['success' => false, 'message' => 'Service temporairement indisponible'];
        }
        
        // Requête sécurisée avec limitation des résultats
        $sql = "SELECT * FROM `$table` WHERE identifiant = ? AND actif = 1 LIMIT 1";
        $users = executeQuery($sql, [$username]);
        
        if (empty($users)) {
            logSecurityEvent('auth_user_not_found', ['username' => $username, 'type' => $userType]);
            return ['success' => false, 'message' => 'Identifiants incorrects'];
        }
        
        $user = $users[0];
        
        // Vérification du verrouillage du compte
        if (isset($user['locked_until']) && $user['locked_until'] && 
            strtotime($user['locked_until']) > time()) {
            logSecurityEvent('auth_account_locked', ['username' => $username]);
            return ['success' => false, 'message' => 'Compte temporairement verrouillé'];
        }
        
        // Vérification du mot de passe avec protection timing attack
        $passwordValid = password_verify($password, $user['mot_de_passe']);
        
        // Simulation du temps de vérification même si l'utilisateur n'existe pas
        if (empty($users)) {
            password_verify($password, '$2y$10$dummy.hash.to.prevent.timing.attacks.here');
        }
        
        if (!$passwordValid) {
            // Incrémenter le compteur d'échecs
            $failedAttempts = ($user['failed_login_attempts'] ?? 0) + 1;
            $lockUntil = null;
            
            // Verrouillage après 5 tentatives échouées
            if ($failedAttempts >= 5) {
                $lockUntil = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
            }
            
            $updateSql = "UPDATE `$table` SET failed_login_attempts = ?, locked_until = ? WHERE id = ?";
            executeQuery($updateSql, [$failedAttempts, $lockUntil, $user['id']]);
            
            logSecurityEvent('auth_failed', [
                'username' => $username,
                'type' => $userType,
                'attempts' => $failedAttempts
            ]);
            
            return ['success' => false, 'message' => 'Identifiants incorrects'];
        }
        
        // Authentification réussie - réinitialiser les compteurs
        $updateSql = "UPDATE `$table` SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?";
        executeQuery($updateSql, [0, null, $user['id']]);
        
        // Préparer les données utilisateur sécurisées
        $userData = [
            'id' => (int)$user['id'],
            'identifiant' => htmlspecialchars($user['identifiant'], ENT_QUOTES, 'UTF-8'),
            'nom' => htmlspecialchars($user['nom'], ENT_QUOTES, 'UTF-8'),
            'prenom' => htmlspecialchars($user['prenom'], ENT_QUOTES, 'UTF-8'),
            'mail' => filter_var($user['mail'], FILTER_SANITIZE_EMAIL),
            'profil' => $userType,
            'actif' => (bool)$user['actif'],
            'table' => $table
        ];
        
        // Champs spécifiques selon le type (avec validation)
        switch ($userType) {
            case 'eleve':
                $userData['classe'] = htmlspecialchars($user['classe'] ?? '', ENT_QUOTES, 'UTF-8');
                $userData['date_naissance'] = $user['date_naissance'] ?? null;
                break;
            case 'professeur':
                $userData['matiere'] = htmlspecialchars($user['matiere'] ?? '', ENT_QUOTES, 'UTF-8');
                $userData['est_pp'] = $user['professeur_principal'] ?? 'non';
                break;
            case 'vie_scolaire':
                $userData['est_CPE'] = $user['est_CPE'] ?? 'non';
                $userData['est_infirmerie'] = $user['est_infirmerie'] ?? 'non';
                break;
            case 'administrateur':
                $userData['role'] = $user['role'] ?? 'administrateur';
                break;
        }
        
        $result = ['success' => true, 'user' => $userData];
        
        // Gestion sécurisée du "Se souvenir de moi"
        if ($rememberMe) {
            $token = generateSecureToken(64);
            // TODO: Stocker le token de manière sécurisée en base
            $result['remember_token'] = $token;
        }
        
        logSecurityEvent('auth_success', [
            'username' => $username,
            'type' => $userType,
            'user_id' => $user['id']
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        logError('Erreur d\'authentification: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur système temporaire'];
    }
}

/**
 * Vérifie si l'utilisateur est connecté avec validation de session
 * @return bool True si connecté et session valide
 */
function isLoggedIn() {
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        return false;
    }
    
    // Validation de l'intégrité de la session
    $requiredFields = ['id', 'identifiant', 'nom', 'prenom', 'profil'];
    foreach ($requiredFields as $field) {
        if (!isset($_SESSION['user'][$field])) {
            logSecurityEvent('session_integrity_fail', ['missing_field' => $field]);
            session_destroy();
            return false;
        }
    }
    
    // Vérification de l'expiration de session
    if (isset($_SESSION['last_activity'])) {
        $sessionLifetime = 3600; // 1 heure
        if (time() - $_SESSION['last_activity'] > $sessionLifetime) {
            logSecurityEvent('session_expired', ['user_id' => $_SESSION['user']['id']]);
            session_destroy();
            return false;
        }
    }
    
    // Vérification de l'IP (si activée)
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        logSecurityEvent('session_ip_mismatch', [
            'stored_ip' => $_SESSION['user_ip'],
            'current_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        session_destroy();
        return false;
    }
    
    // Mettre à jour l'activité
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Récupère l'utilisateur actuel de manière sécurisée
 * @return array|null Données utilisateur ou null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return $_SESSION['user'];
}

/**
 * Exige une authentification avec redirection sécurisée
 */
function requireAuth() {
    if (!isLoggedIn()) {
        // Sauvegarder l'URL de destination de manière sécurisée
        $currentUrl = filter_var($_SERVER['REQUEST_URI'] ?? '', FILTER_SANITIZE_URL);
        if (!empty($currentUrl) && strlen($currentUrl) < 2048) {
            $_SESSION['redirect_after_login'] = $currentUrl;
        }
        
        logSecurityEvent('unauthorized_access', ['url' => $currentUrl]);
        redirect('/login/public/index.php');
        exit;
    }
}

/**
 * ===============================
 * SECTION 4: UTILITAIRES SÉCURISÉS
 * ===============================
 */

/**
 * Génère un token sécurisé
 * @param int $length Longueur du token
 * @return string Token sécurisé
 */
function generateSecureToken($length = 32) {
    if ($length < 16 || $length > 256) {
        throw new InvalidArgumentException('Longueur de token invalide');
    }
    
    try {
        return bin2hex(random_bytes($length));
    } catch (Exception $e) {
        logError('Erreur génération token: ' . $e->getMessage());
        return hash('sha256', uniqid(mt_rand(), true) . microtime());
    }
}

/**
 * Validation CSRF ultra-sécurisée
 */
function validateCSRFToken() {
    if (!isset($_POST['csrf_token'])) {
        logSecurityEvent('csrf_token_missing');
        http_response_code(403);
        die('Token de sécurité manquant');
    }
    
    $token = $_POST['csrf_token'];
    
    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        logSecurityEvent('csrf_session_invalid');
        http_response_code(403);
        die('Session de sécurité invalide');
    }
    
    $tokenFound = false;
    $now = time();
    
    // Nettoyer les tokens expirés et vérifier le token
    foreach ($_SESSION['csrf_tokens'] as $storedToken => $timestamp) {
        if ($now - $timestamp > 3600) {
            unset($_SESSION['csrf_tokens'][$storedToken]);
        } elseif (hash_equals($storedToken, $token)) {
            $tokenFound = true;
            unset($_SESSION['csrf_tokens'][$storedToken]); // Usage unique
        }
    }
    
    if (!$tokenFound) {
        logSecurityEvent('csrf_token_invalid', ['token' => substr($token, 0, 8)]);
        http_response_code(403);
        die('Token de sécurité invalide');
    }
}

/**
 * Protection contre les attaques par force brute
 * @param string $identifier Identifiant unique pour le rate limiting
 * @param int $maxAttempts Nombre maximum de tentatives
 * @param int $timeWindow Fenêtre de temps en secondes
 * @return bool True si autorisé, false si limite atteinte
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
    if (session_status() === PHP_SESSION_NONE) {
        return true; // Pas de session, pas de protection
    }
    
    $key = 'rate_limit_' . hash('sha256', $identifier);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    // Reset si la fenêtre de temps est écoulée
    if ($now - $data['start'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    // Incrémenter le compteur
    $_SESSION[$key]['count']++;
    
    // Vérifier la limite
    if ($data['count'] >= $maxAttempts) {
        return false;
    }
    
    return true;
}

/**
 * Redirection sécurisée avec validation d'URL
 * @param string $path Chemin de redirection
 */
function redirect($path) {
    // Validation du chemin
    if (empty($path)) {
        $path = '/';
    }
    
    // Empêcher les redirections ouvertes
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        // URL absolue - vérifier le domaine
        $allowedHosts = [
            $_SERVER['HTTP_HOST'] ?? 'localhost'
        ];
        
        $parsedUrl = parse_url($path);
        if (!isset($parsedUrl['host']) || !in_array($parsedUrl['host'], $allowedHosts)) {
            logSecurityEvent('open_redirect_attempt', ['url' => $path]);
            $path = '/';
        }
    } else {
        // URL relative - valider le format
        if (!preg_match('#^/[a-zA-Z0-9/_\-\.]*(\?[a-zA-Z0-9=&_\-]*)?$#', $path)) {
            logSecurityEvent('invalid_redirect_path', ['path' => $path]);
            $path = '/';
        }
    }
    
    try {
        loadConfiguration();
        $url = BASE_URL . $path;
    } catch (Exception $e) {
        $url = $path;
    }
    
    // Protection supplémentaire
    if (headers_sent()) {
        echo "<script>window.location.href = '" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'></noscript>";
        exit;
    }
    
    header("Location: " . $url);
    exit;
}

/**
 * ===============================
 * SECTION 5: LOGGING SÉCURISÉ
 * ===============================
 */

/**
 * Log d'événement de sécurité
 * @param string $event Type d'événement
 * @param array $data Données contextuelles
 */
function logSecurityEvent($event, $data = []) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $user = getCurrentUser();
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $user['id'] ?? null,
        'user_type' => $user['profil'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
        'data' => $data
    ];
    
    $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
    
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    
    // Log également dans le système pour les événements critiques
    $criticalEvents = ['auth_failed', 'csrf_token_invalid', 'session_hijack', 'dangerous_query_attempt'];
    if (in_array($event, $criticalEvents)) {
        error_log("SECURITY: $event - " . json_encode($data));
    }
}

/**
 * Log d'erreur sécurisé
 * @param string $message Message d'erreur
 */
function logError($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = $backtrace[1] ?? $backtrace[0];
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ERROR',
        'message' => $message,
        'file' => basename($caller['file'] ?? 'unknown'),
        'line' => $caller['line'] ?? 'unknown',
        'user_id' => ($_SESSION['user']['id'] ?? null)
    ];
    
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
    
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * ===============================
 * SECTION 6: INITIALISATION
 * ===============================
 */

// Chargement sécurisé de la configuration
try {
    loadConfiguration();
} catch (Exception $e) {
    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($currentScript, ['install.php', 'diagnostic.php'])) {
        error_log("Erreur configuration critique: " . $e->getMessage());
        http_response_code(503);
        die('Service temporairement indisponible. Configuration manquante.');
    }
}

// Initialisation de la connexion DB avec gestion d'erreur
if (!isset($GLOBALS['pdo'])) {
    try {
        $GLOBALS['pdo'] = getDatabaseConnection();
        $pdo = $GLOBALS['pdo'];
    } catch (Exception $e) {
        $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($currentScript, ['install.php', 'diagnostic.php'])) {
            logError("Erreur connexion DB critique: " . $e->getMessage());
            http_response_code(503);
            die('Service temporairement indisponible. Base de données inaccessible.');
        }
    }
}

/**
 * ===============================
 * FIN DE L'API CORE SÉCURISÉE
 * ===============================
 */
?>
