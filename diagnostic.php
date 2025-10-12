<?php
/**
 * Page de diagnostic pour Pronote - RÉSERVÉE AUX ADMINISTRATEURS
 * Version améliorée avec sécurité renforcée et diagnostics avancés
 */

// Vérification immédiate des tentatives d'accès direct
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // Définir un timeout plus court pour les scripts de diagnostic
    set_time_limit(60);
}

// Démarrer la session pour la vérification d'authentification
if (session_status() === PHP_SESSION_NONE) {
    // Configuration sécurisée des cookies de session
    ini_set('session.cookie_httponly', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_strict_mode', 1); // Activation du mode strict pour les sessions
    ini_set('session.use_only_cookies', 1); // Utiliser uniquement les cookies
    session_start();
}

// Vérifier si l'utilisateur est administrateur avant même de charger d'autres fichiers
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['profil']) || $_SESSION['user']['profil'] !== 'administrateur') {
    // Rediriger vers la page de connexion ou afficher un message d'erreur
    http_response_code(403); // Forbidden
    echo '<!DOCTYPE html><html><head><title>Accès refusé</title><meta charset="UTF-8"></head>';
    echo '<body><h1>Accès refusé</h1><p>Seuls les administrateurs peuvent accéder à cette page.</p>';
    echo '<p><a href="login/public/index.php">Connexion</a></p></body></html>';
    exit;
}

/**
 * Teste les permissions d'un répertoire de manière sécurisée
 * @param string $directory Chemin du répertoire
 * @return array Résultats des tests
 */
function testDirectoryPermissions($directory) {
    $results = [];
    
    try {
        // Vérifier que le chemin est valide et ne contient pas d'injection de chemin
        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            $realDirectory = $directory; // Fallback si le répertoire n'existe pas encore
        }
        
        // Vérifier si le répertoire existe
        if (is_dir($realDirectory)) {
            $results['exists'] = true;
            
            // Vérifier les permissions
            $results['readable'] = is_readable($realDirectory);
            $results['writable'] = is_writable($realDirectory);
            
            // Essayer de créer un fichier temporaire avec un nom sécurisé
            $testFile = rtrim($realDirectory, '/\\') . DIRECTORY_SEPARATOR . 'test_' . bin2hex(random_bytes(8)) . '.txt';
            $canWrite = false;
            try {
                $canWrite = file_put_contents($testFile, 'Test') !== false;
            } catch (\Exception $e) {
                $canWrite = false;
            }
            $results['can_write_file'] = $canWrite;
            
            // Supprimer le fichier de test s'il a été créé
            if ($canWrite && file_exists($testFile)) {
                @unlink($testFile);
            }
        } else {
            $results['exists'] = false;
            $results['readable'] = false;
            $results['writable'] = false;
            $results['can_write_file'] = false;
            
            // Essayer de créer le répertoire
            $canCreate = false;
            try {
                // Utiliser le répertoire parent pour éviter les chemins non valides
                $parentDir = dirname($realDirectory);
                if (is_dir($parentDir) && is_writable($parentDir)) {
                    $canCreate = @mkdir($realDirectory, 0755, true);
                    if ($canCreate) {
                        @rmdir($realDirectory); // Supprimer le répertoire créé
                    }
                }
            } catch (\Exception $e) {
                $canCreate = false;
            }
            $results['can_create'] = $canCreate;
        }
    } catch (\Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

/**
 * Teste l'accessibilité d'une page de manière sécurisée
 * @param string $url URL à tester
 * @return array Résultats du test
 */
function testPageAccess($url) {
    $result = [
        'url' => $url,
        'code' => 0,
        'accessible' => false,
        'error' => null,
        'content_type' => null,
        'redirect_count' => 0,
        'final_url' => null
    ];
    
    // Validation basique de l'URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $result['error'] = "URL non valide";
        return $result;
    }
    
    try {
        if (!function_exists('curl_init')) {
            throw new \Exception('cURL n\'est pas disponible. Utilisez la vérification manuelle.');
        }
        
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \Exception('Impossible d\'initialiser cURL');
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false); // Modifié pour récupérer le contenu
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Suivre les redirections
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Augmenté le nombre max de redirections
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Pronote-Diagnostic/1.0');
        
        // Ajouter un cookie de session pour les pages qui nécessitent l'authentification
        if (isset($_COOKIE[session_name()])) {
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
        }
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $result['error'] = curl_error($ch);
        } else {
            // Analyser l'en-tête et le corps de la réponse
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            
            // Récupérer les infos sur la réponse
            $result['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['content_type'] = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $result['redirect_count'] = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
            $result['final_url'] = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            
            // Vérifier si la page est accessible
            $result['accessible'] = ($result['code'] >= 200 && $result['code'] < 400);
            
            // Vérifier si le contenu contient des messages d'erreur courants
            if ($result['accessible']) {
                if (strpos($body, 'Fatal error') !== false || 
                    strpos($body, 'Parse error') !== false ||
                    strpos($body, 'Warning:') !== false ||
                    strpos($body, 'Notice:') !== false ||
                    strpos($body, 'SQL syntax') !== false ||
                    strpos($body, 'database error') !== false) {
                    $result['accessible'] = false;
                    $result['error'] = "La page contient des erreurs PHP ou SQL visibles";
                }
                
                // Vérifier si c'est une page de login alors qu'on devrait être connecté
                if (strpos($body, 'form method="post"') !== false && 
                    (strpos($body, 'password') !== false || strpos($body, 'mot de passe') !== false)) {
                    $result['warning'] = "Cette page semble être un formulaire de connexion. Vous pourriez avoir été redirigé.";
                }
            }
        }
        
        curl_close($ch);
    } catch (\Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

/**
 * Masque les informations sensibles dans le contenu des fichiers
 * @param string $content Contenu à nettoyer
 * @return string Contenu nettoyé
 */
function sanitizeSensitiveData($content) {
    // Masquer les mots de passe
    $content = preg_replace('/([\'"]DB_PASS[\'"]\s*,\s*[\'"]).*?([\'"])/', '$1********$2', $content);
    $content = preg_replace('/(\$db_pass\s*=\s*[\'"]).*?([\'"])/', '$1********$2', $content);
    
    // Masquer les jetons de sécurité
    $content = preg_replace('/(csrf_token|token|secret|key)\s*=\s*[\'"].*?[\'"]/', '$1="********"', $content);
    
    // Masquer les identifiants de session
    $content = preg_replace('/PHPSESSID=([a-zA-Z0-9]{3}).*?;/', 'PHPSESSID=$1***;', $content);
    
    // Masquer les chemins du système de fichiers avec une regex plus précise
    $content = preg_replace('#(/[a-z0-9\._\-/]+)#i', '[CHEMIN_SYSTEME]', $content);
    
    return $content;
}

/**
 * Masque les chemins absolus dans les messages d'erreur
 * @param string $path Chemin à masquer
 * @return string Chemin masqué
 */
function sanitizePath($path) {
    // Définir APP_ROOT si pas encore défini
    if (!defined('APP_ROOT') && file_exists(__DIR__ . '/API/config/env.php')) {
        require_once __DIR__ . '/API/config/env.php';
    }
    
    if (defined('APP_ROOT')) {
        // Remplacer le chemin absolu par un chemin relatif
        return str_replace(APP_ROOT, '[APP_ROOT]', $path);
    } else {
        // Masquer au moins le début du chemin absolu
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        
        // Ne garder que les 2 dernières parties du chemin pour anonymiser
        if (count($parts) > 2) {
            return '...' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, array_slice($parts, -2));
        }
    }
    
    return $path;
}

/**
 * Génère un token anti-CSRF sécurisé
 * @return string Le token généré
 */
function generateDiagToken() {
    if (!isset($_SESSION['diag_token']) || empty($_SESSION['diag_token'])) {
        try {
            $_SESSION['diag_token'] = bin2hex(random_bytes(32));
            $_SESSION['diag_token_time'] = time(); // Ajouter un timestamp pour expiration
        } catch (\Exception $e) {
            // Fallback plus sécurisé que md5
            $_SESSION['diag_token'] = hash('sha256', uniqid(mt_rand(), true));
            $_SESSION['diag_token_time'] = time();
        }
    }
    
    // Vérifier l'expiration du token (30 minutes)
    if (isset($_SESSION['diag_token_time']) && time() - $_SESSION['diag_token_time'] > 1800) {
        // Régénérer le token s'il a expiré
        try {
            $_SESSION['diag_token'] = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            $_SESSION['diag_token'] = hash('sha256', uniqid(mt_rand(), true));
        }
        $_SESSION['diag_token_time'] = time();
    }
    
    return $_SESSION['diag_token'];
}

/**
 * Teste la sécurité de configuration PHP
 * @return array Résultats des tests
 */
function testPhpSecurity() {
    $results = [];
    
    // Vérifier les directives de configuration importantes
    $results['display_errors'] = [
        'name' => 'display_errors',
        'value' => ini_get('display_errors'),
        'recommendation' => 'Off en production',
        'status' => ini_get('display_errors') ? 'warning' : 'success'
    ];
    
    $results['session.use_strict_mode'] = [
        'name' => 'session.use_strict_mode',
        'value' => ini_get('session.use_strict_mode'),
        'recommendation' => '1',
        'status' => ini_get('session.use_strict_mode') ? 'success' : 'error'
    ];
    
    $results['session.cookie_secure'] = [
        'name' => 'session.cookie_secure',
        'value' => ini_get('session.cookie_secure'),
        'recommendation' => '1 si HTTPS',
        'status' => ini_get('session.cookie_secure') ? 'success' : 'warning'
    ];
    
    $results['session.cookie_httponly'] = [
        'name' => 'session.cookie_httponly',
        'value' => ini_get('session.cookie_httponly'),
        'recommendation' => '1',
        'status' => ini_get('session.cookie_httponly') ? 'success' : 'error'
    ];
    
    $results['session.use_only_cookies'] = [
        'name' => 'session.use_only_cookies',
        'value' => ini_get('session.use_only_cookies'),
        'recommendation' => '1',
        'status' => ini_get('session.use_only_cookies') ? 'success' : 'error'
    ];
    
    $results['session.gc_maxlifetime'] = [
        'name' => 'session.gc_maxlifetime',
        'value' => ini_get('session.gc_maxlifetime'),
        'recommendation' => '≥ 1800 (30 minutes)',
        'status' => (ini_get('session.gc_maxlifetime') >= 1800) ? 'success' : 'warning'
    ];
    
    $results['expose_php'] = [
        'name' => 'expose_php',
        'value' => ini_get('expose_php'),
        'recommendation' => 'Off en production',
        'status' => ini_get('expose_php') ? 'warning' : 'success'
    ];
    
    $results['allow_url_include'] = [
        'name' => 'allow_url_include',
        'value' => ini_get('allow_url_include'),
        'recommendation' => 'Off',
        'status' => ini_get('allow_url_include') ? 'error' : 'success'
    ];
    
    $results['open_basedir'] = [
        'name' => 'open_basedir',
        'value' => ini_get('open_basedir') ? ini_get('open_basedir') : 'Non défini',
        'recommendation' => 'Définir pour limiter l\'accès aux fichiers',
        'status' => ini_get('open_basedir') ? 'success' : 'warning'
    ];
    
    return $results;
}

/**
 * Vérifie s'il existe des mises à jour critiques de sécurité
 * @return array Résultats des vérifications
 */
function checkSecurityUpdates() {
    $results = [];
    
    // Vérifier la version de PHP
    $phpVersion = PHP_VERSION;
    $results['php_version'] = [
        'name' => 'Version PHP',
        'value' => $phpVersion,
        'recommendation' => '7.4 ou supérieur',
        'status' => version_compare($phpVersion, '7.4', '>=') ? 'success' : 'error'
    ];
    
    // Vérifier si SSL est activé
    $sslEnabled = extension_loaded('openssl');
    $results['ssl'] = [
        'name' => 'Support SSL',
        'value' => $sslEnabled ? 'Activé' : 'Désactivé',
        'recommendation' => 'Activé',
        'status' => $sslEnabled ? 'success' : 'error'
    ];
    
    // Vérifier si le serveur est accessible en HTTPS
    $httpsEnabled = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $results['https'] = [
        'name' => 'HTTPS',
        'value' => $httpsEnabled ? 'Activé' : 'Désactivé',
        'recommendation' => 'Activé',
        'status' => $httpsEnabled ? 'success' : 'warning'
    ];
    
    return $results;
}

// Charger le système d'autoloading si disponible
$autoloadFile = __DIR__ . '/API/autoload.php';
if (file_exists($autoloadFile) && is_readable($autoloadFile)) {
    require_once $autoloadFile;
} else {
    // Charger manuellement les fichiers nécessaires
    $configFile = __DIR__ . '/API/config/config.php';
    if (file_exists($configFile) && is_readable($configFile)) {
        require_once $configFile;
    }
}

// Génération d'un token pour la protection du formulaire de diagnostic
$diagToken = generateDiagToken();

// Répertoires à tester - Utiliser des chemins relatifs pour plus de portabilité
$directories = [
    'API' => __DIR__ . '/API',
    'API/logs' => __DIR__ . '/API/logs',
    'API/config' => __DIR__ . '/API/config',
    'uploads' => __DIR__ . '/uploads',
    'login/logs' => __DIR__ . '/login/logs',
    'temp' => __DIR__ . '/temp'
];

// Déterminer dynamiquement l'URL de base
$baseUrl = '';
if (!empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['SCRIPT_NAME'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    if ($scriptPath !== '/' && $scriptPath !== '\\') {
        $baseUrl .= $scriptPath;
    }
}

// Définir l'URL de base si elle est déjà configurée
if (defined('BASE_URL')) {
    $baseUrl = BASE_URL;
}

// Pages à tester avec des descriptions plus détaillées
$pages = [
    'Accueil' => [
        'url' => $baseUrl . '/accueil/accueil.php',
        'description' => 'Page d\'accueil principale'
    ],
    'Login' => [
        'url' => $baseUrl . '/login/public/index.php',
        'description' => 'Page de connexion'
    ],
    'Logout' => [
        'url' => $baseUrl . '/login/public/logout.php',
        'description' => 'Déconnexion'
    ],
    'Notes' => [
        'url' => $baseUrl . '/notes/notes.php',
        'description' => 'Module de gestion des notes'
    ],
    'Ajouter Note' => [
        'url' => $baseUrl . '/notes/ajouter_note.php',
        'description' => 'Formulaire d\'ajout de notes'
    ],
    'Absences' => [
        'url' => $baseUrl . '/absences/absences.php',
        'description' => 'Liste des absences'
    ],
    'Justificatifs' => [
        'url' => $baseUrl . '/absences/justificatifs.php',
        'description' => 'Gestion des justificatifs d\'absence'
    ],
    'Agenda' => [
        'url' => $baseUrl . '/agenda/agenda.php',
        'description' => 'Calendrier et événements'
    ],
    'Cahier de Textes' => [
        'url' => $baseUrl . '/cahierdetextes/cahierdetextes.php',
        'description' => 'Cahier de textes et devoirs'
    ],
    'Messagerie' => [
        'url' => $baseUrl . '/messagerie/index.php',
        'description' => 'Système de messagerie'
    ]
];

// Détection du chemin absolu de l'application
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$applicationPath = dirname($scriptPath);

// Détection des configurations importantes
$configFiles = [
    'API/config/env.php' => __DIR__ . '/API/config/env.php',
    'API/config/config.php' => __DIR__ . '/API/config/config.php'
];

// Lire le contenu des fichiers de configuration de manière sécurisée
$configContents = [];
foreach ($configFiles as $name => $path) {
    if (file_exists($path) && is_readable($path)) {
        // Charger le contenu mais masquer les informations sensibles
        $content = file_get_contents($path);
        $content = sanitizeSensitiveData($content);
        $configContents[$name] = $content;
    } else {
        $configContents[$name] = 'Fichier non trouvé ou inaccessible';
    }
}

// Informations de session sécurisées
$sessionInfo = [
    'session_id' => '********' . (session_id() ? substr(session_id(), -4) : '****'),
    'session_status' => session_status(),
    'session_name' => session_name(),
    'session_cookie_params' => session_get_cookie_params(),
    'user_logged_in' => isset($_SESSION['user']),
    'user_role' => $_SESSION['user']['profil'] ?? 'Non connecté'
];

// Vérifier les fichiers d'authentification des modules
$authFiles = [
    'API/autoload.php' => __DIR__ . '/API/autoload.php',
    'API/core/auth.php' => __DIR__ . '/API/core/auth.php',
    'Notes/includes/auth.php' => __DIR__ . '/notes/includes/auth.php',
    'Absences/includes/auth.php' => __DIR__ . '/absences/includes/auth.php',
    'Agenda/includes/auth.php' => __DIR__ . '/agenda/includes/auth.php',
    'CahierDeTextes/includes/auth.php' => __DIR__ . '/cahierdetextes/includes/auth.php',
];

$authFilesStatus = [];
foreach ($authFiles as $name => $path) {
    $authFilesStatus[$name] = [
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'modified' => file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : 'N/A'
    ];
}

// Tests de sécurité PHP
$phpSecurity = testPhpSecurity();

// Vérifications de mises à jour de sécurité
$securityUpdates = checkSecurityUpdates();

// Tenter de charger la configuration directement
$dbCredentials = [
    'host' => 'localhost',
    'dbname' => '',
    'user' => '',
    'pass' => ''
];

// Essayer de charger les constantes depuis env.php
$envFile = __DIR__ . '/API/config/env.php';
if (file_exists($envFile) && is_readable($envFile)) {
    include_once $envFile;
    if (defined('DB_HOST')) $dbCredentials['host'] = DB_HOST;
    if (defined('DB_NAME')) $dbCredentials['dbname'] = DB_NAME;
    if (defined('DB_USER')) $dbCredentials['user'] = DB_USER;
    if (defined('DB_PASS')) $dbCredentials['pass'] = DB_PASS;
}

// Tester la connexion à la base de données
$dbTest = testDatabaseConnection(
    $dbCredentials['host'], 
    $dbCredentials['dbname'], 
    $dbCredentials['user'], 
    $dbCredentials['pass']
);

// Vérifier la structure des tables essentielles de façon sécurisée
try {
    $dbStatus = ['connected' => $dbTest['success'], 'tables' => [], 'error' => $dbTest['error'] ?? null];
    
    if ($dbStatus['connected']) {
        // Si la connexion a réussi, vérifier les tables
        $dsn = "mysql:host={$dbCredentials['host']};dbname={$dbCredentials['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $pdo = new PDO($dsn, $dbCredentials['user'], $dbCredentials['pass'], $options);
        
        // Vérifier les tables critiques
        $criticalTables = [
            'administrateurs', 'eleves', 'professeurs', 'vie_scolaire',
            'notes', 'absences', 'evenements', 'messages', 'justificatifs'
        ];
        
        foreach ($criticalTables as $table) {
            try {
                // Utiliser des requêtes préparées même pour les requêtes simples
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                $exists = $stmt && $stmt->rowCount() > 0;
                
                if ($exists) {
                    // Vérifier le nombre d'enregistrements
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `" . $table . "`");
                    $countStmt->execute();
                    $count = $countStmt ? $countStmt->fetchColumn() : '?';
                    
                    // Vérifier la structure de la table
                    $columnsStmt = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "`");
                    $columnsStmt->execute();
                    $columnsCount = $columnsStmt->rowCount();
                    $columns = $columnsStmt->fetchAll();
                    $columnsData = [];
                    
                    foreach ($columns as $col) {
                        $columnsData[] = [
                            'name' => $col['Field'],
                            'type' => $col['Type'],
                            'key' => $col['Key']
                        ];
                    }
                    
                    $dbStatus['tables'][$table] = [
                        'exists' => true,
                        'count' => $count,
                        'columns_count' => $columnsCount,
                        'columns' => $columnsData
                    ];
                } else {
                    $dbStatus['tables'][$table] = [
                        'exists' => false,
                        'count' => 'N/A',
                        'error' => "La table n'existe pas"
                    ];
                }
            } catch (\PDOException $e) {
                $dbStatus['tables'][$table] = [
                    'exists' => false,
                    'count' => 'N/A',
                    'error' => "Erreur lors de l'accès à la table: " . $e->getMessage()
                ];
            }
        }
        
        // Vérifier les indexs important manquants
        $dbStatus['missing_indexes'] = [];
        
        // Vérifier l'index sur la table notes (id_eleve, id_matiere)
        try {
            $indexStmt = $pdo->query("SHOW INDEX FROM notes");
            $indexes = $indexStmt->fetchAll();
            $hasEleveMatiere = false;
            
            foreach ($indexes as $idx) {
                if (($idx['Column_name'] === 'id_eleve' || $idx['Column_name'] === 'id_matiere') && 
                    $idx['Key_name'] === 'idx_eleve_matiere') {
                    $hasEleveMatiere = true;
                    break;
                }
            }
            
            if (!$hasEleveMatiere) {
                $dbStatus['missing_indexes'][] = "Index manquant sur la table notes: idx_eleve_matiere";
            }
        } catch (\PDOException $e) {
            // La table notes n'existe peut-être pas ou autre erreur
        }
    }
} catch (\Exception $e) {
    $dbStatus = [
        'connected' => false,
        'error' => "Erreur lors de la connexion à la base de données: " . $e->getMessage()
    ];
}

/**
 * Teste spécifique de la connexion à la base de données 
 * @param string $host Hôte de la base de données
 * @param string $dbName Nom de la base de données
 * @param string $user Nom d'utilisateur
 * @param string $pass Mot de passe
 * @return array Résultat du test
 */
function testDatabaseConnection($host, $dbName, $user, $pass) {
    $result = ['success' => false, 'error' => null, 'details' => []];
    
    try {
        // Tenter la connexion
        $start = microtime(true);
        $dsn = "mysql:host={$host};";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        // Essayer de se connecter au serveur sans spécifier de base de données
        $pdo = new PDO($dsn, $user, $pass, $options);
        $serverConnectTime = microtime(true) - $start;
        
        $result['details']['server_connection'] = [
            'success' => true,
            'time' => round($serverConnectTime * 1000, 2) . 'ms',
        ];
        
        // Maintenant, essayer de sélectionner la base de données
        $dbStart = microtime(true);
        try {
            $pdo->exec("USE `{$dbName}`");
            $dbSelectTime = microtime(true) - $dbStart;
            
            $result['details']['database_selection'] = [
                'success' => true,
                'time' => round($dbSelectTime * 1000, 2) . 'ms',
            ];
            
            // Vérifier la version de MySQL
            $versionStmt = $pdo->query('SELECT VERSION() as version');
            $versionInfo = $versionStmt->fetch(PDO::FETCH_ASSOC);
            $result['details']['server_version'] = $versionInfo['version'] ?? 'Inconnue';
            
            // Exécuter une requête simple pour tester le temps de réponse
            $testStart = microtime(true);
            $pdo->query('SELECT 1');
            $testTime = microtime(true) - $testStart;
            
            $result['details']['query_time'] = round($testTime * 1000, 2) . 'ms';
            
            $result['success'] = true;
        } catch (PDOException $e) {
            $result['details']['database_selection'] = [
                'success' => false,
                'error' => "Base de données '{$dbName}' inaccessible: " . $e->getMessage()
            ];
            $result['error'] = "Impossible de sélectionner la base de données: {$e->getMessage()}";
        }
    } catch (PDOException $e) {
        $result['error'] = "Échec de connexion au serveur MySQL: {$e->getMessage()}";
    } catch (Exception $e) {
        $result['error'] = "Erreur générale: {$e->getMessage()}";
    }
    
    return $result;
}

// Recherche de problèmes de sécurité courants dans les fichiers PHP
$securityIssues = [];

/**
 * Recherche les problèmes de sécurité dans un répertoire
 * @param string $dir Répertoire à analyser
 * @param array &$issues Tableau pour stocker les problèmes trouvés
 */
function scanDirectoryForSecurityIssues($dir, &$issues) {
    if (!is_dir($dir)) return;
    
    // Utiliser une liste d'exclusion pour les répertoires à ignorer
    $excludeDirs = ['vendor', 'node_modules', 'cache'];
    
    try {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        $patterns = [
            'eval' => [
                'pattern' => '/eval\s*\(/i',
                'severity' => 'high',
                'description' => "Usage d'eval() - Risque d'exécution de code arbitraire"
            ],
            'shell_exec' => [
                'pattern' => '/shell_exec\s*\(/i',
                'severity' => 'high',
                'description' => "Usage de shell_exec() - Risque d'exécution de commandes système"
            ],
            'exec' => [
                'pattern' => '/\bexec\s*\(/i',
                'severity' => 'high',
                'description' => "Usage d'exec() - Risque d'exécution de commandes système"
            ],
            'system' => [
                'pattern' => '/\bsystem\s*\(/i',
                'severity' => 'high',
                'description' => "Usage de system() - Risque d'exécution de commandes système"
            ],
            'include_user_input' => [
                'pattern' => '/include\s*\(\s*\$_/i',
                'severity' => 'high',
                'description' => "Include avec entrée utilisateur - Risque d'inclusion de fichier arbitraire"
            ],
            'require_user_input' => [
                'pattern' => '/require\s*\(\s*\$_/i',
                'severity' => 'high',
                'description' => "Require avec entrée utilisateur - Risque d'inclusion de fichier arbitraire"
            ],
            'sql_injection' => [
                'pattern' => '/\$sql\s*=.*\$_/i',
                'severity' => 'high',
                'description' => "Construction de requête SQL avec des entrées utilisateur - Risque d'injection SQL"
            ],
            'xss_output' => [
                'pattern' => '/echo\s+\$_(?!.*html)/i',
                'severity' => 'medium',
                'description' => "Affichage d'entrée utilisateur sans échappement - Risque XSS"
            ],
            'debug_info' => [
                'pattern' => '/var_dump\s*\(|print_r\s*\(/i',
                'severity' => 'low',
                'description' => "Affichage d'informations de débogage - Risque de fuite d'informations sensibles"
            ],
            'hardcoded_credentials' => [
                'pattern' => '/password\s*=\s*[\'"][^\'"]+[\'"]/i',
                'severity' => 'medium',
                'description' => "Informations d'identification en dur dans le code - Risque de sécurité"
            ],
        ];
        
        foreach ($iterator as $file) {
            // Ignorer les répertoires exclus
            if ($file->isDir() && in_array($file->getBasename(), $excludeDirs)) {
                continue;
            }
            
            if ($file->isFile() && $file->getExtension() == 'php') {
                $filePath = $file->getPathname();
                $content = file_get_contents($filePath);
                
                foreach ($patterns as $type => $pattern) {
                    if (preg_match($pattern['pattern'], $content, $matches)) {
                        $issues[] = [
                            'file' => sanitizePath($filePath),
                            'type' => $type,
                            'severity' => $pattern['severity'],
                            'description' => $pattern['description']
                        ];
                    }
                }
            }
        }
    } catch (\Exception $e) {
        // Logger l'erreur plutôt que d'échouer
        error_log("Erreur lors de l'analyse de sécurité: " . $e->getMessage());
    }
}

// Ne pas scanner si le système est trop lent ou si le scan a déjà été fait
$scanPerformed = false;
$maxScanTime = 5; // secondes maximum pour le scan

if (!isset($_SESSION['security_scan_done']) && !isset($_GET['skip_scan'])) {
    $startTime = microtime(true);
    set_time_limit(30); // Augmenter la limite de temps d'exécution
    
    try {
        // Limiter le scan à des répertoires spécifiques pour éviter de scanner tout
        $dirsToScan = [
            __DIR__ . '/API',
            __DIR__ . '/login',
            __DIR__ . '/notes'
        ];
        
        foreach ($dirsToScan as $dir) {
            if (is_dir($dir)) {
                scanDirectoryForSecurityIssues($dir, $securityIssues);
            }
            
            // Arrêter si ça prend trop de temps
            if ((microtime(true) - $startTime) > $maxScanTime) {
                $securityIssues[] = [
                    'file' => 'Scan incomplet',
                    'type' => 'timeout',
                    'severity' => 'info',
                    'description' => "Le scan a été interrompu car il prenait trop de temps."
                ];
                break;
            }
        }
        
        $scanPerformed = true;
        $_SESSION['security_scan_done'] = true;
        $_SESSION['security_scan_time'] = time();
    } catch (\Exception $e) {
        $securityIssues[] = [
            'file' => 'Erreur de scan',
            'type' => 'error',
            'severity' => 'error',
            'description' => "Erreur lors du scan: " . htmlspecialchars($e->getMessage())
        ];
    }
} else {
    // Vérifier si le scan n'est pas trop ancien (24 heures)
    if (isset($_SESSION['security_scan_time']) && time() - $_SESSION['security_scan_time'] > 86400) {
        unset($_SESSION['security_scan_done']);
        unset($_SESSION['security_scan_time']);
    }
}

// Vérifier les permissions du fichier install.php et install.lock
$installFile = __DIR__ . '/install.php';
$installLockFile = __DIR__ . '/install.lock';

$installStatus = [
    'install_exists' => file_exists($installFile),
    'lock_exists' => file_exists($installLockFile),
    'install_protected' => false
];

if ($installStatus['install_exists'] && $installStatus['lock_exists']) {
    // Vérifier si le fichier est protégé
    if (file_exists(__DIR__ . '/.htaccess')) {
        $htaccessContent = file_get_contents(__DIR__ . '/.htaccess');
        $installStatus['install_protected'] = 
            strpos($htaccessContent, 'install.php') !== false || 
            !is_readable($installFile);
    }
}

// Diagnostic avancé des permissions (ajout après la section permissions existante)
$advancedDiagnostic = [
    'php_user' => [
        'name' => get_current_user(),
        'uid' => getmyuid(),
        'gid' => getmygid(),
        'umask' => sprintf('%04o', umask())
    ],
    'current_directory' => [
        'path' => __DIR__,
        'owner' => 'unknown',
        'group' => 'unknown',
        'permissions' => 'unknown'
    ],
    'capabilities' => [
        'create_directory' => false,
        'write_file' => false
    ],
    'php_config' => [
        'open_basedir' => ini_get('open_basedir') ?: 'Non défini',
        'safe_mode' => ini_get('safe_mode') ? 'Activé' : 'Désactivé',
        'disabled_functions' => ini_get('disable_functions') ?: 'Aucune',
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ]
];

// Tests avancés des capacités système
if (function_exists('posix_getpwuid') && function_exists('stat')) {
    $stat = stat(__DIR__);
    $owner = posix_getpwuid($stat['uid']);
    $group = posix_getgrgid($stat['gid']);
    
    $advancedDiagnostic['current_directory']['owner'] = $owner ? $owner['name'] : "UID:{$stat['uid']}";
    $advancedDiagnostic['current_directory']['group'] = $group ? $group['name'] : "GID:{$stat['gid']}";
    $advancedDiagnostic['current_directory']['permissions'] = sprintf('%o', $stat['mode'] & 0777);
}

// Test de création de répertoire
$testDir = __DIR__ . '/test_diagnostic_' . uniqid();
$advancedDiagnostic['capabilities']['create_directory'] = @mkdir($testDir, 0755);
if ($advancedDiagnostic['capabilities']['create_directory']) {
    @rmdir($testDir);
}

// Test d'écriture de fichier
$testFile = __DIR__ . '/test_write_' . uniqid() . '.tmp';
$advancedDiagnostic['capabilities']['write_file'] = @file_put_contents($testFile, 'test') !== false;
if ($advancedDiagnostic['capabilities']['write_file']) {
    @unlink($testFile);
}

// Analyse détaillée des répertoires
$detailedDirectoryAnalysis = [];
foreach ($directories as $name => $path) {
    $analysis = [
        'exists' => is_dir($path),
        'readable' => false,
        'writable' => false,
        'permissions' => 'N/A',
        'permissions_string' => 'N/A',
        'owner' => 'N/A',
        'group' => 'N/A',
        'write_test' => false
    ];
    
    if ($analysis['exists']) {
        $analysis['readable'] = is_readable($path);
        $analysis['writable'] = is_writable($path);
        
        if (function_exists('stat') && function_exists('posix_getpwuid')) {
            $stat = stat($path);
            $owner = posix_getpwuid($stat['uid']);
            $group = posix_getgrgid($stat['gid']);
            
            $analysis['permissions'] = sprintf('%o', $stat['mode'] & 0777);
            $analysis['permissions_string'] = sprintf('%04o', $stat['mode'] & 0777);
            $analysis['owner'] = $owner ? $owner['name'] : "UID:{$stat['uid']}";
            $analysis['group'] = $group ? $group['name'] : "GID:{$stat['gid']}";
        }
        
        // Test d'écriture réel
        $testFile = $path . '/diagnostic_test_' . uniqid() . '.tmp';
        $analysis['write_test'] = @file_put_contents($testFile, 'diagnostic test') !== false;
        if ($analysis['write_test']) {
            @unlink($testFile);
        }
    }
    
    $detailedDirectoryAnalysis[$name] = $analysis;
}

// Traitement des actions de maintenance
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['diag_token'])) {
    if (!isset($_SESSION['diag_token']) || !hash_equals($_SESSION['diag_token'], $_POST['diag_token'])) {
        $error = "Token de sécurité invalide";
    } else {
        if (isset($_POST['create_missing_dirs'])) {
            $created = 0;
            foreach ($directories as $name => $path) {
                if (!is_dir($path)) {
                    if (@mkdir($path, 0755, true)) {
                        $created++;
                    }
                }
            }
            $message = "Répertoires créés: {$created}";
        }
        
        if (isset($_POST['fix_permissions'])) {
            $fixed = 0;
            foreach ($directories as $name => $path) {
                if (is_dir($path) && @chmod($path, 0755)) {
                    $fixed++;
                }
            }
            $message = "Permissions corrigées: {$fixed}";
        }
        
        if (isset($_POST['test_777_permissions'])) {
            $changed = 0;
            foreach ($directories as $name => $path) {
                if (is_dir($path) && @chmod($path, 0777)) {
                    $changed++;
                }
            }
            $message = "Permissions 777 appliquées (temporaire): {$changed}";
        }
        
        if (isset($_POST['clean_temp_files'])) {
            $cleaned = 0;
            $tempFiles = glob(__DIR__ . '/temp/*');
            foreach ($tempFiles as $file) {
                if (is_file($file) && @unlink($file)) {
                    $cleaned++;
                }
            }
            $message = "Fichiers temporaires nettoyés: {$cleaned}";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Pronote - Administration</title>
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        .section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .success {
            color: #2ecc71;
        }
        .warning {
            color: #e67e22;
        }
        .error {
            color: #e74c3c;
        }
        pre {
            background: #f8f8f8;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            overflow: auto;
            max-height: 400px;
            font-size: 13px;
            white-space: pre-wrap;
        }
        .actions {
            margin-top: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 15px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            border: none;
            cursor: pointer;
        }
        .button:hover {
            background: #2980b9;
        }
        .security-success {
            color: #2ecc71;
        }
        .security-warning {
            color: #f39c12;
        }
        .security-error {
            color: #e74c3c;
        }
        .copy-button {
            padding: 3px 8px;
            background: #7f8c8d;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        .copy-button:hover {
            background: #95a5a6;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
        }
        .tab.active {
            border-bottom: 3px solid #3498db;
            font-weight: bold;
        }
        .security-issue-high {
            background-color: #ffecec;
        }
        .security-issue-medium {
            background-color: #fff8e1;
        }
        .security-issue-low {
            background-color: #e3f2fd;
        }
        .security-issue-info {
            background-color: #f0f8ff;
        }
    </style>
</head>
<body>
    <div class="section">
        <h1>Diagnostic Pronote - Administration</h1>
        <p>Cette page est réservée aux administrateurs pour diagnostiquer les problèmes de configuration, de permissions et de redirections dans l'application Pronote.</p>
        <div class="tabs">
            <div class="tab active" data-tab="general">Général</div>
            <div class="tab" data-tab="permissions">Permissions</div>
            <div class="tab" data-tab="database">Base de données</div>
            <div class="tab" data-tab="security">Sécurité</div>
            <div class="tab" data-tab="config">Configuration</div>
        </div>
    </div>
    
    <div id="general" class="tab-content active">
        <div class="section">
            <h2>Informations de base</h2>
            <ul>
                <li><strong>Chemin d'application détecté:</strong> <?= htmlspecialchars(sanitizePath($applicationPath)) ?></li>
                <li><strong>Répertoire courant:</strong> <?= htmlspecialchars(sanitizePath(__DIR__)) ?></li>
                <li><strong>URL de base:</strong> <?= htmlspecialchars($baseUrl) ?></li>
                <li><strong>PHP Version:</strong> <?= htmlspecialchars(PHP_VERSION) ?></li>
                <li><strong>Serveur Web:</strong> <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu') ?></li>
                <li><strong>Date de diagnostic:</strong> <?= date('Y-m-d H:i:s') ?></li>
            </ul>
        </div>
        
        <div class="section">
            <h2>Accessibilité des pages</h2>
            <table>
                <tr>
                    <th>Page</th>
                    <th>Description</th>
                    <th>Code HTTP</th>
                    <th>Accessible</th>
                    <th>État</th>
                </tr>
                <?php 
                $accessibleCount = 0;
                $totalPages = count($pages);
                foreach ($pages as $name => $pageInfo):
                    $url = is_array($pageInfo) ? $pageInfo['url'] : $pageInfo;
                    $description = is_array($pageInfo) ? $pageInfo['description'] : '';
                    $access = testPageAccess($url);
                    if ($access['accessible']) $accessibleCount++;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($name) ?></td>
                        <td><?= htmlspecialchars($description) ?></td>
                        <td><?= $access['code'] ?></td>
                        <td class="<?= $access['accessible'] ? 'success' : 'error' ?>"><?= $access['accessible'] ? 'Oui' : 'Non' ?></td>
                        <td>
                            <?php if ($access['accessible']): ?>
                                <?php if (isset($access['warning'])): ?>
                                    <span class="warning"><?= htmlspecialchars($access['warning']) ?></span>
                                <?php else: ?>
                                    <span class="success">OK</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="error"><?= htmlspecialchars($access['error'] ?? 'Inaccessible') ?></span>
                            <?php endif; ?>
                            
                            <?php if ($access['redirect_count'] > 0): ?>
                                <br><small>Redirections: <?= $access['redirect_count'] ?></small>
                            <?php endif; ?>
                            
                            <?php if ($access['final_url'] !== $url): ?>
                                <br><small>URL finale: <?= htmlspecialchars($access['final_url']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            
            <div style="margin-top: 15px; padding: 10px; border-radius: 5px;" class="<?= $accessibleCount == $totalPages ? 'success' : 'warning' ?>">
                <p><strong>État général:</strong> <?= $accessibleCount ?>/<?= $totalPages ?> pages accessibles</p>
                
                <?php if ($accessibleCount < $totalPages): ?>
                    <h3>Causes possibles des problèmes d'accessibilité:</h3>
                    <ul>
                        <li>Vérifiez que le chemin de base <code><?= htmlspecialchars($baseUrl) ?></code> est correct</li>
                        <li>Assurez-vous que les fichiers existent aux emplacements spécifiés</li>
                        <li>Vérifiez que l'authentification fonctionne correctement</li>
                        <li>Examinez les journaux d'erreurs PHP/Apache pour plus de détails</li>
                    </ul>
                    <button class="button" onclick="window.location.href='API/tools/path_debug.php'">Exécuter l'outil de diagnostic de chemins</button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="section">
            <h2>Variables du serveur</h2>
            <pre><?= htmlspecialchars(print_r([
                'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'Non défini',
                'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'Non défini',
                'DOCUMENT_ROOT' => sanitizePath($_SERVER['DOCUMENT_ROOT'] ?? 'Non défini'),
                'SCRIPT_FILENAME' => sanitizePath($_SERVER['SCRIPT_FILENAME'] ?? 'Non défini'),
                'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'Non défini',
                'PHP_SAPI' => PHP_SAPI,
            ], true)) ?></pre>
        </div>
        
        <div class="section">
            <h2>État de l'installation</h2>
            <table>
                <tr>
                    <th>Élément</th>
                    <th>État</th>
                </tr>
                <tr>
                    <td>Fichier d'installation</td>
                    <td class="<?= $installStatus['install_exists'] ? 'warning' : 'success' ?>">
                        <?= $installStatus['install_exists'] ? 'Présent' : 'Non présent (OK)' ?>
                    </td>
                </tr>
                <tr>
                    <td>Fichier de verrouillage</td>
                    <td class="<?= $installStatus['lock_exists'] ? 'success' : 'warning' ?>">
                        <?= $installStatus['lock_exists'] ? 'Présent (OK)' : 'Non présent' ?>
                    </td>
                </tr>
                <tr>
                    <td>Protection du fichier d'installation</td>
                    <td class="<?= ($installStatus['install_exists'] && $installStatus['install_protected']) ? 'success' : 
                                  ($installStatus['install_exists'] ? 'error' : 'success') ?>">
                        <?php if (!$installStatus['install_exists']): ?>
                            Non applicable
                        <?php elseif ($installStatus['install_protected']): ?>
                            Protégé (OK)
                        <?php else: ?>
                            Non protégé (Risque de sécurité)
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <div id="permissions" class="tab-content">
        <div class="section">
            <h2>Diagnostic avancé des permissions</h2>
            
            <h3>Informations système</h3>
            <table>
                <tr>
                    <th>Élément</th>
                    <th>Valeur</th>
                </tr>
                <tr>
                    <td>Utilisateur PHP</td>
                    <td><?= htmlspecialchars($advancedDiagnostic['php_user']['name']) ?> (UID: <?= $advancedDiagnostic['php_user']['uid'] ?>)</td>
                </tr>
                <tr>
                    <td>Groupe PHP</td>
                    <td>GID: <?= $advancedDiagnostic['php_user']['gid'] ?></td>
                </tr>
                <tr>
                    <td>Umask</td>
                    <td><?= $advancedDiagnostic['php_user']['umask'] ?></td>
                </tr>
                <tr>
                    <td>Répertoire courant</td>
                    <td><?= htmlspecialchars(sanitizePath($advancedDiagnostic['current_directory']['path'])) ?></td>
                </tr>
                <tr>
                    <td>Propriétaire du répertoire racine</td>
                    <td><?= $advancedDiagnostic['current_directory']['owner'] ?></td>
                </tr>
                <tr>
                    <td>Groupe du répertoire racine</td>
                    <td><?= $advancedDiagnostic['current_directory']['group'] ?></td>
                </tr>
                <tr>
                    <td>Permissions répertoire racine</td>
                    <td><?= $advancedDiagnostic['current_directory']['permissions'] ?></td>
                </tr>
            </table>
            
            <h3>Capacités de base</h3>
            <table>
                <tr>
                    <th>Test</th>
                    <th>Résultat</th>
                </tr>
                <tr>
                    <td>Créer un répertoire</td>
                    <td class="<?= $advancedDiagnostic['capabilities']['create_directory'] ? 'success' : 'error' ?>">
                        <?= $advancedDiagnostic['capabilities']['create_directory'] ? 'Succès' : 'Échec' ?>
                    </td>
                </tr>
                <tr>
                    <td>Écrire un fichier</td>
                    <td class="<?= $advancedDiagnostic['capabilities']['write_file'] ? 'success' : 'error' ?>">
                        <?= $advancedDiagnostic['capabilities']['write_file'] ? 'Succès' : 'Échec' ?>
                    </td>
                </tr>
            </table>
            
            <h3>Configuration PHP affectant les permissions</h3>
            <table>
                <tr>
                    <th>Directive</th>
                    <th>Valeur</th>
                </tr>
                <?php foreach ($advancedDiagnostic['php_config'] as $key => $value): ?>
                <tr>
                    <td><?= htmlspecialchars($key) ?></td>
                    <td><?= htmlspecialchars($value) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="section">
            <h2>Analyse détaillée des répertoires</h2>
            <table>
                <tr>
                    <th>Répertoire</th>
                    <th>Existe</th>
                    <th>Permissions</th>
                    <th>Détail</th>
                    <th>Propriétaire</th>
                    <th>Groupe</th>
                    <th>Test écriture</th>
                    <th>Diagnostic</th>
                </tr>
                <?php foreach ($detailedDirectoryAnalysis as $name => $analysis): ?>
                    <tr>
                        <td><?= htmlspecialchars($name) ?></td>
                        <td class="<?= $analysis['exists'] ? 'success' : 'error' ?>">
                            <?= $analysis['exists'] ? 'Oui' : 'Non' ?>
                        </td>
                        <td>
                            <?= $analysis['exists'] ? $analysis['permissions'] : 'N/A' ?>
                        </td>
                        <td>
                            <?= $analysis['exists'] ? $analysis['permissions_string'] : 'N/A' ?>
                        </td>
                        <td>
                            <?= $analysis['exists'] ? $analysis['owner'] : 'N/A' ?>
                        </td>
                        <td>
                            <?= $analysis['exists'] ? $analysis['group'] : 'N/A' ?>
                        </td>
                        <td class="<?= isset($analysis['write_test']) && $analysis['write_test'] ? 'success' : 'error' ?>">
                            <?= isset($analysis['write_test']) && $analysis['write_test'] ? 'Succès' : 'Échec' ?>
                        </td>
                        <td>
                            <?php if (!$analysis['exists']): ?>
                                <span class="error">Répertoire manquant</span>
                            <?php elseif (!$analysis['writable']): ?>
                                <span class="error">Pas d'écriture PHP</span>
                            <?php elseif (!$analysis['write_test']): ?>
                                <span class="error">Test écriture échoué</span>
                            <?php else: ?>
                                <span class="success">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Solutions recommandées</h2>
            
            <?php
            $hasWriteIssues = false;
            $hasOwnershipIssues = false;
            $currentUser = $advancedDiagnostic['php_user']['uid'];
            $currentDir = $advancedDiagnostic['current_directory'];
            
            foreach ($detailedDirectoryAnalysis as $analysis) {
                if ($analysis['exists'] && !$analysis['write_test']) {
                    $hasWriteIssues = true;
                    if ($analysis['owner'] !== $currentUser) {
                        $hasOwnershipIssues = true;
                    }
                }
            }
            ?>
            
            <?php if ($hasWriteIssues): ?>
                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                    <h3>⚠️ Problèmes d'écriture détectés</h3>
                    
                    <?php if ($hasOwnershipIssues): ?>
                        <h4>Problème de propriétaire</h4>
                        <p>Les répertoires appartiennent à un utilisateur différent de celui qui exécute PHP.</p>
                        <p><strong>Solutions (exécutez via SSH) :</strong></p>
                        <pre><?php
echo "cd " . __DIR__ . "\n\n";
echo "# Option 1 - Changer le propriétaire (serveur dédié/VPS)\n";
foreach ($directories as $name => $path) {
    echo "chown " . $advancedDiagnostic['php_user']['name'] . ":" . $advancedDiagnostic['php_user']['name'] . " " . basename($path) . "\n";
}
echo "\n# Option 2 - Utiliser www-data (Apache/Ubuntu)\n";
foreach ($directories as $name => $path) {
    echo "sudo chown www-data:www-data " . basename($path) . "\n";
}
echo "\n# Option 3 - Utiliser nginx (Nginx)\n";
foreach ($directories as $name => $path) {
    echo "sudo chown nginx:nginx " . basename($path) . "\n";
}
                        ?></pre>
                    <?php else: ?>
                        <h4>Problème de permissions</h4>
                        <p>Le propriétaire est correct mais les permissions sont insuffisantes.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <h3>🔧 Actions automatiques disponibles</h3>
                <form method="post" action="">
                    <input type="hidden" name="diag_token" value="<?= htmlspecialchars($diagToken) ?>">
                    <button type="submit" name="create_missing_dirs" class="button">Créer les répertoires manquants</button>
                    <button type="submit" name="fix_permissions" class="button">Corriger permissions (755)</button>
                    <button type="submit" name="test_777_permissions" class="button" style="background: #e67e22;">Test permissions 777 (temporaire)</button>
                    <button type="submit" name="clean_temp_files" class="button">Nettoyer fichiers temporaires</button>
                </form>
                
                <p><strong>Note :</strong> Si les actions automatiques échouent, utilisez les commandes SSH ci-dessous.</p>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                <h3>📋 Commandes SSH complètes</h3>
                <p>Copiez et exécutez ces commandes via SSH :</p>
                <pre><?php
echo "# Naviguer vers le répertoire de l'application\n";
echo "cd " . __DIR__ . "\n\n";

echo "# Créer tous les répertoires manquants\n";
foreach ($directories as $name => $path) {
    echo "mkdir -p " . basename($path) . "\n";
}

echo "\n# Méthode 1 - Permissions standard (755)\n";
foreach ($directories as $name => $path) {
    echo "chmod 755 " . basename($path) . "\n";
}

echo "\n# Méthode 2 - Permissions complètes (777) - TEMPORAIRE SEULEMENT\n";
foreach ($directories as $name => $path) {
    echo "chmod 777 " . basename($path) . "\n";
}

echo "\n# Méthode 3 - Ajuster le propriétaire (serveur Apache)\n";
foreach ($directories as $name => $path) {
    echo "sudo chown www-data:www-data " . basename($path) . "\n";
}
echo "sudo chmod 755 " . implode(' ', array_map('basename', array_values($directories))) . "\n";

echo "\n# Méthode 4 - Serveur Nginx\n";
foreach ($directories as $name => $path) {
    echo "sudo chown nginx:nginx " . basename($path) . "\n";
}

echo "\n# Méthode 5 - Serveur partagé\n";
foreach ($directories as $name => $path) {
    echo "chown " . $advancedDiagnostic['php_user']['name'] . " " . basename($path) . "\n";
}

echo "\n# Vérifier les permissions après correction\n";
echo "ls -la " . implode(' ', array_map('basename', array_values($directories))) . "\n";
                ?></pre>
                <button class="copy-button" onclick="copyToClipboard(this.previousElementSibling)">Copier</button>
            </div>
        </div>

        <div class="section">
            <h2>Actions de maintenance</h2>
            <?php if (isset($message)): ?>
                <div style="padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div style="padding: 10px; background: #f8d7da; color: #721c24; border-radius: 5px; margin-bottom: 15px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <h3>Outils de diagnostic avancés</h3>
            <p>Les anciens scripts de diagnostic ont été intégrés dans cette page :</p>
            <ul>
                <li><strong>Santé de la base de données</strong> : Voir l'onglet "Base de données"</li>
                <li><strong>Test de permissions</strong> : Utilisez le diagnostic ci-dessus</li>
                <li><strong>Test de connexion DB</strong> : Voir l'onglet "Base de données"</li>
                <li><strong>Correction complète</strong> : Relancez l'installation si nécessaire</li>
            </ul>
        </div>
    </div>
    
    <div id="database" class="tab-content">
        <div class="section">
            <h2>État de la connexion à la base de données</h2>
            <p class="<?= $dbStatus['connected'] ? 'success' : 'error' ?>">
                <?= $dbStatus['connected'] ? 'Connecté' : 'Non connecté' ?>
                <?= isset($dbStatus['error']) ? ' - ' . htmlspecialchars($dbStatus['error']) : '' ?>
            </p>
            
            <?php if (!empty($dbTest['details'])): ?>
            <h3>Détails de la connexion</h3>
            <table>
                <tr>
                    <th>Étape</th>
                    <th>Résultat</th>
                    <th>Temps</th>
                </tr>
                <?php if (isset($dbTest['details']['server_connection'])): ?>
                <tr>
                    <td>Connexion au serveur</td>
                    <td class="<?= $dbTest['details']['server_connection']['success'] ? 'success' : 'error' ?>">
                        <?= $dbTest['details']['server_connection']['success'] ? 'OK' : 'Échec' ?>
                    </td>
                    <td><?= $dbTest['details']['server_connection']['time'] ?? 'N/A' ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if (isset($dbTest['details']['database_selection'])): ?>
                <tr>
                    <td>Sélection de la base de données</td>
                    <td class="<?= $dbTest['details']['database_selection']['success'] ? 'success' : 'error' ?>">
                        <?= $dbTest['details']['database_selection']['success'] ? 'OK' : 'Échec' ?>
                        <?= isset($dbTest['details']['database_selection']['error']) ? ' - ' . htmlspecialchars($dbTest['details']['database_selection']['error']) : '' ?>
                    </td>
                    <td><?= $dbTest['details']['database_selection']['time'] ?? 'N/A' ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if (isset($dbTest['details']['query_time'])): ?>
                <tr>
                    <td>Temps de requête test</td>
                    <td class="success">OK</td>
                    <td><?= $dbTest['details']['query_time'] ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if (isset($dbTest['details']['server_version'])): ?>
                <tr>
                    <td>Version du serveur</td>
                    <td colspan="2"><?= htmlspecialchars($dbTest['details']['server_version']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>
        </div>
        
        <?php if ($dbStatus['connected'] && isset($dbStatus['tables'])): ?>
            <div class="section">
                <h2>Structure de la base de données</h2>
                <table>
                    <tr>
                        <th>Table</th>
                        <th>Existe</th>
                        <th>Nombre d'enregistrements</th>
                        <th>Colonnes</th>
                        <th>Statut</th>
                    </tr>
                    <?php foreach ($dbStatus['tables'] as $table => $status): ?>
                        <tr>
                            <td><?= htmlspecialchars($table) ?></td>
                            <td class="<?= $status['exists'] ? 'success' : 'error' ?>">
                                <?= $status['exists'] ? 'Oui' : 'Non' ?>
                            </td>
                            <td><?= htmlspecialchars($status['count']) ?></td>
                            <td>
                                <?php if ($status['exists'] && isset($status['columns_count'])): ?>
                                    <?= $status['columns_count'] ?> colonnes
                                    <button class="copy-button" onclick="toggleColumns('<?= $table ?>')">Afficher/Masquer</button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$status['exists']): ?>
                                    <span class="error">Table manquante</span>
                                <?php elseif ((int)$status['columns_count'] < 3): ?>
                                    <span class="warning">Structure incomplète</span>
                                <?php else: ?>
                                    <span class="success">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($status['exists'] && isset($status['columns'])): ?>
                        <tr id="columns-<?= $table ?>" style="display: none;">
                            <td colspan="5">
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <table style="width: 100%;">
                                        <tr>
                                            <th>Nom</th>
                                            <th>Type</th>
                                            <th>Clé</th>
                                        </tr>
                                        <?php foreach ($status['columns'] as $col): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($col['name']) ?></td>
                                            <td><?= htmlspecialchars($col['type']) ?></td>
                                            <td><?= htmlspecialchars($col['key']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
                
                <?php if (!empty($dbStatus['missing_indexes'])): ?>
                <h3>Index manquants recommandés</h3>
                <ul>
                    <?php foreach ($dbStatus['missing_indexes'] as $index): ?>
                    <li class="warning"><?= htmlspecialchars($index) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                
                <h3>Actions recommandées</h3>
                <?php if (count(array_filter($dbStatus['tables'], function($t) { return !$t['exists']; })) > 0): ?>
                    <p class="error">Certaines tables essentielles sont manquantes. Vous devriez réinstaller la base de données.</p>
                    <button class="button" onclick="if(confirm('Êtes-vous sûr de vouloir créer les tables manquantes?')) { window.location.href='API/tools/rebuild_db.php'; }">Créer les tables manquantes</button>
                <?php elseif (!empty($dbStatus['missing_indexes'])): ?>
                    <p class="warning">Des index recommandés sont manquants. Vous devriez les ajouter pour améliorer les performances.</p>
                    <button class="button" onclick="if(confirm('Êtes-vous sûr de vouloir ajouter les index manquants?')) { window.location.href='API/tools/add_indexes.php'; }">Ajouter les index manquants</button>
                <?php else: ?>
                    <p class="success">La structure de la base de données semble correcte.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="security" class="tab-content">
        <div class="section">
            <h2>Configuration de sécurité PHP</h2>
            <table>
                <tr>
                    <th>Directive</th>
                    <th>Valeur actuelle</th>
                    <th>Recommandation</th>
                    <th>État</th>
                </tr>
                <?php foreach ($phpSecurity as $name => $config): ?>
                    <tr>
                        <td><?= htmlspecialchars($config['name']) ?></td>
                        <td><?= htmlspecialchars($config['value']) ?></td>
                        <td><?= htmlspecialchars($config['recommendation']) ?></td>
                        <td class="security-<?= htmlspecialchars($config['status']) ?>">
                            <?= $config['status'] === 'success' ? 'OK' : ($config['status'] === 'warning' ? 'Attention' : 'Problème') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="section">
            <h2>Mises à jour de sécurité</h2>
            <table>
                <tr>
                    <th>Élément</th>
                    <th>État actuel</th>
                    <th>Recommandation</th>
                    <th>Statut</th>
                </tr>
                <?php foreach ($securityUpdates as $update): ?>
                    <tr>
                        <td><?= htmlspecialchars($update['name']) ?></td>
                        <td><?= htmlspecialchars($update['value']) ?></td>
                        <td><?= htmlspecialchars($update['recommendation']) ?></td>
                        <td class="security-<?= htmlspecialchars($update['status']) ?>">
                            <?= $update['status'] === 'success' ? 'OK' : ($update['status'] === 'warning' ? 'Attention' : 'Critique') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <?php if (!empty($securityIssues)): ?>
            <div class="section">
                <h2>Problèmes de sécurité détectés</h2>
                <table>
                    <tr>
                        <th>Fichier</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Sévérité</th>
                    </tr>
                    <?php foreach ($securityIssues as $issue): ?>
                        <tr class="security-issue-<?= htmlspecialchars($issue['severity'] ?? 'low') ?>">
                            <td><?= htmlspecialchars($issue['file']) ?></td>
                            <td><?= htmlspecialchars($issue['type']) ?></td>
                            <td><?= htmlspecialchars($issue['description']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($issue['severity'] ?? 'inconnu')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p>
                    <?php if ($scanPerformed): ?>
                        Scan de sécurité effectué avec succès.
                    <?php else: ?>
                        <a href="?skip_scan=0" class="button">Relancer le scan de sécurité</a>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="config" class="tab-content">
        <div class="section">
            <h2>Fichiers de configuration</h2>
            <?php foreach ($configContents as $name => $content): ?>
                <h3><?= htmlspecialchars($name) ?></h3>
                <pre><?= htmlspecialchars($content) ?></pre>
            <?php endforeach; ?>
        </div>
        
        <div class="section">
            <h2>Informations de session</h2>
            <pre><?= htmlspecialchars(print_r($sessionInfo, true)) ?></pre>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion des onglets
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(tc => tc.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Protection CSRF pour les formulaires
        document.querySelectorAll('form').forEach(form => {
            if (!form.querySelector('input[name="diag_token"]')) {
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'diag_token';
                tokenInput.value = '<?= htmlspecialchars($diagToken) ?>';
                form.appendChild(tokenInput);
            }
        });
    });

    // Fonction pour afficher/masquer les colonnes des tables
    function toggleColumns(tableName) {
        const columnsRow = document.getElementById('columns-' + tableName);
        if (columnsRow.style.display === 'none') {
            columnsRow.style.display = 'table-row';
        } else {
            columnsRow.style.display = 'none';
        }
    }
    
    // Fonction pour copier dans le presse-papier
    function copyToClipboard(element) {
        const text = element.textContent;
        navigator.clipboard.writeText(text).then(function() {
            alert('Commandes copiées dans le presse-papier !');
        }, function() {
            // Fallback pour les navigateurs plus anciens
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('Commandes copiées dans le presse-papier !');
        });
    }
    </script>
</body>
</html>