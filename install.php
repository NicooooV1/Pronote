<?php
/**
 * Script d'installation de Pronote - VERSION COMPLÈTE ET AUTO-DESTRUCTRICE
 * Utilise la nouvelle architecture API avec bootstrap et facades
 */

// Configuration de sécurité et gestion d'erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

register_shutdown_function('handleFatalError');

function handleFatalError() {
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>";
        echo "<h3>❌ Erreur fatale détectée</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>Fichier:</strong> " . htmlspecialchars($error['file']) . "</p>";
        echo "<p><strong>Ligne:</strong> " . $error['line'] . "</p>";
        echo "</div>";
    }
}

// Définir les en-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// SUPPRESSION DÉFINITIVE DES FICHIERS TEMPORAIRES
$filesToDelete = [
    'check_database_health.php',
    'fix_complete_database.php', 
    'test_permissions.php',
    'test_db_connection.php',
    'debug_ip.php',
    'fix_permissions.php',
    'diagnostic.php'
];

foreach ($filesToDelete as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

// Vérifier si l'installation est déjà terminée
$installLockFile = __DIR__ . '/install.lock';
if (file_exists($installLockFile)) {
    die('<div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px; font-family: Arial;">
        <h2>🔒 Installation déjà effectuée</h2>
        <p>Pronote a déjà été installé sur ce système.</p>
    </div>');
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
    die('Accès non autorisé depuis votre adresse IP: ' . $clientIP);
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
    
    if (!is_dir($path)) {
        if (!@mkdir($path, 0755, true)) {
            $permissionErrors[] = "Impossible de créer le dossier {$dir}";
            continue;
        }
    }
    
    // Test d'écriture
    $testFile = $path . '/test_' . time() . '.txt';
    $canWrite = @file_put_contents($testFile, 'test', LOCK_EX) !== false;
    
    if ($canWrite) {
        @unlink($testFile);
    } else {
        if ($dir === 'API/config') {
            $permissionErrors[] = "CRITIQUE: Le dossier {$dir} n'est pas accessible en écriture";
        } else {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation CSRF
    if (!isset($_POST['install_token']) || $_POST['install_token'] !== $_SESSION['install_token']) {
        $dbError = "Erreur de sécurité: Jeton invalide";
    } else {
        try {
            // Valider les entrées
            $dbHost = filter_input(INPUT_POST, 'db_host', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'localhost';
            $dbName = filter_input(INPUT_POST, 'db_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $dbUser = filter_input(INPUT_POST, 'db_user', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $dbPass = $_POST['db_pass'] ?? '';
            $appEnv = filter_input(INPUT_POST, 'app_env', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $baseUrlInput = filter_input(INPUT_POST, 'base_url', FILTER_SANITIZE_URL) ?: $baseUrl;
            
            $adminNom = filter_input(INPUT_POST, 'admin_nom', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $adminPrenom = filter_input(INPUT_POST, 'admin_prenom', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $adminMail = filter_input(INPUT_POST, 'admin_mail', FILTER_SANITIZE_EMAIL) ?: '';
            $adminPassword = $_POST['admin_password'] ?? '';
            
            if (!in_array($appEnv, ['development', 'production', 'test'])) {
                throw new Exception("Environnement non valide");
            }
            
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
                throw new Exception("Le mot de passe doit contenir au moins 8 caractères");
            }
            
            // ÉTAPE 1: Créer la configuration .env
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 1: Création de la configuration</h3>";
            
            $configFile = $installDir . '/.env';
            $configContent = "# Configuration Pronote - Généré le " . date('Y-m-d H:i:s') . "\n\n";
            $configContent .= "# Sécurité installation\n";
            $configContent .= "ALLOWED_INSTALL_IP={$clientIP}\n\n";
            $configContent .= "# Base de données\n";
            $configContent .= "DB_HOST={$dbHost}\n";
            $configContent .= "DB_NAME={$dbName}\n";
            $configContent .= "DB_USER={$dbUser}\n";
            $configContent .= "DB_PASS={$dbPass}\n\n";
            $configContent .= "# Application\n";
            $configContent .= "BASE_URL=" . rtrim($baseUrlInput, '/') . "\n";
            $configContent .= "APP_ENV={$appEnv}\n";
            $configContent .= "APP_DEBUG=" . ($appEnv === 'development' ? 'true' : 'false') . "\n";
            $configContent .= "APP_NAME=Pronote\n\n";
            $configContent .= "# Sécurité\n";
            $configContent .= "CSRF_TOKEN_LIFETIME=3600\n";
            $configContent .= "SESSION_LIFETIME=7200\n";
            $configContent .= "MAX_LOGIN_ATTEMPTS=5\n\n";
            $configContent .= "# Chemins\n";
            $configContent .= "LOGS_PATH={$installDir}/API/logs\n";
            
            if (@file_put_contents($configFile, $configContent, LOCK_EX) === false) {
                throw new Exception("Impossible d'écrire le fichier .env");
            }
            
            echo "<p>✅ Fichier .env créé</p>";
            echo "</div>";
            
            // ÉTAPE 2: Charger l'API avec la nouvelle configuration
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 2: Initialisation de l'API</h3>";
            
            // Charger le bootstrap de l'API
            $bootstrapPath = __DIR__ . '/API/bootstrap.php';
            if (!file_exists($bootstrapPath)) {
                throw new Exception("Fichier bootstrap.php non trouvé");
            }
            
            $app = require $bootstrapPath;
            echo "<p>✅ API bootstrap chargée</p>";
            
            // Importer les facades
            use Pronote\Core\Facades\DB;
            use Pronote\Core\Facades\Auth;
            use Pronote\Core\Facades\Log;
            
            echo "<p>✅ Facades chargées</p>";
            echo "</div>";
            
            // ÉTAPE 3: Créer/recréer la base de données
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 3: Gestion de la base de données</h3>";
            
            // Connexion sans base de données pour la créer
            $dsn = "mysql:host={$dbHost};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Supprimer si existe
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$dbName]);
            if ($stmt->fetch()) {
                $pdo->exec("DROP DATABASE `{$dbName}`");
                echo "<p>✅ Ancienne base supprimée</p>";
            }
            
            // Créer nouvelle base
            $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            echo "<p>✅ Base de données créée</p>";
            echo "</div>";
            
            // ÉTAPE 4: Créer la structure avec le QueryBuilder
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 4: Création de la structure</h3>";
            
            createDatabaseStructure($pdo);
            echo "<p>✅ Structure créée</p>";
            echo "</div>";
            
            // ÉTAPE 5: Créer le compte admin avec Auth
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 5: Compte administrateur</h3>";
            
            // Utiliser directement PDO car UserProvider n'est pas encore configuré
            $identifiant = 'admin';
            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO administrateurs (nom, prenom, mail, identifiant, mot_de_passe, role, actif) 
                VALUES (?, ?, ?, ?, ?, 'administrateur', 1)
            ");
            
            $stmt->execute([$adminNom, $adminPrenom, $adminMail, $identifiant, $hashedPassword]);
            
            echo "<p>✅ Administrateur créé (identifiant: {$identifiant})</p>";
            echo "</div>";
            
            // ÉTAPE 6: Finalisation
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 6: Finalisation</h3>";
            
            // Créer le fichier lock
            $lockContent = json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'version' => '1.0.0',
                'php_version' => PHP_VERSION
            ]);
            file_put_contents($installLockFile, $lockContent, LOCK_EX);
            
            // Protection .htaccess
            $htaccess = "# Protection fichiers sensibles\n";
            $htaccess .= "<Files ~ \"^(\.env|install\.php)$\">\n";
            $htaccess .= "    Order allow,deny\n";
            $htaccess .= "    Deny from all\n";
            $htaccess .= "</Files>\n";
            file_put_contents(__DIR__ . '/.htaccess', $htaccess, FILE_APPEND | LOCK_EX);
            
            echo "<p>✅ Installation finalisée</p>";
            echo "</div>";
            
            $installed = true;
            
        } catch (Exception $e) {
            $dbError = $e->getMessage();
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>❌ Erreur: " . htmlspecialchars($dbError) . "</h3>";
            echo "</div>";
        }
    }
}

// Fonction de création de structure
function createDatabaseStructure($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS `administrateurs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `mail` varchar(200) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `role` varchar(50) DEFAULT 'administrateur',
            `actif` tinyint(1) DEFAULT 1,
            `date_creation` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `mail` (`mail`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `eleves` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `classe` varchar(50) DEFAULT NULL,
            `mail` varchar(200) DEFAULT NULL,
            `actif` tinyint(1) DEFAULT 1,
            `date_creation` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `professeurs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `mail` varchar(200) NOT NULL,
            `actif` tinyint(1) DEFAULT 1,
            `date_creation` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `parents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `mail` varchar(200) NOT NULL,
            `actif` tinyint(1) DEFAULT 1,
            `date_creation` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `vie_scolaire` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `prenom` varchar(100) NOT NULL,
            `identifiant` varchar(50) NOT NULL,
            `mot_de_passe` varchar(255) NOT NULL,
            `mail` varchar(200) NOT NULL,
            `actif` tinyint(1) DEFAULT 1,
            `date_creation` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifiant` (`identifiant`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `matieres` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `code` varchar(10) NOT NULL,
            `coefficient` decimal(3,2) DEFAULT 1.00,
            `actif` tinyint(1) DEFAULT 1,
            PRIMARY KEY (`id`),
            UNIQUE KEY `code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `classes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nom` varchar(50) NOT NULL,
            `niveau` varchar(20) NOT NULL,
            `annee_scolaire` varchar(10) NOT NULL,
            `actif` tinyint(1) DEFAULT 1,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    
    // Données par défaut
    $pdo->exec("INSERT IGNORE INTO matieres (nom, code) VALUES 
        ('Mathématiques', 'MATH'),
        ('Français', 'FR'),
        ('Anglais', 'ANG')");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Pronote</title>
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
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
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
            <h1>🎓 Installation Pronote</h1>
            <p>Nouvelle architecture API</p>
        </div>
        
        <div class="content">
            <?php if (!empty($permissionErrors)): ?>
                <div class="error">
                    <h3>❌ Erreurs critiques</h3>
                    <?php foreach ($permissionErrors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($installed): ?>
                <div class="success">
                    <h2>🎉 Installation réussie !</h2>
                    <p>Pronote est prêt à être utilisé.</p>
                    <div style="margin-top: 20px;">
                        <a href="login/public/index.php" class="btn" style="display: inline-block; text-decoration: none;">
                            🔐 Se connecter
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($dbError)): ?>
                    <div class="error">
                        <h3>❌ Erreur</h3>
                        <p><?= htmlspecialchars($dbError) ?></p>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="install_token" value="<?= htmlspecialchars($install_token) ?>">
                    
                    <h3>🗄️ Base de données</h3>
                    <div class="grid">
                        <div class="form-group">
                            <label>Hôte :</label>
                            <input type="text" name="db_host" value="localhost" required>
                        </div>
                        <div class="form-group">
                            <label>Nom :</label>
                            <input type="text" name="db_name" required>
                        </div>
                        <div class="form-group">
                            <label>Utilisateur :</label>
                            <input type="text" name="db_user" required>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe :</label>
                            <input type="password" name="db_pass">
                        </div>
                    </div>
                    
                    <h3>⚙️ Application</h3>
                    <div class="grid">
                        <div class="form-group">
                            <label>Environnement :</label>
                            <select name="app_env" required>
                                <option value="production">Production</option>
                                <option value="development">Développement</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>URL de base :</label>
                            <input type="text" name="base_url" value="<?= htmlspecialchars($baseUrl) ?>">
                        </div>
                    </div>
                    
                    <h3>👤 Administrateur</h3>
                    <div class="grid">
                        <div class="form-group">
                            <label>Nom :</label>
                            <input type="text" name="admin_nom" required>
                        </div>
                        <div class="form-group">
                            <label>Prénom :</label>
                            <input type="text" name="admin_prenom" required>
                        </div>
                        <div class="form-group">
                            <label>Email :</label>
                            <input type="email" name="admin_mail" required>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe :</label>
                            <input type="password" name="admin_password" required minlength="8">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">🚀 Installer</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
