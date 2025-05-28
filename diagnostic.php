<?php
/**
 * Script de diagnostic pour Pronote
 * Ce script permet de vérifier l'état de l'installation et des composants nécessaires
 */

// Configuration de sécurité
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Vérification de l'adresse IP pour des raisons de sécurité
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
    die('Accès non autorisé depuis votre adresse IP: ' . $clientIP . '. Pour autoriser votre adresse IP, créez un fichier .env avec ALLOWED_INSTALL_IP=' . $clientIP);
}

// Fonction pour tester la connexion à la base de données
function testDatabaseConnection() {
    $configFile = __DIR__ . '/API/config/env.php';
    
    if (!file_exists($configFile)) {
        return [
            'status' => 'error',
            'message' => "Le fichier de configuration n'existe pas. L'installation n'est peut-être pas terminée."
        ];
    }
    
    // Inclure le fichier de configuration de manière sécurisée
    require_once $configFile;
    
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        return [
            'status' => 'error',
            'message' => "Configuration de base de données incomplète dans le fichier de configuration."
        ];
    }
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Vérifier si les tables existent
        $tables = [
            'administrateurs', 'eleves', 'professeurs', 'classes', 
            'notes', 'absences', 'matieres'
        ];
        
        $existingTables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $existingTables[] = $row[0];
        }
        
        $missingTables = array_diff($tables, $existingTables);
        
        if (!empty($missingTables)) {
            return [
                'status' => 'warning',
                'message' => "Connexion à la base de données réussie, mais les tables suivantes sont manquantes : " . implode(', ', $missingTables)
            ];
        }
        
        // Vérifier si au moins un administrateur existe
        $stmt = $pdo->query("SELECT COUNT(*) FROM administrateurs");
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount === 0) {
            return [
                'status' => 'warning',
                'message' => "Connexion à la base de données réussie, mais aucun administrateur n'est configuré."
            ];
        }
        
        return [
            'status' => 'success',
            'message' => "Connexion à la base de données réussie. {$adminCount} administrateur(s) trouvé(s)."
        ];
        
    } catch (PDOException $e) {
        return [
            'status' => 'error',
            'message' => "Erreur de connexion à la base de données : " . $e->getMessage()
        ];
    }
}

// Fonction pour vérifier les permissions des répertoires
function checkDirectoryPermissions() {
    $directories = [
        'API/logs',
        'API/config',
        'uploads',
        'temp',
        'login/logs'
    ];
    
    $results = [];
    $allOk = true;
    
    foreach ($directories as $dir) {
        $path = __DIR__ . '/' . $dir;
        $result = [
            'directory' => $dir,
            'exists' => is_dir($path),
            'writable' => false,
            'permissions' => null
        ];
        
        if ($result['exists']) {
            $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
            $result['writable'] = is_writable($path);
            
            // Test d'écriture réel
            if ($result['writable']) {
                $testFile = $path . '/test_diag_' . uniqid() . '.tmp';
                $writeResult = @file_put_contents($testFile, 'test');
                
                if ($writeResult === false) {
                    $result['writable'] = false;
                } else {
                    @unlink($testFile);
                }
            }
            
            if (!$result['writable']) {
                $allOk = false;
            }
        } else {
            $allOk = false;
        }
        
        $results[$dir] = $result;
    }
    
    return [
        'directories' => $results,
        'all_ok' => $allOk
    ];
}

// Fonction pour vérifier les extensions PHP requises
function checkPhpExtensions() {
    $requiredExtensions = [
        'pdo', 'pdo_mysql', 'json', 'mbstring', 'session', 'curl'
    ];
    
    $results = [];
    $allOk = true;
    
    foreach ($requiredExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $results[$ext] = $loaded;
        
        if (!$loaded) {
            $allOk = false;
        }
    }
    
    return [
        'extensions' => $results,
        'all_ok' => $allOk
    ];
}

// Fonction pour vérifier l'état de l'installation
function checkInstallationStatus() {
    $installLockFile = __DIR__ . '/install.lock';
    $configFile = __DIR__ . '/API/config/env.php';
    
    $status = [
        'lock_file_exists' => file_exists($installLockFile),
        'config_file_exists' => file_exists($configFile),
        'install_script_exists' => file_exists(__DIR__ . '/install.php')
    ];
    
    if ($status['lock_file_exists']) {
        $lockContent = file_get_contents($installLockFile);
        $lockData = json_decode($lockContent, true);
        $status['installation_data'] = $lockData;
    }
    
    return $status;
}

// Exécution des tests
$dbStatus = testDatabaseConnection();
$dirPermissions = checkDirectoryPermissions();
$phpExtensions = checkPhpExtensions();
$installStatus = checkInstallationStatus();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Pronote</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .panel {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .panel h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        .success, .error, .warning {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        code {
            background: #f0f0f0;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .btn.danger {
            background: #e74c3c;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .actions {
            margin-top: 20px;
            text-align: center;
        }
        .system-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        @media (max-width: 700px) {
            .system-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic du système Pronote</h1>
    
    <div class="panel">
        <h2>🖥️ Informations système</h2>
        <div class="system-info">
            <div>
                <p><strong>Système d'exploitation :</strong> <?= PHP_OS ?></p>
                <p><strong>Version PHP :</strong> <?= PHP_VERSION ?></p>
                <p><strong>Serveur web :</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Non détecté' ?></p>
                <p><strong>Heure du serveur :</strong> <?= date('Y-m-d H:i:s') ?></p>
            </div>
            <div>
                <p><strong>Mémoire limite PHP :</strong> <?= ini_get('memory_limit') ?></p>
                <p><strong>Temps d'exécution max :</strong> <?= ini_get('max_execution_time') ?> secondes</p>
                <p><strong>Upload max :</strong> <?= ini_get('upload_max_filesize') ?></p>
                <p><strong>Post max :</strong> <?= ini_get('post_max_size') ?></p>
            </div>
        </div>
    </div>
    
    <div class="panel">
        <h2>📊 Statut de l'installation</h2>
        <?php if ($installStatus['lock_file_exists'] && $installStatus['config_file_exists']): ?>
            <div class="success">
                <p><strong>✅ Installation complétée</strong></p>
                <?php if (isset($installStatus['installation_data'])): ?>
                    <p>Date d'installation: <?= $installStatus['installation_data']['installed_at'] ?? 'Inconnue' ?></p>
                    <p>Version installée: <?= $installStatus['installation_data']['version'] ?? 'Inconnue' ?></p>
                <?php endif; ?>
                
                <?php if ($installStatus['install_script_exists']): ?>
                    <div class="warning">
                        <p><strong>⚠️ Le script d'installation est toujours présent.</strong> Pour des raisons de sécurité, il est recommandé de le supprimer.</p>
                    </div>
                <?php else: ?>
                    <p>✅ Script d'installation supprimé (bon pour la sécurité)</p>
                <?php endif; ?>
            </div>
        <?php elseif ($installStatus['config_file_exists'] && !$installStatus['lock_file_exists']): ?>
            <div class="warning">
                <p><strong>⚠️ Installation partiellement complétée</strong></p>
                <p>Le fichier de configuration existe, mais le fichier de verrouillage n'a pas été trouvé.</p>
                <p>Il est recommandé de terminer l'installation ou de créer manuellement le fichier de verrouillage.</p>
            </div>
        <?php else: ?>
            <div class="error">
                <p><strong>❌ Installation incomplète</strong></p>
                <p>Le fichier de configuration n'a pas été trouvé. Veuillez lancer l'installation.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h2>🗃️ Base de données</h2>
        <div class="<?= $dbStatus['status'] ?>">
            <p><strong><?= $dbStatus['status'] === 'success' ? '✅' : ($dbStatus['status'] === 'warning' ? '⚠️' : '❌') ?> <?= $dbStatus['message'] ?></strong></p>
        </div>
        
        <?php if ($dbStatus['status'] === 'error'): ?>
            <div class="actions">
                <a href="install.php" class="btn">Lancer l'installation</a>
                <a href="fix_permissions.php" class="btn">Corriger les permissions</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h2>📁 Permissions des répertoires</h2>
        <?php if ($dirPermissions['all_ok']): ?>
            <div class="success">
                <p><strong>✅ Toutes les permissions sont correctement configurées</strong></p>
            </div>
        <?php else: ?>
            <div class="warning">
                <p><strong>⚠️ Certains répertoires ont des problèmes de permissions</strong></p>
            </div>
        <?php endif; ?>
        
        <table>
            <tr>
                <th>Répertoire</th>
                <th>Existe</th>
                <th>Accessible en écriture</th>
                <th>Permissions</th>
            </tr>
            <?php foreach ($dirPermissions['directories'] as $dir => $info): ?>
                <tr>
                    <td><code><?= htmlspecialchars($dir) ?></code></td>
                    <td><?= $info['exists'] ? '✅ Oui' : '❌ Non' ?></td>
                    <td><?= $info['writable'] ? '✅ Oui' : '❌ Non' ?></td>
                    <td><?= $info['permissions'] ?? 'N/A' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        
        <?php if (!$dirPermissions['all_ok']): ?>
            <div class="actions">
                <a href="fix_permissions.php" class="btn">Corriger les permissions</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h2>🧩 Extensions PHP</h2>
        <?php if ($phpExtensions['all_ok']): ?>
            <div class="success">
                <p><strong>✅ Toutes les extensions requises sont installées</strong></p>
            </div>
        <?php else: ?>
            <div class="error">
                <p><strong>❌ Certaines extensions requises sont manquantes</strong></p>
            </div>
        <?php endif; ?>
        
        <table>
            <tr>
                <th>Extension</th>
                <th>Statut</th>
            </tr>
            <?php foreach ($phpExtensions['extensions'] as $ext => $loaded): ?>
                <tr>
                    <td><code><?= htmlspecialchars($ext) ?></code></td>
                    <td><?= $loaded ? '✅ Installée' : '❌ Manquante' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="panel">
        <h2>🛠️ Actions</h2>
        <div class="actions">
            <?php if (!$installStatus['lock_file_exists'] || !$installStatus['config_file_exists']): ?>
                <a href="install.php" class="btn">Lancer l'installation</a>
            <?php endif; ?>
            <a href="fix_permissions.php" class="btn">Corriger les permissions</a>
            <a href="login/public/index.php" class="btn">Accéder à l'application</a>
            <?php if ($installStatus['install_script_exists']): ?>
                <a href="#" class="btn danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer le script d\'installation?') ? window.location.href = '?remove_install=1' : false;">Supprimer le script d'installation</a>
            <?php endif; ?>
        </div>
    </div>
    
    <footer style="text-align: center; margin-top: 30px; font-size: 0.9em; color: #777;">
        Diagnostic généré le <?= date('d/m/Y à H:i:s') ?>
    </footer>
</body>
</html>
<?php
// Gestion de la suppression du script d'installation
if (isset($_GET['remove_install']) && $_GET['remove_install'] === '1' && $installStatus['install_script_exists']) {
    // Créer une sauvegarde du script d'installation
    $backupFile = __DIR__ . '/install.php.backup';
    if (!file_exists($backupFile)) {
        copy(__DIR__ . '/install.php', $backupFile);
    }
    
    // Remplacer le contenu du fichier par une redirection
    $redirectContent = '<?php
// Le script d\'installation a été supprimé pour des raisons de sécurité
header("Location: login/public/index.php");
exit;
?>';
    
    file_put_contents(__DIR__ . '/install.php', $redirectContent);
    
    // Redirection vers le diagnostic
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>