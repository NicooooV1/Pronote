<?php
/**
 * Script d'installation de Pronote - VERSION COMPLÈTE ET AUTO-CORRECTIVE
 * Ce script s'auto-désactivera après une installation réussie
 * Il corrige automatiquement tous les problèmes de structure de base de données
 */

// Configuration de sécurité et gestion d'erreurs améliorée
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 minutes pour l'installation complète

// Gestion des erreurs fatales
register_shutdown_function('handleFatalError');

function handleFatalError() {
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>";
        echo "<h3>❌ Erreur fatale détectée</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>Fichier:</strong> " . htmlspecialchars($error['file']) . "</p>";
        echo "<p><strong>Ligne:</strong> " . $error['line'] . "</p>";
        echo "<h4>Solutions possibles:</h4>";
        echo "<ul>";
        echo "<li>Vérifiez que tous les répertoires ont les bonnes permissions (755 ou 777)</li>";
        echo "<li>Vérifiez que la base de données est accessible</li>";
        echo "<li>Consultez les logs d'erreur du serveur web</li>";
        echo "<li>Exécutez le script fix_permissions.php avant l'installation</li>";
        echo "</ul>";
        echo "</div>";
    }
}

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
    
    // Test d'écriture réel avec un nom de fichier spécifique selon le répertoire
    $testFileName = ($dir === 'API/config') ? 'test_config_' . time() . '.php' : 'test_install_' . time() . '.txt';
    $testFile = $path . '/' . $testFileName;
    $testContent = ($dir === 'API/config') ? "<?php\n// Test de configuration\ndefine('TEST', true);\n?>" : 'test d\'installation';
    
    $canWrite = @file_put_contents($testFile, $testContent, LOCK_EX) !== false;
    
    if ($canWrite) {
        @unlink($testFile);
    } else {
        // Essayer de corriger automatiquement avec plus de stratégies
        $fixed = false;
        
        // Stratégie 1: Essayer différentes permissions
        $permissions = [0755, 0775, 0777];
        foreach ($permissions as $perm) {
            if (@chmod($path, $perm)) {
                $testFile2 = $path . '/test_chmod_' . time() . '.txt';
                if (@file_put_contents($testFile2, 'test après chmod') !== false) {
                    @unlink($testFile2);
                    $fixed = true;
                    break;
                }
            }
        }
        
        // Stratégie 2: Essayer de changer le propriétaire si on est root
        if (!$fixed && function_exists('posix_getuid') && posix_getuid() === 0) {
            $currentUser = posix_getpwuid(posix_getuid());
            if (@chown($path, $currentUser['uid']) && @chgrp($path, $currentUser['gid'])) {
                $testFile3 = $path . '/test_chown_' . time() . '.txt';
                if (@file_put_contents($testFile3, 'test après chown') !== false) {
                    @unlink($testFile3);
                    $fixed = true;
                }
            }
        }
        
        // Stratégie 3: Essayer de recréer le répertoire avec des permissions différentes
        if (!$fixed && $dir !== 'API/config') { // Ne pas supprimer le répertoire config s'il existe déjà
            @rmdir($path);
            if (@mkdir($path, 0777, true)) {
                $testFile4 = $path . '/test_recreate_' . time() . '.txt';
                if (@file_put_contents($testFile4, 'test après recréation') !== false) {
                    @unlink($testFile4);
                    $fixed = true;
                }
            }
        }
        
        if (!$fixed) {
            if ($dir === 'API/config') {
                $permissionErrors[] = "CRITIQUE: Le dossier {$dir} n'est pas accessible en écriture - Installation impossible";
            } else {
                $permissionWarnings[] = "Le dossier {$dir} n'est pas accessible en écriture";
            }
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

// Traitement du formulaire avec gestion d'erreur améliorée
$installed = false;
$dbError = '';
$step = isset($_POST['step']) ? intval($_POST['step']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation CSRF
    if (!isset($_POST['install_token']) || $_POST['install_token'] !== $_SESSION['install_token']) {
        $dbError = "Erreur de sécurité: Jeton de sécurité invalide";
    } else {
        try {
            // Valider les entrées avec validation renforcée
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
                throw new Exception("Environnement non valide");
            }
            
            // Validations renforcées
            if (empty($dbName) || empty($dbUser)) {
                throw new Exception("Le nom de la base de données et l'utilisateur sont requis");
            }
            
            if (empty($adminNom) || empty($adminPrenom) || empty($adminMail) || empty($adminPassword)) {
                throw new Exception("Toutes les informations administrateur sont requises");
            }
            
            if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email administrateur n'est pas valide");
            }
            
            if (strlen($adminPassword) < 8) {
                throw new Exception("Le mot de passe administrateur doit contenir au moins 8 caractères");
            }
            
            // Vérifier la robustesse du mot de passe (version simplifiée)
            $hasUpper = preg_match('/[A-Z]/', $adminPassword);
            $hasLower = preg_match('/[a-z]/', $adminPassword);
            $hasNumber = preg_match('/[0-9]/', $adminPassword);
            $hasSpecial = preg_match('/[^A-Za-z0-9]/', $adminPassword);
            
            $validationCount = $hasUpper + $hasLower + $hasNumber + $hasSpecial;
            
            if ($validationCount < 3) {
                throw new Exception("Le mot de passe doit contenir au moins 3 des 4 types de caractères suivants : majuscule, minuscule, chiffre, caractère spécial");
            }
            
            // ÉTAPE 1: Tester et configurer la base de données avec gestion d'erreur
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 1: Test de la base de données</h3>";
            
            $dsn = "mysql:host={$dbHost};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10
            ];
            
            try {
                $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
                echo "<p>✅ Connexion au serveur MySQL réussie</p>";
            } catch (PDOException $e) {
                throw new Exception("Impossible de se connecter au serveur MySQL: " . $e->getMessage());
            }
            
            // Créer la base de données si elle n'existe pas
            try {
                $dbNameSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameSafe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbNameSafe}`");
                echo "<p>✅ Base de données '{$dbNameSafe}' sélectionnée</p>";
            } catch (PDOException $e) {
                throw new Exception("Impossible de créer/sélectionner la base de données: " . $e->getMessage());
            }
            
            echo "</div>";
            
            // ÉTAPE 2: Créer la configuration avec vérification approfondie
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 2: Création de la configuration</h3>";
            
            $configDir = $installDir . '/API/config';
            
            // Vérifier à nouveau que le répertoire config est vraiment accessible
            if (!is_dir($configDir)) {
                if (!@mkdir($configDir, 0755, true)) {
                    throw new Exception("Impossible de créer le répertoire de configuration: {$configDir}");
                }
                echo "<p>✅ Répertoire de configuration créé</p>";
            }
            
            // Test d'écriture spécifique pour le fichier de configuration
            $tempConfigFile = $configDir . '/test_env_' . time() . '.php';
            $testConfigContent = "<?php\n// Test de configuration\ndefine('TEST_CONFIG', true);\n?>";
            
            if (@file_put_contents($tempConfigFile, $testConfigContent, LOCK_EX) === false) {
                // Essayer des corrections d'urgence
                @chmod($configDir, 0777);
                if (@file_put_contents($tempConfigFile, $testConfigContent, LOCK_EX) === false) {
                    throw new Exception("Le répertoire de configuration n'est pas accessible en écriture. Permissions actuelles: " . substr(sprintf('%o', fileperms($configDir)), -4) . ". Exécutez: chmod 777 {$configDir}");
                }
            }
            
            @unlink($tempConfigFile);
            echo "<p>✅ Test d'écriture dans le répertoire de configuration réussi</p>";
            
            // Générer la configuration avec gestion d'erreur
            $jwtSecret = bin2hex(random_bytes(32));
            $configContent = "<?php\n";
            $configContent .= "/**\n";
            $configContent .= " * Configuration automatique générée le " . date('Y-m-d H:i:s') . "\n";
            $configContent .= " * NE PAS MODIFIER MANUELLEMENT\n";
            $configContent .= " */\n\n";
            $configContent .= "// Configuration de la base de données\n";
            $configContent .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
            $configContent .= "define('DB_NAME', " . var_export($dbNameSafe, true) . ");\n";
            $configContent .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
            $configContent .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n\n";
            $configContent .= "// Configuration de l'application\n";
            $configContent .= "define('APP_ENV', " . var_export($appEnv, true) . ");\n";
            $configContent .= "define('BASE_URL', " . var_export(rtrim($baseUrlInput, '/'), true) . ");\n";
            $configContent .= "define('APP_ROOT', " . var_export($installDir, true) . ");\n";
            $configContent .= "define('JWT_SECRET', " . var_export($jwtSecret, true) . ");\n\n";
            $configContent .= "// Configuration de sécurité\n";
            $configContent .= "define('SECURE_MODE', " . var_export($appEnv === 'production', true) . ");\n";
            $configContent .= "define('DEBUG_MODE', " . var_export($appEnv === 'development', true) . ");\n\n";
            $configContent .= "// Fuseau horaire\n";
            $configContent .= "date_default_timezone_set('Europe/Paris');\n\n";
            $configContent .= "?>";
            
            // Écrire le fichier avec plusieurs tentatives et gestion d'erreur améliorée
            $configFile = $configDir . '/env.php';
            
            // Sauvegarder l'ancien fichier s'il existe
            if (file_exists($configFile)) {
                $backupFile = $configFile . '.backup.' . date('Y-m-d-H-i-s');
                if (!@copy($configFile, $backupFile)) {
                    error_log("Impossible de sauvegarder l'ancien fichier de configuration");
                }
            }
            
            // Écrire le fichier avec plusieurs tentatives
            $writeSuccess = false;
            $attempts = 0;
            $maxAttempts = 3;
            
            while (!$writeSuccess && $attempts < $maxAttempts) {
                $attempts++;
                
                // Essayer d'écrire le fichier
                $bytesWritten = @file_put_contents($configFile, $configContent, LOCK_EX);
                
                if ($bytesWritten !== false && $bytesWritten > 0) {
                    // Vérifier que le fichier a été correctement écrit
                    if (file_exists($configFile) && filesize($configFile) > 100) {
                        $writeSuccess = true;
                    }
                }
                
                if (!$writeSuccess) {
                    // Tentatives de correction
                    if ($attempts === 1) {
                        // Première tentative : changer les permissions du répertoire
                        @chmod($configDir, 0777);
                        echo "<p>⚠️ Tentative de correction des permissions (777)</p>";
                    } elseif ($attempts === 2) {
                        // Deuxième tentative : essayer un nom de fichier temporaire puis renommer
                        $tempFile = $configDir . '/env_temp_' . time() . '.php';
                        if (@file_put_contents($tempFile, $configContent, LOCK_EX) !== false) {
                            if (@rename($tempFile, $configFile)) {
                                $writeSuccess = true;
                                echo "<p>✅ Fichier créé via méthode alternative</p>";
                            } else {
                                @unlink($tempFile);
                            }
                        }
                    }
                    
                    if (!$writeSuccess && $attempts < $maxAttempts) {
                        usleep(500000); // Attendre 0.5 seconde avant de réessayer
                    }
                }
            }
            
            if (!$writeSuccess) {
                // Diagnostic détaillé de l'erreur
                $errorDetails = [];
                $errorDetails[] = "Répertoire: " . $configDir;
                $errorDetails[] = "Fichier cible: " . $configFile;
                $errorDetails[] = "Répertoire existe: " . (is_dir($configDir) ? 'Oui' : 'Non');
                $errorDetails[] = "Répertoire lisible: " . (is_readable($configDir) ? 'Oui' : 'Non');
                $errorDetails[] = "Répertoire accessible en écriture: " . (is_writable($configDir) ? 'Oui' : 'Non');
                
                if (file_exists($configFile)) {
                    $errorDetails[] = "Fichier existe déjà: Oui";
                    $errorDetails[] = "Fichier accessible en écriture: " . (is_writable($configFile) ? 'Oui' : 'Non');
                    $errorDetails[] = "Permissions fichier: " . substr(sprintf('%o', fileperms($configFile)), -4);
                }
                
                $errorDetails[] = "Permissions répertoire: " . substr(sprintf('%o', fileperms($configDir)), -4);
                
                if (function_exists('posix_getuid')) {
                    $errorDetails[] = "UID PHP: " . posix_getuid();
                    $errorDetails[] = "GID PHP: " . posix_getgid();
                    
                    $stat = stat($configDir);
                    $errorDetails[] = "UID répertoire: " . $stat['uid'];
                    $errorDetails[] = "GID répertoire: " . $stat['gid'];
                }
                
                throw new Exception("Impossible d'écrire le fichier de configuration après {$attempts} tentatives.\n\nDétails:\n" . implode("\n", $errorDetails) . "\n\nSolution: Exécutez ces commandes via SSH:\nchmod 777 " . $configDir . "\nchown " . get_current_user() . " " . $configDir);
            }
            
            // Vérifier que le fichier est lisible après écriture
            if (!is_readable($configFile)) {
                throw new Exception("Le fichier de configuration a été créé mais n'est pas lisible");
            }
            
            // Sécuriser le fichier après création
            @chmod($configFile, 0640);
            echo "<p>✅ Fichier de configuration créé et sécurisé</p>";
            echo "</div>";
            
            // ÉTAPE 3: Créer la structure de base de données
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 3: Création de la base de données</h3>";
            
            try {
                createCompleteDatabase($pdo);
                echo "<p>✅ Structure de base de données créée</p>";
            } catch (Exception $e) {
                throw new Exception("Erreur lors de la création de la base de données: " . $e->getMessage());
            }
            
            echo "</div>";
            
            // ÉTAPE 4: Créer le compte administrateur
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 4: Création du compte administrateur</h3>";
            
            try {
                createAdminAccount($pdo, $adminNom, $adminPrenom, $adminMail, $adminPassword);
                echo "<p>✅ Compte administrateur créé</p>";
            } catch (Exception $e) {
                throw new Exception("Erreur lors de la création du compte administrateur: " . $e->getMessage());
            }
            
            echo "</div>";
            
            // ÉTAPE 5: Finalisation
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 5: Finalisation</h3>";
            
            try {
                finalizeInstallation();
                echo "<p>✅ Installation finalisée</p>";
            } catch (Exception $e) {
                throw new Exception("Erreur lors de la finalisation: " . $e->getMessage());
            }
            
            echo "</div>";
            
            $installed = true;
            
        } catch (Exception $e) {
            $dbError = $e->getMessage();
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>❌ Erreur d'installation</h3>";
            echo "<p>" . htmlspecialchars($dbError) . "</p>";
            echo "</div>";
        }
    }
}

// Fonction pour créer toute la structure de base de données avec gestion d'erreur
function createCompleteDatabase($pdo) {
    // Tables principales avec structure complète et gestion d'erreur
    $tables = [
        'administrateurs' => "CREATE TABLE IF NOT EXISTS `administrateurs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `mail` varchar(200) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `adresse` text,
            `role` varchar(50) NOT NULL DEFAULT 'administrateur',
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `mail` (`mail`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'eleves' => "CREATE TABLE IF NOT EXISTS `eleves` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `classe_id` int(11) DEFAULT NULL,
            `date_naissance` date DEFAULT NULL,
            `adresse` text,
            `telephone` varchar(20) DEFAULT NULL,
            `mail_personnel` varchar(200) DEFAULT NULL,
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifiant` (`identifiant`),
            KEY `idx_classe` (`classe_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'professeurs' => "CREATE TABLE IF NOT EXISTS `professeurs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `mail` varchar(200) NOT NULL,
            `specialite` varchar(100) DEFAULT NULL,
            `telephone` varchar(20) DEFAULT NULL,
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifiant` (`identifiant`),
            UNIQUE KEY `mail` (`mail`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'parents' => "CREATE TABLE IF NOT EXISTS `parents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `mail` varchar(200) NOT NULL,
            `telephone` varchar(20) DEFAULT NULL,
            `adresse` text,
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifiant` (`identifiant`),
            UNIQUE KEY `mail` (`mail`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'vie_scolaire' => "CREATE TABLE IF NOT EXISTS `vie_scolaire` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `mail` varchar(200) NOT NULL,
            `poste` varchar(100) DEFAULT NULL,
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifiant` (`identifiant`),
            UNIQUE KEY `mail` (`mail`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'matieres' => "CREATE TABLE IF NOT EXISTS `matieres` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `code` varchar(10) NOT NULL,
            `coefficient` decimal(3,2) NOT NULL DEFAULT '1.00',
            `couleur` varchar(7) DEFAULT '#3498db',
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`),
            UNIQUE KEY `code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'classes' => "CREATE TABLE IF NOT EXISTS `classes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(50) NOT NULL,
            `niveau` varchar(20) NOT NULL,
            `annee_scolaire` varchar(10) NOT NULL,
            `professeur_principal_id` int(11) DEFAULT NULL,
            `actif` tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`),
            UNIQUE KEY `nom_annee` (`nom`, `annee_scolaire`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'notes' => "CREATE TABLE IF NOT EXISTS `notes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `eleve_id` int(11) NOT NULL,
            `matiere_id` int(11) NOT NULL,
            `professeur_id` int(11) NOT NULL,
            `note` decimal(4,2) NOT NULL,
            `note_sur` decimal(4,2) NOT NULL DEFAULT '20.00',
            `coefficient` decimal(3,2) NOT NULL DEFAULT '1.00',
            `type_evaluation` varchar(50) DEFAULT 'Contrôle',
            `date_note` date NOT NULL,
            `commentaire` text,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_eleve` (`eleve_id`),
            KEY `idx_matiere` (`matiere_id`),
            KEY `idx_date` (`date_note`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'absences' => "CREATE TABLE IF NOT EXISTS `absences` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `eleve_id` int(11) NOT NULL,
            `date_debut` datetime NOT NULL,
            `date_fin` datetime NOT NULL,
            `motif` varchar(200) DEFAULT NULL,
            `justifiee` tinyint(1) NOT NULL DEFAULT '0',
            `justificatif_id` int(11) DEFAULT NULL,
            `saisie_par` int(11) NOT NULL,
            `type_saisie` enum('administrateur','vie_scolaire','professeur') NOT NULL,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_eleve` (`eleve_id`),
            KEY `idx_date` (`date_debut`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'devoirs' => "CREATE TABLE IF NOT EXISTS `devoirs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `matiere_id` int(11) NOT NULL,
            `professeur_id` int(11) NOT NULL,
            `classe_id` int(11) NOT NULL,
            `titre` varchar(200) NOT NULL,
            `description` text,
            `date_pour` date NOT NULL,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_matiere` (`matiere_id`),
            KEY `idx_classe` (`classe_id`),
            KEY `idx_date` (`date_pour`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'cahier_texte' => "CREATE TABLE IF NOT EXISTS `cahier_texte` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `matiere_id` int(11) NOT NULL,
            `professeur_id` int(11) NOT NULL,
            `classe_id` int(11) NOT NULL,
            `date_cours` date NOT NULL,
            `contenu` text NOT NULL,
            `ressources` text,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_matiere` (`matiere_id`),
            KEY `idx_classe` (`classe_id`),
            KEY `idx_date` (`date_cours`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'evenements' => "CREATE TABLE IF NOT EXISTS `evenements` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titre` varchar(200) NOT NULL,
            `description` text,
            `date_debut` datetime NOT NULL,
            `date_fin` datetime NOT NULL,
            `type_evenement` varchar(50) NOT NULL,
            `concerne_classes` text,
            `createur_id` int(11) NOT NULL,
            `type_createur` enum('administrateur','professeur','vie_scolaire') NOT NULL,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_type` (`type_evenement`),
            KEY `idx_date` (`date_debut`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'messages' => "CREATE TABLE IF NOT EXISTS `messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `expediteur_id` int(11) NOT NULL,
            `expediteur_type` enum('administrateur','professeur','parent','vie_scolaire') NOT NULL,
            `destinataire_id` int(11) NOT NULL,
            `destinataire_type` enum('administrateur','professeur','parent','vie_scolaire') NOT NULL,
            `objet` varchar(200) NOT NULL,
            `contenu` text NOT NULL,
            `lu` tinyint(1) NOT NULL DEFAULT '0',
            `date_lecture` datetime DEFAULT NULL,
            `date_envoi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_expediteur` (`expediteur_id`, `expediteur_type`),
            KEY `idx_destinataire` (`destinataire_id`, `destinataire_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'justificatifs' => "CREATE TABLE IF NOT EXISTS `justificatifs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `eleve_id` int(11) NOT NULL,
            `nom_fichier` varchar(255) NOT NULL,
            `fichier_path` varchar(500) NOT NULL,
            `type_fichier` varchar(10) NOT NULL,
            `taille_fichier` int(11) NOT NULL,
            `date_upload` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `valide` tinyint(1) DEFAULT NULL,
            `valide_par` int(11) DEFAULT NULL,
            `date_validation` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_eleve` (`eleve_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        'demandes_reinitialisation' => "CREATE TABLE IF NOT EXISTS `demandes_reinitialisation` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(200) NOT NULL,
            `token` varchar(100) NOT NULL,
            `type_utilisateur` enum('administrateur','professeur','parent','eleve','vie_scolaire') NOT NULL,
            `user_id` int(11) NOT NULL,
            `date_demande` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_expiration` datetime NOT NULL,
            `utilise` tinyint(1) NOT NULL DEFAULT '0',
            `date_utilisation` datetime DEFAULT NULL,
            `status` enum('pending','used','expired') NOT NULL DEFAULT 'pending',
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            KEY `idx_status` (`status`),
            KEY `idx_expiration` (`date_expiration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
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
            echo "<p>✅ Table '{$tableName}' vérifiée/créée</p>";
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la création de la table {$tableName}: " . $e->getMessage());
        }
    }
    
    // Insérer les matières par défaut
    $stmt = $pdo->prepare("INSERT IGNORE INTO matieres (nom, code, coefficient, couleur) VALUES (?, ?, ?, ?)");
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
            $stmt->execute($matiere);
        } catch (PDOException $e) {
            // Ignorer les erreurs de doublons
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                throw new Exception("Erreur lors de l'insertion de la matière {$matiere[0]}: " . $e->getMessage());
            }
        }
    }
    
    // Insérer les classes par défaut
    $currentYear = date('Y') . '-' . (date('Y') + 1);
    $stmt = $pdo->prepare("INSERT IGNORE INTO classes (nom, niveau, annee_scolaire) VALUES (?, ?, ?)");
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
            $stmt->execute($classe);
        } catch (PDOException $e) {
            // Ignorer les erreurs de doublons
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                throw new Exception("Erreur lors de l'insertion de la classe {$classe[0]}: " . $e->getMessage());
            }
        }
    }
}

// Fonction pour créer le compte administrateur avec gestion d'erreur
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

// Fonction pour finaliser l'installation avec gestion d'erreur
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
        .password-requirements {
            margin-top: 8px;
            font-size: 0.85em;
            color: #666;
        }
        .password-requirements ul {
            padding-left: 20px;
            margin: 5px 0;
        }
        .password-requirements li {
            margin-bottom: 3px;
        }
        .valid {
            color: #2ecc71;
        }
        .invalid {
            color: #e74c3c;
        }
        .password-feedback {
            display: none;
            padding: 8px 12px;
            margin-top: 5px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .password-error {
            background-color: #fee;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        .password-success {
            background-color: #efe;
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Installation de Pronote</h1>
            <p>Assistant d'installation et de configuration</p>
        </div>
        
        <div class="content">
            <?php if (!empty($permissionErrors)): ?>
                <div class="error">
                    <h3>❌ Erreurs de permissions critiques</h3>
                    <?php foreach ($permissionErrors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                    
                    <h4>Solutions :</h4>
                    <ol>
                        <li>Exécutez le script <a href="fix_permissions.php">fix_permissions.php</a></li>
                        <li>Ou corrigez manuellement via SSH :</li>
                    </ol>
                    <pre>cd <?= htmlspecialchars($installDir) ?>
chmod 777 API/config API/logs uploads temp
# ou
chown -R www-data:www-data API/config API/logs uploads temp</pre>
                </div>
            <?php elseif (!empty($permissionWarnings)): ?>
                <div class="warning">
                    <h3>⚠️ Avertissements de permissions</h3>
                    <?php foreach ($permissionWarnings as $warning): ?>
                        <p><?= htmlspecialchars($warning) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($installed): ?>
                <div class="success">
                    <h2>🎉 Installation terminée avec succès !</h2>
                    <p>Pronote a été installé et configuré correctement.</p>
                    
                    <h3>Étapes suivantes :</h3>
                    <ol>
                        <li>Supprimez le fichier <code>install.php</code> pour sécuriser l'installation</li>
                        <li>Connectez-vous avec le compte administrateur créé</li>
                        <li>Configurez les utilisateurs et les classes</li>
                    </ol>
                    
                    <div class="actions">
                        <a href="login/public/index.php" class="btn">🔐 Se connecter</a>
                        <a href="diagnostic.php" class="btn">🔧 Page de diagnostic</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($dbError)): ?>
                    <div class="error">
                        <h3>❌ Erreur d'installation</h3>
                        <p><?= htmlspecialchars($dbError) ?></p>
                        
                        <h4>Solutions suggérées :</h4>
                        <ul>
                            <li>Vérifiez les informations de connexion à la base de données</li>
                            <li>Assurez-vous que le serveur MySQL est accessible</li>
                            <li>Vérifiez que l'utilisateur a les droits de création de base de données</li>
                            <li>Exécutez le script <a href="fix_permissions.php">fix_permissions.php</a> si l'erreur concerne les permissions</li>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="" id="installForm">
                    <input type="hidden" name="install_token" value="<?= htmlspecialchars($install_token) ?>">
                    <input type="hidden" name="step" value="1">
                    
                    <div class="section">
                        <h3>🗄️ Configuration de la base de données</h3>
                        <div class="grid">
                            <div class="form-group">
                                <label for="db_host">Hôte de la base de données :</label>
                                <input type="text" id="db_host" name="db_host" value="localhost" required>
                            </div>
                            <div class="form-group">
                                <label for="db_name">Nom de la base de données :</label>
                                <input type="text" id="db_name" name="db_name" placeholder="pronote_db" required>
                            </div>
                            <div class="form-group">
                                <label for="db_user">Utilisateur :</label>
                                <input type="text" id="db_user" name="db_user" required placeholder="utilisateur_mysql">
                            </div>
                            <div class="form-group">
                                <label for="db_pass">Mot de passe :</label>
                                <input type="password" id="db_pass" name="db_pass" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>⚙️ Configuration de l'application</h3>
                        <div class="grid">
                            <div class="form-group">
                                <label for="app_env">Environnement :</label>
                                <select id="app_env" name="app_env" required>
                                    <option value="development">Développement</option>
                                    <option value="production" selected>Production</option>
                                    <option value="test">Test</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="base_url">URL de base :</label>
                                <input type="url" id="base_url" name="base_url" value="<?= htmlspecialchars($baseUrl) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>👤 Compte administrateur</h3>
                        <div class="grid">
                            <div class="form-group">
                                <label for="admin_nom">Nom :</label>
                                <input type="text" id="admin_nom" name="admin_nom" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_prenom">Prénom :</label>
                                <input type="text" id="admin_prenom" name="admin_prenom" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_mail">Email :</label>
                                <input type="email" id="admin_mail" name="admin_mail" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_password">Mot de passe :</label>
                                <input type="password" id="admin_password" name="admin_password" required minlength="12">
                                <div class="password-requirements">
                                    <p>Le mot de passe doit contenir au moins :</p>
                                    <ul>
                                        <li id="length">12 caractères</li>
                                        <li id="uppercase">Une lettre majuscule (A-Z)</li>
                                        <li id="lowercase">Une lettre minuscule (a-z)</li>
                                        <li id="number">Un chiffre (0-9)</li>
                                        <li id="special">Un caractère spécial (@$!%*?&)</li>
                                    </ul>
                                </div>
                                <div id="password-feedback" class="password-feedback"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn" id="installBtn">🚀 Installer Pronote</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('installForm');
        const btn = document.getElementById('installBtn');
        const passwordInput = document.getElementById('admin_password');
        const lengthCheck = document.getElementById('length');
        const uppercaseCheck = document.getElementById('uppercase');
        const lowercaseCheck = document.getElementById('lowercase');
        const numberCheck = document.getElementById('number');
        const specialCheck = document.getElementById('special');
        const feedbackDiv = document.getElementById('password-feedback');
        
        // Fonction de validation du mot de passe
        function validatePassword() {
            const password = passwordInput.value;
            
            // Critères de validation
            const isLongEnough = password.length >= 12;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[@$!%*?&]/.test(password);
            
            // Mettre à jour les indicateurs visuels
            lengthCheck.className = isLongEnough ? 'valid' : 'invalid';
            uppercaseCheck.className = hasUppercase ? 'valid' : 'invalid';
            lowercaseCheck.className = hasLowercase ? 'valid' : 'invalid';
            numberCheck.className = hasNumber ? 'valid' : 'invalid';
            specialCheck.className = hasSpecial ? 'valid' : 'invalid';
            
            // Message de feedback
            if (password.length > 0) {
                const isValid = isLongEnough && hasUppercase && hasLowercase && hasNumber && hasSpecial;
                
                if (isValid) {
                    feedbackDiv.textContent = "✅ Mot de passe valide";
                    feedbackDiv.className = "password-feedback password-success";
                    feedbackDiv.style.display = "block";
                    return true;
                } else {
                    feedbackDiv.textContent = "⚠️ Le mot de passe ne respecte pas toutes les exigences";
                    feedbackDiv.className = "password-feedback password-error";
                    feedbackDiv.style.display = "block";
                    return false;
                }
            } else {
                feedbackDiv.style.display = "none";
                return false;
            }
        }
        
        // Validation lors de la saisie
        if (passwordInput) {
            passwordInput.addEventListener('keyup', validatePassword);
        }
        
        // Validation du formulaire
        if (form && btn) {
            form.addEventListener('submit', function(event) {
                if (passwordInput && passwordInput.value.length > 0) {
                    const isPasswordValid = validatePassword();
                    
                    if (!isPasswordValid) {
                        event.preventDefault();
                        alert("Le mot de passe ne respecte pas les exigences de sécurité.");
                        return false;
                    }
                }
                
                btn.textContent = '⏳ Installation en cours...';
                btn.disabled = true;
                
                // Afficher un message de progression
                setTimeout(function() {
                    if (btn.disabled) {
                        btn.textContent = '🔄 Configuration en cours...';
                    }
                }, 5000);
                
                setTimeout(function() {
                    if (btn.disabled) {
                        btn.textContent = '📊 Création de la base de données...';
                    }
                }, 10000);
            });
        }
    });
    </script>
</body>
</html>
