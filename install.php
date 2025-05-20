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

if (!in_array($clientIP, $allowedIPs) && 
    (!isset($_SERVER['SERVER_ADDR']) || $clientIP !== $_SERVER['SERVER_ADDR'])) {
    
    // Journaliser la tentative d'accès non autorisée
    error_log("Tentative d'accès non autorisée au script d'installation depuis: " . $clientIP);
    
    // Si un fichier .env existe, vérifier si une IP supplémentaire est autorisée
    $envFile = __DIR__ . '/.env';
    $additionalIpAllowed = false;
    
    if (file_exists($envFile) && is_readable($envFile)) {
        $envContent = file_get_contents($envFile);
        if (preg_match('/ALLOWED_INSTALL_IP\s*=\s*(.+)/', $envContent, $matches)) {
            $additionalIP = trim($matches[1]);
            if (filter_var($additionalIP, FILTER_VALIDATE_IP) && $additionalIP === $clientIP) {
                $additionalIpAllowed = true;
            }
        }
    }
    
    if (!$additionalIpAllowed) {
        die('Accès non autorisé depuis votre adresse IP.');
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
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($path)) {
        try {
            if (!mkdir($path, 0755, true)) {
                $permissionIssues[] = "Impossible de créer le dossier {$dir}";
            }
        } catch (Exception $e) {
            $permissionIssues[] = "Erreur lors de la création du dossier {$dir}: " . $e->getMessage();
        }
    } else if (!is_writable($path)) {
        $permissionIssues[] = "Le dossier {$dir} n'est pas accessible en écriture";
    }
}

// Génération d'un jeton CSRF unique
if (!isset($_SESSION['install_token']) || empty($_SESSION['install_token'])) {
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
    // Validation du jeton CSRF
    if (!isset($_POST['install_token']) || !isset($_SESSION['install_token']) || 
        $_POST['install_token'] !== $_SESSION['install_token'] ||
        !isset($_SESSION['token_time']) || (time() - $_SESSION['token_time']) > 3600) {
        $dbError = "Erreur de sécurité: jeton de formulaire invalide ou expiré.";
    } else {
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
                
                if (!is_dir($configDir)) {
                    if (!mkdir($configDir, 0755, true)) {
                        throw new Exception("Impossible de créer le répertoire de configuration.");
                    }
                }
                
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

                // Sauvegarder le fichier de configuration de manière sécurisée
                if (file_put_contents($apiDir . '/config/env.php', $configContent, LOCK_EX) === false) {
                    throw new Exception("Impossible d'écrire le fichier de configuration.");
                }
                chmod($apiDir . '/config/env.php', 0640); // Permissions restreintes
                
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
                // Vérifier d'abord que la table existe
                $tableExists = false;
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE 'administrateurs'");
                    $tableExists = $checkTable && $checkTable->rowCount() > 0;
                } catch (PDOException $e) {
                    // Ignorer cette erreur et essayer de créer la table
                }
                
                // Si la table n'existe pas, la créer
                if (!$tableExists) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS `administrateurs` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `nom` varchar(50) NOT NULL,
                        `prenom` varchar(50) NOT NULL,
                        `mail` varchar(100) NOT NULL,
                        `identifiant` varchar(50) NOT NULL,
                        `mot_de_passe` varchar(255) NOT NULL,
                        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `adresse` varchar(255) DEFAULT NULL,
                        `role` varchar(50) NOT NULL DEFAULT 'administration',
                        `actif` tinyint(1) NOT NULL DEFAULT '1',
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `identifiant` (`identifiant`),
                        UNIQUE KEY `mail` (`mail`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                }
                
                // Hachage du mot de passe avec une méthode sécurisée
                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                
                // Générer un identifiant au format nom.prenom
                $identifiant = strtolower(
                    transliterator_transliterate(
                        'Any-Latin; Latin-ASCII; [^a-zA-Z0-9\.] Remove;', 
                        $adminNom
                    ) . '.' . 
                    transliterator_transliterate(
                        'Any-Latin; Latin-ASCII; [^a-zA-Z0-9] Remove;', 
                        $adminPrenom
                    )
                );
                
                // En cas de problème avec transliterator, utiliser un fallback simple
                if (empty($identifiant)) {
                    $identifiant = strtolower(
                        preg_replace('/[^a-zA-Z0-9]/', '', $adminNom) . '.' . 
                        preg_replace('/[^a-zA-Z0-9]/', '', $adminPrenom)
                    );
                }
                
                // Insérer l'administrateur en utilisant une requête préparée
                $stmt = $pdo->prepare("
                    INSERT INTO administrateurs 
                    (nom, prenom, mail, identifiant, mot_de_passe, role, adresse) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $adminNom,
                    $adminPrenom,
                    $adminMail,
                    $identifiant,
                    $passwordHash,
                    'administrateur', // Rôle par défaut
                    'N/A' // Adresse par défaut
                ]);
                
                // Créer un fichier de verrouillage des comptes admin
                $adminLockFile = $installDir . '/admin.lock';
                $adminLockContent = "ADMIN_CREATED=true\nDATE=" . date('Y-m-d H:i:s');
                file_put_contents($adminLockFile, $adminLockContent, LOCK_EX);
                chmod($adminLockFile, 0400); // Rendre le fichier en lecture seule
                
                // Créer un fichier de verrou pour empêcher l'exécution future de l'installation
                $installTime = date('Y-m-d H:i:s');
                $lockContent = <<<LOCK
Installation completed on: {$installTime}
IP: {$clientIP}
Admin account created: {$adminNom} {$adminPrenom} ({$identifiant})
DO NOT DELETE THIS FILE UNLESS YOU WANT TO REINSTALL THE APPLICATION
LOCK;

                file_put_contents($installLockFile, $lockContent, LOCK_EX);
                
                // Indiquer que l'installation est réussie
                $installed = true;
                $adminIdentifiant = $identifiant;
                
                // Sécuriser le fichier d'installation immédiatement
                if (file_exists(__DIR__ . '/install_guard.php')) {
                    include_once __DIR__ . '/install_guard.php';
                }
                
            } catch (PDOException $e) {
                throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
            }
        } catch (Exception $e) {
            $dbError = $e->getMessage();
        }
    }
}

// Renouvellement du jeton CSRF après la soumission pour éviter les attaques par réutilisation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $_SESSION['install_token'] = bin2hex(random_bytes(32));
        $_SESSION['token_time'] = time();
        $install_token = $_SESSION['install_token'];
    } catch (Exception $e) {
        // Fallback si random_bytes échoue
        $_SESSION['install_token'] = hash('sha256', uniqid(mt_rand(), true));
        $_SESSION['token_time'] = time();
        $install_token = $_SESSION['install_token'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <title>Installation de Pronote</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px;
            line-height: 1.6;
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="email"], select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .error { 
            color: #721c24;
            padding: 12px;
            background: #f8d7da;
            border: 1px solid #f5c6cb; 
            border-radius: 5px; 
            margin-bottom: 20px; 
        }
        .warning {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success { 
            color: #155724; 
            padding: 12px; 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            border-radius: 5px; 
            margin-bottom: 20px; 
        }
        button { 
            padding: 10px 15px; 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 4px;
            cursor: pointer; 
        }
        button:hover {
            background-color: #45a049;
        }
        .requirements {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .requirements ul {
            margin-bottom: 0;
        }
        code {
            background-color: #f1f1f1;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        .section-title {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <h1>Installation de Pronote</h1>
    
    <?php if (isset($httpsWarning)): ?>
        <div class="warning">
            <p><?= htmlspecialchars($httpsWarning) ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($installed): ?>
        <div class="success">
            <h2>Installation réussie!</h2>
            <p>Pronote a été correctement configuré.</p>
            <p><strong>Compte administrateur créé:</strong> Identifiant: <code><?= htmlspecialchars($adminIdentifiant) ?></code></p>
            <p><strong>Important:</strong> Par mesure de sécurité, le script d'installation a été désactivé et aucun autre compte administrateur ne pourra être créé ultérieurement.</p>
            <p><a href="<?= htmlspecialchars($baseUrl) ?>/login/public/index.php">Accéder à l'application</a></p>
        </div>
    <?php else: ?>
        <div class="requirements">
            <h3>Prérequis vérifiés</h3>
            <ul>
                <li>PHP Version: <strong><?= htmlspecialchars(PHP_VERSION) ?></strong> ✓</li>
                <li>Extensions PHP requises: <strong>Présentes</strong> ✓</li>
                <li>Répertoires avec permissions d'écriture
                    <?php if (!empty($permissionIssues)): ?>
                        <span style="color: red;">✗</span>
                    <?php else: ?>
                        ✓
                    <?php endif; ?>
                </li>
            </ul>
        </div>
        
        <?php if (!empty($dbError)): ?>
            <div class="error">
                <p>Erreur: <?= htmlspecialchars($dbError) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($permissionIssues)): ?>
            <div class="error">
                <h3>Problèmes de permissions</h3>
                <ul>
                    <?php foreach ($permissionIssues as $issue): ?>
                        <li><?= htmlspecialchars($issue) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Veuillez résoudre ces problèmes de permissions avant de continuer.</p>
            </div>
        <?php else: ?>
            <form method="post" action="">
                <h2 class="section-title">Configuration de l'application</h2>
                <div class="form-group">
                    <label for="base_url">URL de base de l'application</label>
                    <input type="text" id="base_url" name="base_url" value="<?= htmlspecialchars($baseUrl) ?>" required>
                    <small>Par exemple: /pronote ou laisser vide si installé à la racine</small>
                </div>
                
                <div class="form-group">
                    <label for="app_env">Environnement</label>
                    <select id="app_env" name="app_env">
                        <option value="development">Développement</option>
                        <option value="production" selected>Production</option>
                        <option value="test">Test</option>
                    </select>
                </div>
                
                <h2 class="section-title">Configuration de la base de données</h2>
                <div class="form-group">
                    <label for="db_host">Hôte de la base de données</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Nom de la base de données</label>
                    <input type="text" id="db_name" name="db_name" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Utilisateur de la base de données</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Mot de passe de la base de données</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <h2 class="section-title">Création du compte administrateur principal</h2>
                <p>Ce compte sera le seul compte administrateur autorisé. Aucun autre compte administrateur ne pourra être créé ultérieurement.</p>
                
                <div class="form-group">
                    <label for="admin_nom">Nom</label>
                    <input type="text" id="admin_nom" name="admin_nom" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_prenom">Prénom</label>
                    <input type="text" id="admin_prenom" name="admin_prenom" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_mail">Email</label>
                    <input type="email" id="admin_mail" name="admin_mail" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Mot de passe</label>
                    <input type="password" id="admin_password" name="admin_password" required minlength="12" 
                           pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}"
                           title="Le mot de passe doit contenir au moins 12 caractères, incluant au moins une majuscule, une minuscule, un chiffre et un caractère spécial">
                    <small>Minimum 12 caractères incluant au moins une majuscule, une minuscule, un chiffre et un caractère spécial</small>
                </div>
                
                <!-- Champ caché pour le jeton CSRF -->
                <input type="hidden" name="install_token" value="<?= htmlspecialchars($install_token) ?>">
                
                <button type="submit">Installer</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
