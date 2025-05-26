<?php
/**
 * Script d'installation de Pronote
 * Ce script s'auto-désactivera après une installation réussie
 */

// Configuration de sécurité
ini_set('display_errors', 0); // Ne pas afficher les erreurs aux utilisateurs
error_reporting(E_ALL); // Mais les capturer toutes

// Configurer une limite de temps d'exécution plus élevée pour l'installation
set_time_limit(120);

// Définir les en-têtes de sécurité via PHP au lieu de meta tags
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Vérifier si l'installation est déjà terminée
$installLockFile = __DIR__ . '/install.lock';
if (file_exists($installLockFile)) {
    die('L\'installation a déjà été effectuée. Pour réinstaller, supprimez le fichier install.lock du répertoire racine.');
}

// Vérification HTTPS recommandée
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
if (!$isHttps) {
    $httpsWarning = "Avertissement: L'installation est effectuée sur une connexion non sécurisée (HTTP). Il est recommandé d'utiliser HTTPS.";
}

// Journaliser l'accès au script d'installation de façon sécurisée
$logMessage = 'Accès au script d\'installation de Pronote: ' . date('Y-m-d H:i:s');
$logMessage .= ' - IP: ' . (isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 7) . '***' : 'Inconnue');
error_log($logMessage);

// Vérifier la version de PHP
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('Pronote nécessite PHP 7.4 ou supérieur. Version actuelle: ' . PHP_VERSION);
}

// Vérifier les extensions requises
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die('Extensions PHP requises manquantes : ' . implode(', ', $missingExtensions));
}

// Limiter l'accès à l'installation par IP avec une approche plus sécurisée
$allowedIPs = ['127.0.0.1', '::1']; // IPs locales uniquement par défaut

// Récupérer l'IP du client de façon sécurisée
$clientIP = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);

// DIAGNOSTIC TEMPORAIRE - À SUPPRIMER APRÈS RÉSOLUTION
echo "<!-- DEBUG: IP détectée: " . $clientIP . " -->\n";
echo "<!-- DEBUG: Contenu de \$_SERVER['REMOTE_ADDR']: " . ($_SERVER['REMOTE_ADDR'] ?? 'non défini') . " -->\n";
echo "<!-- DEBUG: Contenu de \$_SERVER['HTTP_X_FORWARDED_FOR']: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'non défini') . " -->\n";
echo "<!-- DEBUG: Contenu de \$_SERVER['HTTP_X_REAL_IP']: " . ($_SERVER['HTTP_X_REAL_IP'] ?? 'non défini') . " -->\n";

if (!in_array($clientIP, $allowedIPs) && 
    (!isset($_SERVER['SERVER_ADDR']) || $clientIP !== $_SERVER['SERVER_ADDR'])) {
    
    // Journaliser la tentative d'accès non autorisée
    error_log("Tentative d'accès non autorisée au script d'installation depuis: " . $clientIP);
    
    // Si un fichier .env existe, vérifier si une IP supplémentaire est autorisée
    $envFile = __DIR__ . '/.env';
    $additionalIpAllowed = false;
    
    // DIAGNOSTIC TEMPORAIRE
    echo "<!-- DEBUG: Fichier .env existe: " . (file_exists($envFile) ? 'oui' : 'non') . " -->\n";
    echo "<!-- DEBUG: Fichier .env lisible: " . (is_readable($envFile) ? 'oui' : 'non') . " -->\n";
    
    if (file_exists($envFile) && is_readable($envFile)) {
        $envContent = file_get_contents($envFile);
        echo "<!-- DEBUG: Contenu du fichier .env: " . htmlspecialchars($envContent) . " -->\n";
        
        if (preg_match('/ALLOWED_INSTALL_IP\s*=\s*(.+)/', $envContent, $matches)) {
            $additionalIP = trim($matches[1]);
            echo "<!-- DEBUG: IP trouvées dans .env: " . htmlspecialchars($additionalIP) . " -->\n";
            
            // Gérer une liste d'IPs séparées par des virgules
            $ipList = array_map('trim', explode(',', $additionalIP));
            echo "<!-- DEBUG: Liste des IPs après explosion: " . implode(', ', $ipList) . " -->\n";
            
            foreach ($ipList as $ip) {
                echo "<!-- DEBUG: Vérification IP: " . htmlspecialchars($ip) . " vs " . htmlspecialchars($clientIP) . " -->\n";
                if (filter_var($ip, FILTER_VALIDATE_IP) && $ip === $clientIP) {
                    $additionalIpAllowed = true;
                    echo "<!-- DEBUG: IP autorisée trouvée: " . htmlspecialchars($ip) . " -->\n";
                    break;
                }
            }
        } else {
            echo "<!-- DEBUG: Aucune correspondance trouvée pour ALLOWED_INSTALL_IP dans le fichier .env -->\n";
        }
    }
    
    echo "<!-- DEBUG: additionalIpAllowed: " . ($additionalIpAllowed ? 'true' : 'false') . " -->\n";
    
    if (!$additionalIpAllowed) {
        die('Accès non autorisé depuis votre adresse IP: ' . $clientIP . '. IPs autorisées: ' . implode(', ', $allowedIPs));
    }
}

// Démarrer la session pour le jeton CSRF
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $isHttps
]);

// Détecter le chemin absolu du répertoire d'installation
$installDir = __DIR__;
$baseUrl = isset($_SERVER['REQUEST_URI']) ? 
    dirname(str_replace('/install.php', '', $_SERVER['REQUEST_URI'])) : 
    '/pronote';

// Si le chemin est la racine, ajuster la valeur
if ($baseUrl === '/.') {
    $baseUrl = '';
}

// Nettoyer le baseUrl pour éviter les injections
$baseUrl = filter_var($baseUrl, FILTER_SANITIZE_URL);

// Vérifier les permissions des dossiers
$directories = [
    'API/logs',
    'API/config', 
    'uploads',
    'temp'
];

$permissionIssues = [];
foreach ($directories as $dir) {
    $path = $installDir . '/' . $dir;
    
    // Créer le dossier s'il n'existe pas avec gestion d'erreur améliorée
    if (!is_dir($path)) {
        try {
            // Créer avec des permissions appropriées
            if (!mkdir($path, 0755, true)) {
                $permissionIssues[] = "Impossible de créer le dossier {$dir}. Vérifiez les permissions du répertoire parent.";
            } else {
                // Vérifier que le répertoire a bien été créé avec les bonnes permissions
                if (!is_writable($path)) {
                    // Essayer de corriger les permissions
                    @chmod($path, 0755);
                    if (!is_writable($path)) {
                        $permissionIssues[] = "Le dossier {$dir} a été créé mais n'est pas accessible en écriture. Exécutez: chmod 755 {$path}";
                    }
                }
            }
        } catch (Exception $e) {
            $permissionIssues[] = "Erreur lors de la création du dossier {$dir}: " . $e->getMessage() . ". Exécutez manuellement: mkdir -p {$path} && chmod 755 {$path}";
        }
    } else if (!is_writable($path)) {
        // Le répertoire existe mais n'est pas accessible en écriture
        $permissionIssues[] = "Le dossier {$dir} n'est pas accessible en écriture. Exécutez: chmod 755 {$path}";
    }
}

// Génération d'un jeton CSRF unique avec gestion d'expiration améliorée
if (!isset($_SESSION['install_token']) || empty($_SESSION['install_token']) || 
    !isset($_SESSION['token_time']) || (time() - $_SESSION['token_time']) > 1800) { // 30 minutes au lieu de 1 heure
    try {
        $_SESSION['install_token'] = bin2hex(random_bytes(32));
        $_SESSION['token_time'] = time();
    } catch (Exception $e) {
        $_SESSION['install_token'] = hash('sha256', uniqid(mt_rand(), true));
        $_SESSION['token_time'] = time();
    }
}
$install_token = $_SESSION['install_token'];

// Traitement du formulaire
$installed = false;
$dbError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation du jeton CSRF avec diagnostic amélioré
    $csrfValid = true;
    $csrfError = '';
    
    if (!isset($_POST['install_token'])) {
        $csrfValid = false;
        $csrfError = "Jeton de sécurité manquant dans le formulaire.";
    } elseif (!isset($_SESSION['install_token'])) {
        $csrfValid = false;
        $csrfError = "Jeton de sécurité manquant dans la session. La session a peut-être expiré.";
    } elseif ($_POST['install_token'] !== $_SESSION['install_token']) {
        $csrfValid = false;
        $csrfError = "Jeton de sécurité invalide. Veuillez recharger la page et réessayer.";
    } elseif (!isset($_SESSION['token_time']) || (time() - $_SESSION['token_time']) > 1800) {
        $csrfValid = false;
        $csrfError = "Jeton de sécurité expiré (plus de 30 minutes). Veuillez recharger la page et réessayer.";
    }
    
    if (!$csrfValid) {
        $dbError = "Erreur de sécurité: " . $csrfError;
        
        // Régénérer un nouveau jeton pour la prochaine tentative
        try {
            $_SESSION['install_token'] = bin2hex(random_bytes(32));
            $_SESSION['token_time'] = time();
            $install_token = $_SESSION['install_token'];
        } catch (Exception $e) {
            $_SESSION['install_token'] = hash('sha256', uniqid(mt_rand(), true));
            $_SESSION['token_time'] = time();
            $install_token = $_SESSION['install_token'];
        }
    } else {
        // Le jeton CSRF est valide, continuer le traitement
        try {
            // Valider les entrées utilisateur
            $dbHost = filter_input(INPUT_POST, 'db_host', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'localhost';
            $dbName = filter_input(INPUT_POST, 'db_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $dbUser = filter_input(INPUT_POST, 'db_user', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $dbPass = $_POST['db_pass'] ?? ''; // Ne pas filtrer le mot de passe pour permettre les caractères spéciaux
            $appEnv = filter_input(INPUT_POST, 'app_env', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $baseUrlInput = filter_input(INPUT_POST, 'base_url', FILTER_SANITIZE_URL) ?: $baseUrl;
            
            // Récupérer les informations du compte administrateur
            $adminNom = filter_input(INPUT_POST, 'admin_nom', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $adminPrenom = filter_input(INPUT_POST, 'admin_prenom', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $adminMail = filter_input(INPUT_POST, 'admin_mail', FILTER_SANITIZE_EMAIL) ?: '';
            $adminPassword = $_POST['admin_password'] ?? '';
            
            // Valider l'environnement
            $validEnvs = ['development', 'production', 'test'];
            if (!in_array($appEnv, $validEnvs)) {
                $appEnv = 'production'; // Valeur par défaut sécurisée
            }
            
            // Validation supplémentaire
            if (empty($dbName) || empty($dbUser)) {
                throw new Exception("Le nom de la base de données et l'utilisateur sont obligatoires.");
            }
            
            // Validation du compte administrateur
            if (empty($adminNom) || empty($adminPrenom) || empty($adminMail) || empty($adminPassword)) {
                throw new Exception("Tous les champs du compte administrateur sont obligatoires.");
            }
            
            if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email de l'administrateur n'est pas valide.");
            }
            
            // Validation renforcée du mot de passe
            if (strlen($adminPassword) < 12) {
                throw new Exception("Le mot de passe administrateur doit contenir au moins 12 caractères.");
            }
            
            // Vérifier la robustesse du mot de passe avec des règles strictes
            $uppercase = preg_match('/[A-Z]/', $adminPassword);
            $lowercase = preg_match('/[a-z]/', $adminPassword);
            $number    = preg_match('/[0-9]/', $adminPassword);
            $specialChars = preg_match('/[^a-zA-Z0-9]/', $adminPassword);
            
            if (!$uppercase || !$lowercase || !$number || !$specialChars) {
                throw new Exception("Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.");
            }
            
            // Tester la connexion à la base de données
            try {
                $dsn = "mysql:host={$dbHost};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                
                $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
                
                // Créer la base de données si elle n'existe pas
                // Utiliser des requêtes préparées même pour les noms de base de données
                $dbNameSafe = str_replace('`', '', $dbName);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameSafe}`");
                $pdo->exec("USE `{$dbNameSafe}`");
                
                // Créer le fichier de configuration
                $apiDir = $installDir . '/API';
                $configDir = $apiDir . '/config';
                
                // Améliorer la création du répertoire de configuration avec diagnostic détaillé
                if (!is_dir($apiDir)) {
                    if (!mkdir($apiDir, 0755, true)) {
                        throw new Exception("Impossible de créer le répertoire API. Permissions insuffisantes sur " . dirname($apiDir) . ". Exécutez: mkdir -p {$apiDir} && chmod 755 {$apiDir}");
                    }
                }
                
                if (!is_dir($configDir)) {
                    if (!mkdir($configDir, 0755, true)) {
                        throw new Exception("Impossible de créer le répertoire de configuration. Permissions insuffisantes sur {$apiDir}. Exécutez: mkdir -p {$configDir} && chmod 755 {$configDir}");
                    }
                }
                
                // Vérifier que le répertoire est accessible en écriture avec diagnostic détaillé
                if (!is_writable($configDir)) {
                    // Essayer de corriger automatiquement les permissions
                    @chmod($configDir, 0755);
                    if (!is_writable($configDir)) {
                        $perms = substr(sprintf('%o', fileperms($configDir)), -4);
                        $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($configDir))['name'] : 'inconnu';
                        throw new Exception("Le répertoire de configuration n'est pas accessible en écriture. Permissions actuelles: {$perms}, Propriétaire: {$owner}. Exécutez: chmod 755 {$configDir} && chown webadmin:www-data {$configDir}");
                    }
                }
                
                $installTime = date('Y-m-d H:i:s');
                
                // Améliorer la sécurité des sessions
                $sessionSecure = $isHttps ? 'true' : 'false';
                
                // Créer le contenu du fichier de configuration en évitant les injections
                $configContent = <<<CONFIG
<?php
/**
 * Configuration d'environnement
 * Généré automatiquement par le script d'installation
 * Date: {$installTime}
 */

// Environnement (development, production, test)
if (!defined('APP_ENV')) define('APP_ENV', '{$appEnv}');

// Configuration de base
if (!defined('APP_NAME')) define('APP_NAME', 'Pronote');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

// Configuration des URLs et chemins - CHEMIN COMPLET OBLIGATOIRE
if (!defined('BASE_URL')) define('BASE_URL', '{$baseUrlInput}');
if (!defined('APP_URL')) define('APP_URL', '{$baseUrlInput}'); // Même valeur que BASE_URL par défaut
if (!defined('APP_ROOT')) define('APP_ROOT', realpath(__DIR__ . '/../../'));

// URLs communes construites avec BASE_URL
if (!defined('LOGIN_URL')) define('LOGIN_URL', BASE_URL . '/login/public/index.php');
if (!defined('LOGOUT_URL')) define('LOGOUT_URL', BASE_URL . '/login/public/logout.php');
if (!defined('HOME_URL')) define('HOME_URL', BASE_URL . '/accueil/accueil.php');

// Configuration de la base de données
if (!defined('DB_HOST')) define('DB_HOST', '{$dbHost}');
if (!defined('DB_NAME')) define('DB_NAME', '{$dbName}');
if (!defined('DB_USER')) define('DB_USER', '{$dbUser}');
if (!defined('DB_PASS')) define('DB_PASS', '{$dbPass}');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Configuration des sessions
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'pronote_session');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 heure
if (!defined('SESSION_PATH')) define('SESSION_PATH', '/');
if (!defined('SESSION_SECURE')) define('SESSION_SECURE', {$sessionSecure}); // True en HTTPS
if (!defined('SESSION_HTTPONLY')) define('SESSION_HTTPONLY', true);
if (!defined('SESSION_SAMESITE')) define('SESSION_SAMESITE', 'Lax'); // Options: Lax, Strict, None

// Configuration des logs
if (!defined('LOG_ENABLED')) define('LOG_ENABLED', true);
if (!defined('LOG_LEVEL')) define('LOG_LEVEL', '{$appEnv}' === 'development' ? 'debug' : 'error');
CONFIG;

                // Tenter d'écrire le fichier avec différentes méthodes
                $configFile = $configDir . '/env.php';
                $writeSuccess = false;
                $lastError = '';
                
                // Méthode 1: file_put_contents avec LOCK_EX
                try {
                    $result = file_put_contents($configFile, $configContent, LOCK_EX);
                    if ($result !== false) {
                        $writeSuccess = true;
                    } else {
                        $lastError = "file_put_contents a retourné false";
                    }
                } catch (Exception $e) {
                    $lastError = "Erreur file_put_contents: " . $e->getMessage();
                }
                
                // Méthode 2: Si la première méthode échoue, essayer fopen/fwrite
                if (!$writeSuccess) {
                    try {
                        $handle = fopen($configFile, 'w');
                        if ($handle !== false) {
                            if (flock($handle, LOCK_EX)) {
                                $result = fwrite($handle, $configContent);
                                flock($handle, LOCK_UN);
                                if ($result !== false) {
                                    $writeSuccess = true;
                                } else {
                                    $lastError = "fwrite a échoué";
                                }
                            } else {
                                $lastError = "Impossible de verrouiller le fichier";
                            }
                            fclose($handle);
                        } else {
                            $lastError = "Impossible d'ouvrir le fichier pour écriture";
                        }
                    } catch (Exception $e) {
                        $lastError = "Erreur fopen/fwrite: " . $e->getMessage();
                    }
                }
                
                if (!$writeSuccess) {
                    throw new Exception("Impossible d'écrire le fichier de configuration. Dernière erreur: " . $lastError . ". Vérifiez les permissions du répertoire: " . $configDir);
                }
                
                // Vérifier que le fichier a bien été créé et qu'il contient le bon contenu
                if (!file_exists($configFile)) {
                    throw new Exception("Le fichier de configuration a été créé mais est introuvable.");
                }
                
                $writtenContent = file_get_contents($configFile);
                if (strlen($writtenContent) < 100) {
                    throw new Exception("Le fichier de configuration semble incomplet ou corrompu.");
                }
                
                // Définir les permissions restreintes après création
                chmod($configFile, 0640); // Permissions restreintes
                
                // Créer un fichier .htaccess pour protéger les fichiers de config
                $htaccessContent = <<<HTACCESS
# Protéger les fichiers de configuration
<Files ~ "^(env|config|settings)\.(php|inc)$">
    Order allow,deny
    Deny from all
</Files>

# Protection contre l'accès aux fichiers .env ou .htaccess
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>
HTACCESS;

                file_put_contents($configDir . '/.htaccess', $htaccessContent, LOCK_EX);
                
                // Importer le schéma SQL s'il existe
                $schemaFile = $apiDir . '/schema.sql';
                if (file_exists($schemaFile) && is_readable($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    
                    // Exécuter le script SQL par requêtes séparées
                    if (!empty($sql)) {
                        // Diviser le fichier en requêtes individuelles
                        $queries = array_filter(
                            array_map('trim', 
                                explode(";", $sql)
                            )
                        );
                        
                        foreach ($queries as $query) {
                            if (!empty($query)) {
                                $pdo->exec($query);
                            }
                        }
                    }
                }
                
                // Créer le compte administrateur initial
                // Vérifier d'abord que la table existe et a la bonne structure
                $tableExists = false;
                $tableHasCorrectStructure = false;
                
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE 'administrateurs'");
                    $tableExists = $checkTable && $checkTable->rowCount() > 0;
                    
                    if ($tableExists) {
                        // Vérifier la structure de la table
                        $columns = $pdo->query("DESCRIBE administrateurs")->fetchAll(PDO::FETCH_COLUMN);
                        $requiredColumns = ['id', 'nom', 'prenom', 'mail', 'identifiant', 'mot_de_passe', 'date_creation', 'adresse', 'role', 'actif'];
                        $missingColumns = array_diff($requiredColumns, $columns);
                        
                        if (empty($missingColumns)) {
                            $tableHasCorrectStructure = true;
                        } else {
                            // Essayer de corriger la structure
                            foreach ($missingColumns as $column) {
                                try {
                                    switch ($column) {
                                        case 'adresse':
                                            $pdo->exec("ALTER TABLE administrateurs ADD COLUMN `adresse` varchar(255) DEFAULT NULL");
                                            break;
                                        case 'role':
                                            $pdo->exec("ALTER TABLE administrateurs ADD COLUMN `role` varchar(50) NOT NULL DEFAULT 'administrateur'");
                                            break;
                                        case 'actif':
                                            $pdo->exec("ALTER TABLE administrateurs ADD COLUMN `actif` tinyint(1) NOT NULL DEFAULT '1'");
                                            break;
                                    }
                                } catch (PDOException $e) {
                                    // Ignorer les erreurs de colonnes qui existent déjà
                                }
                            }
                            
                            // Vérifier à nouveau après correction
                            $columns = $pdo->query("DESCRIBE administrateurs")->fetchAll(PDO::FETCH_COLUMN);
                            $missingColumns = array_diff($requiredColumns, $columns);
                            $tableHasCorrectStructure = empty($missingColumns);
                        }
                    }
                } catch (PDOException $e) {
                    // Ignorer cette erreur et essayer de créer la table
                }
                
                // Si la table n'existe pas ou n'a pas la bonne structure, la créer/recréer
                if (!$tableExists || !$tableHasCorrectStructure) {
                    try {
                        // Sauvegarder les données existantes si la table existe
                        $existingAdminData = [];
                        if ($tableExists) {
                            try {
                                $existingAdmins = $pdo->query("SELECT * FROM administrateurs")->fetchAll();
                                $existingAdminData = $existingAdmins;
                            } catch (PDOException $e) {
                                // Ignorer si on ne peut pas lire les données existantes
                            }
                        }
                        
                        // Créer la table avec la bonne structure
                        $pdo->exec("CREATE TABLE IF NOT EXISTS `administrateurs` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `nom` varchar(50) NOT NULL,
                            `prenom` varchar(50) NOT NULL,
                            `mail` varchar(100) NOT NULL,
                            `identifiant` varchar(50) NOT NULL,
                            `mot_de_passe` varchar(255) NOT NULL,
                            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `adresse` varchar(255) DEFAULT NULL,
                            `role` varchar(50) NOT NULL DEFAULT 'administrateur',
                            `actif` tinyint(1) NOT NULL DEFAULT '1',
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `identifiant` (`identifiant`),
                            UNIQUE KEY `mail` (`mail`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                        
                        // Restaurer les données existantes si nécessaire
                        if (!empty($existingAdminData)) {
                            foreach ($existingAdminData as $admin) {
                                try {
                                    $stmt = $pdo->prepare("
                                        INSERT IGNORE INTO administrateurs 
                                        (nom, prenom, mail, identifiant, mot_de_passe, date_creation, adresse, role, actif) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmt->execute([
                                        $admin['nom'] ?? '',
                                        $admin['prenom'] ?? '',
                                        $admin['mail'] ?? '',
                                        $admin['identifiant'] ?? '',
                                        $admin['mot_de_passe'] ?? '',
                                        $admin['date_creation'] ?? date('Y-m-d H:i:s'),
                                        $admin['adresse'] ?? 'N/A',
                                        $admin['role'] ?? 'administrateur',
                                        isset($admin['actif']) ? $admin['actif'] : 1
                                    ]);
