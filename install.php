<?php
/**
 * Script d'installation de Pronote - VERSION COMPLÈTE ET AUTO-CORRECTIVE
 * Ce script s'auto-désactivera après une installation réussie
 * Il corrige automatiquement tous les problèmes de structure de base de données
 */

// Configuration de sécurité
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(300); // 5 minutes pour l'installation complète

// Définir les en-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// NETTOYAGE AUTOMATIQUE DES FICHIERS TEMPORAIRES
$filesToClean = [
    'check_database_health.php',
    'fix_complete_database.php', 
    'test_permissions.php',
    'test_db_connection.php',
    'debug_ip.php'
];

foreach ($filesToClean as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        // Vérifier si le fichier contient du code de redirection (déjà nettoyé)
        $content = file_get_contents($filePath);
        if (strpos($content, 'Ce fichier a été supprimé') === false && 
            strpos($content, 'fichier de débogage temporaire') === false) {
            // Remplacer par une redirection de sécurité
            $redirectContent = "<?php\n// Fichier supprimé - redirection de sécurité\nheader('Location: install.php');\nexit;\n?>";
            @file_put_contents($filePath, $redirectContent);
        }
    }
}

// Vérifier si l'installation est déjà terminée
$installLockFile = __DIR__ . '/install.lock';
if (file_exists($installLockFile)) {
    die('L\'installation a déjà été effectuée. Pour réinstaller, supprimez le fichier install.lock du répertoire racine.');
}

// Vérification de la version PHP
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

// Gestion sécurisée de l'accès par IP
$allowedIPs = ['127.0.0.1', '::1'];
$clientIP = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);

// Vérifier le fichier .env pour les IPs supplémentaires
$envFile = __DIR__ . '/.env';
$additionalIpAllowed = false;

if (file_exists($envFile) && is_readable($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/ALLOWED_INSTALL_IP\s*=\s*(.+)/', $envContent, $matches)) {
        $ipList = array_map('trim', explode(',', trim($matches[1])));
        foreach ($ipList as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && $ip === $clientIP) {
                $additionalIpAllowed = true;
                break;
            }
        }
    }
}

if (!in_array($clientIP, $allowedIPs) && !$additionalIpAllowed) {
    error_log("Tentative d'accès non autorisée au script d'installation depuis: " . $clientIP);
    die('Accès non autorisé depuis votre adresse IP: ' . $clientIP . '. Créez un fichier .env avec ALLOWED_INSTALL_IP=' . $clientIP);
}

// Démarrer la session
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
]);

// Détecter automatiquement les chemins
$installDir = __DIR__;
$baseUrl = '';

if (isset($_SERVER['REQUEST_URI'])) {
    $scriptPath = dirname($_SERVER['REQUEST_URI']);
    $baseUrl = str_replace('/install.php', '', $scriptPath);
    if ($baseUrl === '/.') {
        $baseUrl = '';
    }
}

$baseUrl = filter_var($baseUrl, FILTER_SANITIZE_URL);

// Créer automatiquement tous les répertoires nécessaires
$directories = [
    'API/logs',
    'API/config', 
    'uploads',
    'temp',
    'login/logs'
];

$permissionErrors = [];
$permissionWarnings = [];

foreach ($directories as $dir) {
    $path = $installDir . '/' . $dir;
    
    // Créer le répertoire s'il n'existe pas
    if (!is_dir($path)) {
        if (!@mkdir($path, 0755, true)) {
            $permissionErrors[] = "Impossible de créer le dossier {$dir}";
            continue;
        }
    }
    
    // Test d'écriture réel
    $testFile = $path . '/test_install_' . time() . '.txt';
    $canWrite = @file_put_contents($testFile, 'test d\'installation') !== false;
    
    if ($canWrite) {
        @unlink($testFile);
    } else {
        // Essayer de corriger automatiquement
        $fixed = false;
        
        // Essayer différentes permissions
        $permissions = [0755, 0775, 0777];
        foreach ($permissions as $perm) {
            if (@chmod($path, $perm)) {
                $testFile = $path . '/test_chmod_' . time() . '.txt';
                if (@file_put_contents($testFile, 'test chmod') !== false) {
                    @unlink($testFile);
                    $fixed = true;
                    break;
                }
            }
        }
        
        if (!$fixed) {
            $permissionWarnings[] = "Le dossier {$dir} n'est pas accessible en écriture";
        }
    }
}

// Générer un token CSRF
if (!isset($_SESSION['install_token']) || !isset($_SESSION['token_time']) || 
    (time() - $_SESSION['token_time']) > 1800) {
    try {
        $_SESSION['install_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['install_token'] = hash('sha256', uniqid(mt_rand(), true));
    }
    $_SESSION['token_time'] = time();
}
$install_token = $_SESSION['install_token'];

// Traitement du formulaire
$installed = false;
$dbError = '';
$step = isset($_POST['step']) ? intval($_POST['step']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation CSRF
    if (!isset($_POST['install_token']) || $_POST['install_token'] !== $_SESSION['install_token']) {
        $dbError = "Erreur de sécurité: Jeton de sécurité invalide";
    } else {
        try {
            // Valider les entrées
            $dbHost = filter_input(INPUT_POST, 'db_host', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'localhost';
            $dbName = filter_input(INPUT_POST, 'db_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $dbUser = filter_input(INPUT_POST, 'db_user', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $dbPass = $_POST['db_pass'] ?? '';
            $appEnv = filter_input(INPUT_POST, 'app_env', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $baseUrlInput = filter_input(INPUT_POST, 'base_url', FILTER_SANITIZE_URL) ?: $baseUrl;
            
            // Informations administrateur
            $adminNom = filter_input(INPUT_POST, 'admin_nom', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $adminPrenom = filter_input(INPUT_POST, 'admin_prenom', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $adminMail = filter_input(INPUT_POST, 'admin_mail', FILTER_SANITIZE_EMAIL) ?: '';
            $adminPassword = $_POST['admin_password'] ?? '';
            
            if (!in_array($appEnv, ['development', 'production', 'test'])) {
                $appEnv = 'production';
            }
            
            // Validations
            if (empty($dbName) || empty($dbUser)) {
                throw new Exception("Le nom de la base de données et l'utilisateur sont obligatoires.");
            }
            
            if (empty($adminNom) || empty($adminPrenom) || empty($adminMail) || empty($adminPassword)) {
                throw new Exception("Tous les champs du compte administrateur sont obligatoires.");
            }
            
            if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email de l'administrateur n'est pas valide.");
            }
            
            if (strlen($adminPassword) < 12) {
                throw new Exception("Le mot de passe administrateur doit contenir au moins 12 caractères.");
            }
            
            // Vérifier la robustesse du mot de passe
            if (!preg_match('/[A-Z]/', $adminPassword) || !preg_match('/[a-z]/', $adminPassword) || 
                !preg_match('/[0-9]/', $adminPassword) || !preg_match('/[^a-zA-Z0-9]/', $adminPassword)) {
                throw new Exception("Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.");
            }
            
            // ÉTAPE 1: Tester et configurer la base de données
            $dsn = "mysql:host={$dbHost};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            
            // Créer la base de données si elle n'existe pas
            $dbNameSafe = str_replace('`', '', $dbName);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameSafe}`");
            $pdo->exec("USE `{$dbNameSafe}`");
            
            // ÉTAPE 2: Créer la configuration
            $configDir = $installDir . '/API/config';
            if (!is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }
            
            $sessionSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'true' : 'false';
            $installTime = date('Y-m-d H:i:s');
            
            $configContent = <<<CONFIG
<?php
/**
 * Configuration d'environnement - Généré par l'installation
 * Date: {$installTime}
 */

// Environnement
if (!defined('APP_ENV')) define('APP_ENV', '{$appEnv}');

// Configuration de base
if (!defined('APP_NAME')) define('APP_NAME', 'Pronote');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_ROOT')) define('APP_ROOT', realpath(__DIR__ . '/../../'));

// URLs de base
if (!defined('BASE_URL')) define('BASE_URL', '{$baseUrlInput}');
if (!defined('APP_URL')) define('APP_URL', '{$baseUrlInput}');
if (!defined('LOGIN_URL')) define('LOGIN_URL', BASE_URL . '/login/public/index.php');
if (!defined('LOGOUT_URL')) define('LOGOUT_URL', BASE_URL . '/login/public/logout.php');
if (!defined('HOME_URL')) define('HOME_URL', BASE_URL . '/accueil/accueil.php');

// Base de données
if (!defined('DB_HOST')) define('DB_HOST', '{$dbHost}');
if (!defined('DB_NAME')) define('DB_NAME', '{$dbName}');
if (!defined('DB_USER')) define('DB_USER', '{$dbUser}');
if (!defined('DB_PASS')) define('DB_PASS', '{$dbPass}');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Sessions
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'pronote_session');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600);
if (!defined('SESSION_PATH')) define('SESSION_PATH', '/');
if (!defined('SESSION_SECURE')) define('SESSION_SECURE', {$sessionSecure});
if (!defined('SESSION_HTTPONLY')) define('SESSION_HTTPONLY', true);
if (!defined('SESSION_SAMESITE')) define('SESSION_SAMESITE', 'Lax');

// Logs
if (!defined('LOG_ENABLED')) define('LOG_ENABLED', true);
if (!defined('LOG_LEVEL')) define('LOG_LEVEL', '{$appEnv}' === 'development' ? 'debug' : 'error');
CONFIG;

            $configFile = $configDir . '/env.php';
            if (file_put_contents($configFile, $configContent, LOCK_EX) === false) {
                throw new Exception("Impossible d'écrire le fichier de configuration");
            }
            
            chmod($configFile, 0640);
            
            // ÉTAPE 3: Créer/corriger TOUTES les tables de la base de données
            $this->createCompleteDatabase($pdo);
            
            // ÉTAPE 4: Créer le compte administrateur
            $this->createAdminAccount($pdo, $adminNom, $adminPrenom, $adminMail, $adminPassword);
            
            // ÉTAPE 5: Finaliser l'installation
            $this->finalizeInstallation();
            
            $installed = true;
            
        } catch (Exception $e) {
            $dbError = $e->getMessage();
        }
    }
}

// Fonction pour créer toute la structure de base de données
function createCompleteDatabase($pdo) {
    // Tables principales avec structure complète
    $tables = [
        'administrateurs' => "CREATE TABLE IF NOT EXISTS `administrateurs` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        'eleves' => "CREATE TABLE IF NOT EXISTS `eleves` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `date_naissance` date NOT NULL,
            `classe` varchar(50) NOT NULL,
            `lieu_naissance` varchar(100) NOT NULL,
            `adresse` varchar(255) NOT NULL,
            `mail` varchar(150) NOT NULL,
            `telephone` varchar(20) DEFAULT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `date_creation` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `mail` (`mail`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'professeurs' => "CREATE TABLE IF NOT EXISTS `professeurs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `mail` varchar(150) NOT NULL,
            `adresse` varchar(255) NOT NULL,
            `telephone` varchar(20) DEFAULT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `professeur_principal` varchar(50) NOT NULL DEFAULT 'non',
            `matiere` varchar(100) NOT NULL,
            `date_creation` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `mail` (`mail`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'parents' => "CREATE TABLE IF NOT EXISTS `parents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `mail` varchar(150) NOT NULL,
            `adresse` varchar(255) NOT NULL,
            `telephone` varchar(20) DEFAULT NULL,
            `metier` varchar(100) DEFAULT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `est_parent_eleve` enum('oui','non') NOT NULL DEFAULT 'non',
            `date_creation` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `mail` (`mail`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'vie_scolaire' => "CREATE TABLE IF NOT EXISTS `vie_scolaire` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `mail` varchar(150) NOT NULL,
            `telephone` varchar(20) DEFAULT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `est_CPE` enum('oui','non') NOT NULL DEFAULT 'non',
            `est_infirmerie` enum('oui','non') NOT NULL DEFAULT 'non',
            `date_creation` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `mail` (`mail`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'notes' => "CREATE TABLE IF NOT EXISTS `notes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_eleve` int(11) NOT NULL,
            `id_matiere` int(11) NOT NULL,
            `note` decimal(4,2) NOT NULL,
            `coefficient` decimal(3,2) DEFAULT '1.00',
            `commentaire` text,
            `date_note` date NOT NULL,
            `trimestre` int(1) NOT NULL DEFAULT '1',
            `type_evaluation` varchar(50) DEFAULT 'controle',
            `date_creation` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_eleve_matiere` (`id_eleve`, `id_matiere`),
            KEY `idx_date` (`date_note`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'absences' => "CREATE TABLE IF NOT EXISTS `absences` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_eleve` int(11) NOT NULL,
            `date_debut` datetime NOT NULL,
            `date_fin` datetime NOT NULL,
            `motif` varchar(255) DEFAULT NULL,
            `justifiee` enum('oui','non') NOT NULL DEFAULT 'non',
            `type_absence` enum('absence','retard') NOT NULL DEFAULT 'absence',
            `commentaire` text,
            `date_creation` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_eleve` (`id_eleve`),
            KEY `idx_date` (`date_debut`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'devoirs' => "CREATE TABLE IF NOT EXISTS `devoirs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titre` varchar(255) NOT NULL,
            `description` text NOT NULL,
            `id_matiere` int(11) NOT NULL,
            `classe` varchar(50) NOT NULL,
            `date_pour` date NOT NULL,
            `date_creation` datetime DEFAULT current_timestamp(),
            `id_professeur` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_matiere` (`id_matiere`),
            KEY `idx_classe` (`classe`),
            KEY `idx_date` (`date_pour`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'cahier_texte' => "CREATE TABLE IF NOT EXISTS `cahier_texte` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_matiere` int(11) NOT NULL,
            `classe` varchar(50) NOT NULL,
            `contenu` text NOT NULL,
            `date_cours` date NOT NULL,
            `date_creation` datetime DEFAULT current_timestamp(),
            `id_professeur` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_matiere_classe` (`id_matiere`, `classe`),
            KEY `idx_date` (`date_cours`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'evenements' => "CREATE TABLE IF NOT EXISTS `evenements` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titre` varchar(255) NOT NULL,
            `description` text,
            `date_debut` datetime NOT NULL,
            `date_fin` datetime NOT NULL,
            `lieu` varchar(255) DEFAULT NULL,
            `type_evenement` varchar(50) DEFAULT 'general',
            `date_creation` datetime DEFAULT current_timestamp(),
            `id_createur` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_date` (`date_debut`),
            KEY `idx_type` (`type_evenement`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'messages' => "CREATE TABLE IF NOT EXISTS `messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `expediteur_id` int(11) NOT NULL,
            `expediteur_type` varchar(20) NOT NULL,
            `destinataire_id` int(11) NOT NULL,
            `destinataire_type` varchar(20) NOT NULL,
            `sujet` varchar(255) NOT NULL,
            `contenu` text NOT NULL,
            `date_envoi` datetime DEFAULT current_timestamp(),
            `lu` enum('oui','non') NOT NULL DEFAULT 'non',
            `date_lecture` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_destinataire` (`destinataire_id`, `destinataire_type`),
            KEY `idx_expediteur` (`expediteur_id`, `expediteur_type`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'matieres' => "CREATE TABLE IF NOT EXISTS `matieres` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `code` varchar(10) NOT NULL,
            `coefficient` decimal(3,2) DEFAULT '1.00',
            `couleur` varchar(7) DEFAULT '#3498db',
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`),
            UNIQUE KEY `code` (`code`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'classes' => "CREATE TABLE IF NOT EXISTS `classes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(50) NOT NULL,
            `niveau` varchar(20) NOT NULL,
            `annee_scolaire` varchar(9) NOT NULL,
            `professeur_principal_id` int(11) DEFAULT NULL,
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`),
            UNIQUE KEY `nom_annee` (`nom`, `annee_scolaire`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;",
        
        'demandes_reinitialisation' => "CREATE TABLE IF NOT EXISTS `demandes_reinitialisation` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `user_type` varchar(30) NOT NULL,
            `date_demande` datetime NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'pending',
            `date_traitement` datetime DEFAULT NULL,
            `admin_id` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`, `user_type`),
            KEY `idx_status` (`status`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    ];
    
    // Vérifier et corriger automatiquement toutes les tables
    $existingTables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }
    
    // Créer toutes les tables
    foreach ($tables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            
            // Si la table existait déjà, vérifier sa structure
            if (in_array($tableName, $existingTables)) {
                correctTableStructure($pdo, $tableName);
            }
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la création de la table {$tableName}: " . $e->getMessage());
        }
    }
    
    // Insérer les matières par défaut
    $defaultMatieres = [
        ['Mathématiques', 'MATH', 4.00, '#e74c3c'],
        ['Français', 'FR', 4.00, '#3498db'],
        ['Histoire-Géographie', 'HG', 3.00, '#f39c12'],
        ['Sciences Physiques', 'PHY', 3.00, '#9b59b6'],
        ['SVT', 'SVT', 3.00, '#2ecc71'],
        ['Anglais', 'ANG', 3.00, '#1abc9c'],
        ['Espagnol', 'ESP', 2.00, '#e67e22'],
        ['Technologie', 'TECH', 2.00, '#95a5a6'],
        ['Arts Plastiques', 'ART', 1.00, '#f1c40f'],
        ['EPS', 'EPS', 1.00, '#27ae60']
    ];
    
    foreach ($defaultMatieres as $matiere) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO matieres (nom, code, coefficient, couleur) VALUES (?, ?, ?, ?)");
            $stmt->execute($matiere);
        } catch (PDOException $e) {
            // Ignorer les erreurs de doublons
        }
    }
    
    // Insérer les classes par défaut
    $currentYear = date('Y') . '-' . (date('Y') + 1);
    $defaultClasses = [
        ['6ème A', '6ème', $currentYear],
        ['6ème B', '6ème', $currentYear],
        ['5ème A', '5ème', $currentYear],
        ['5ème B', '5ème', $currentYear],
        ['4ème A', '4ème', $currentYear],
        ['4ème B', '4ème', $currentYear],
        ['3ème A', '3ème', $currentYear],
        ['3ème B', '3ème', $currentYear]
    ];
    
    foreach ($defaultClasses as $classe) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO classes (nom, niveau, annee_scolaire) VALUES (?, ?, ?)");
            $stmt->execute($classe);
        } catch (PDOException $e) {
            // Ignorer les erreurs de doublons
        }
    }
}

// Nouvelle fonction pour corriger automatiquement les structures de tables
function correctTableStructure($pdo, $tableName) {
    $corrections = [
        'administrateurs' => [
            'adresse' => "ALTER TABLE administrateurs ADD COLUMN `adresse` varchar(255) DEFAULT NULL",
            'role' => "ALTER TABLE administrateurs ADD COLUMN `role` varchar(50) NOT NULL DEFAULT 'administrateur'",
            'actif' => "ALTER TABLE administrateurs ADD COLUMN `actif` tinyint(1) NOT NULL DEFAULT '1'"
        ],
        'eleves' => [
            'telephone' => "ALTER TABLE eleves ADD COLUMN `telephone` varchar(20) DEFAULT NULL"
        ],
        'professeurs' => [
            'telephone' => "ALTER TABLE professeurs ADD COLUMN `telephone` varchar(20) DEFAULT NULL"
        ]
    ];
    
    if (isset($corrections[$tableName])) {
        // Obtenir les colonnes existantes
        $stmt = $pdo->query("DESCRIBE {$tableName}");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Ajouter les colonnes manquantes
        foreach ($corrections[$tableName] as $column => $sql) {
            if (!in_array($column, $existingColumns)) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    // Ignorer les erreurs si la colonne existe déjà
                }
            }
        }
    }
}

// Fonction pour créer le compte administrateur
function createAdminAccount($pdo, $nom, $prenom, $mail, $password) {
    // Vérifier s'il y a déjà des administrateurs
    $stmt = $pdo->query("SELECT COUNT(*) FROM administrateurs");
    $adminCount = $stmt->fetchColumn();
    
    if ($adminCount > 0) {
        throw new Exception("Un compte administrateur existe déjà. L'installation ne peut pas continuer.");
    }
    
    // Générer un identifiant unique
    $identifiant = 'admin_' . uniqid();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insérer l'administrateur
    $stmt = $pdo->prepare("
        INSERT INTO administrateurs (nom, prenom, mail, identifiant, mot_de_passe, adresse, role, actif) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $nom,
        $prenom, 
        $mail,
        $identifiant,
        $hashedPassword,
        'Non spécifiée',
        'administrateur',
        1
    ]);
    
    if (!$result) {
        throw new Exception("Erreur lors de la création du compte administrateur");
    }
}

// Fonction pour finaliser l'installation
function finalizeInstallation() {
    // Créer le fichier de verrouillage
    $lockContent = json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
        'php_version' => PHP_VERSION
    ]);
    
    if (file_put_contents(__DIR__ . '/install.lock', $lockContent, LOCK_EX) === false) {
        throw new Exception("Impossible de créer le fichier de verrouillage");
    }
    
    // Créer un fichier .htaccess de protection
    $htaccessContent = <<<HTACCESS
# Protection des fichiers de configuration
<Files ~ "^(env|config|settings)\.(php|inc)$">
    Order allow,deny
    Deny from all
</Files>

# Protection contre l'accès aux fichiers sensibles
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protection du fichier d'installation après installation
<Files "install.php">
    Order allow,deny
    Deny from all
</Files>
HTACCESS;

    file_put_contents(__DIR__ . '/.htaccess', $htaccessContent, FILE_APPEND | LOCK_EX);
    
    // Nettoyer définitivement les fichiers temporaires
    $filesToDelete = [
        'check_database_health.php',
        'fix_complete_database.php', 
        'test_permissions.php',
        'test_db_connection.php',
        'debug_ip.php'
    ];
    
    foreach ($filesToDelete as $file) {
        $filePath = __DIR__ . '/' . $file;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
    
    // Nettoyer la session
    unset($_SESSION['install_token']);
    unset($_SESSION['token_time']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation de Pronote</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 40px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            border-color: #3498db;
            outline: none;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .success {
            background: #2ecc71;
            color: white;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
        }
        .warning {
            background: #f39c12;
            color: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .section h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Installation de Pronote</h1>
            <p>Configuration complète et automatique du système</p>
        </div>
        
        <div class="content">
            <?php if (!empty($permissionErrors)): ?>
                <div class="error">
                    <h3>❌ Erreurs critiques de permissions</h3>
                    <?php foreach ($permissionErrors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                    <p><strong>Action requise:</strong> Corrigez ces problèmes avant de continuer.</p>
                    <p><a href='test_permissions.php' class='btn'>🔧 Outil de correction automatique</a></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($permissionWarnings)): ?>
                <div class="warning">
                    <h3>⚠️ Problèmes de permissions détectés</h3>
                    <?php foreach ($permissionWarnings as $warning): ?>
                        <p><?= htmlspecialchars($warning) ?></p>
                    <?php endforeach; ?>
                    <p>L'installation peut continuer, mais certaines fonctionnalités peuvent être limitées.</p>
                    <p><a href='test_permissions.php' class='btn'>🔧 Corriger automatiquement</a></p>
                </div>
            <?php endif; ?>

            <?php if ($installed): ?>
                <div class="success">
                    <h2>✅ Installation réussie !</h2>
                    <p>Votre application Pronote a été installée avec succès.</p>
                    <p><strong>Accédez maintenant à votre application :</strong></p>
                    <p><a href="<?= htmlspecialchars($baseUrlInput ?: $baseUrl) ?>/login/public/index.php" 
                          style="color: white; text-decoration: underline;">
                        Page de connexion
                    </a></p>
                    <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.2); border-radius: 5px;">
                        <p><strong>Informations importantes :</strong></p>
                        <ul style="text-align: left;">
                            <li>Le fichier d'installation a été automatiquement protégé</li>
                            <li>Votre base de données est complètement configurée</li>
                            <li>Toutes les tables ont été créées avec les données par défaut</li>
                            <li>Votre compte administrateur est prêt à être utilisé</li>
                        </ul>
                    </div>
                </div>
            <?php elseif (!empty($dbError)): ?>
                <div class="error">
                    <h3>❌ Erreur d'installation</h3>
                    <p><?= htmlspecialchars($dbError) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$installed): ?>
                <form method="post" action="">
                    <input type="hidden" name="install_token" value="<?= htmlspecialchars($install_token) ?>">
                    
                    <div class="section">
                        <h3>🗄️ Configuration de la base de données</h3>
                        <div class="grid">
                            <div class="form-group">
                                <label for="db_host">Hôte de la base de données</label>
                                <input type="text" id="db_host" name="db_host" value="localhost" required>
                            </div>
                            <div class="form-group">
                                <label for="db_name">Nom de la base de données</label>
                                <input type="text" id="db_name" name="db_name" value="pronote" required>
                            </div>
                            <div class="form-group">
                                <label for="db_user">Utilisateur</label>
                                <input type="text" id="db_user" name="db_user" required>
                            </div>
                            <div class="form-group">
                                <label for="db_pass">Mot de passe</label>
                                <input type="password" id="db_pass" name="db_pass">
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h3>⚙️ Configuration de l'application</h3>
                        <div class="grid">
                            <div class="form-group">
                                <label for="base_url">URL de base (chemin depuis la racine)</label>
                                <input type="text" id="base_url" name="base_url" 
                                       value="<?= htmlspecialchars($baseUrl) ?>" 
                                       placeholder="/pronote ou laisser vide si racine">
                            </div>
                            <div class="form-group">
                                <label for="app_env">Environnement</label>
                                <select id="app_env" name="app_env">
                                    <option value="production">Production (recommandé)</option>
                                    <option value="development">Développement</option>
                                    <option value="test">Test</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h3>👤 Compte administrateur principal</h3>
                        <div class="grid">
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
                                <label for="admin_password">Mot de passe (min. 12 caractères)</label>
                                <input type="password" id="admin_password" name="admin_password" 
                                       required minlength="12">
                                <small style="color: #666;">Doit contenir : majuscule, minuscule, chiffre, caractère spécial</small>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn">🚀 Installer Pronote</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
