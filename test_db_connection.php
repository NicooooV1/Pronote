<?php
/**
 * Test de connexion à la base de données
 */

header('Content-Type: text/html; charset=UTF-8');

// Vérification de l'accès sécurisé améliorée
$allowedIPs = ['127.0.0.1', '::1'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

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

// Vérifier si c'est une IP du réseau local
$isLocalNetwork = false;
if ($clientIP) {
    $localNetworks = ['192.168.', '10.', '172.16.', '172.17.', '172.18.', '172.19.', '172.20.', '172.21.', '172.22.', '172.23.', '172.24.', '172.25.', '172.26.', '172.27.', '172.28.', '172.29.', '172.30.', '172.31.'];
    
    foreach ($localNetworks as $network) {
        if (strpos($clientIP, $network) === 0) {
            $isLocalNetwork = true;
            break;
        }
    }
}

if (!in_array($clientIP, $allowedIPs) && !$additionalIpAllowed && !$isLocalNetwork) {
    die('Accès non autorisé depuis: ' . $clientIP . '. Ajoutez votre IP au fichier .env');
}

// Style CSS simple
echo "<!DOCTYPE html><html><head><title>Test Connexion DB</title><meta charset='UTF-8'>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 600px; background: white; padding: 20px; border-radius: 8px; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    th { background: #f8f9fa; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔌 Test de Connexion Base de Données</h1>";

// Charger la configuration UNIQUEMENT depuis l'API centralisée
$config = [];
try {
    require_once __DIR__ . '/API/core.php';
    
    if (defined('DB_HOST')) $config['host'] = DB_HOST;
    if (defined('DB_NAME')) $config['name'] = DB_NAME;
    if (defined('DB_USER')) $config['user'] = DB_USER;
    if (defined('DB_PASS')) $config['pass'] = DB_PASS;
} catch (Exception $e) {
    echo "<p class='error'>❌ Impossible de charger la configuration centralisée</p>";
    echo "<p>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Veuillez d'abord installer l'application.</p>";
    echo "</div></body></html>";
    exit;
}

if (empty($config)) {
    echo "<p class='error'>❌ Configuration de base de données non trouvée</p>";
    echo "<p>Veuillez d'abord installer l'application.</p>";
    echo "</div></body></html>";
    exit;
}

echo "<h2>Configuration détectée</h2>";
echo "<table>";
echo "<tr><td>Hôte</td><td>" . htmlspecialchars($config['host'] ?? 'Non défini') . "</td></tr>";
echo "<tr><td>Base de données</td><td>" . htmlspecialchars($config['name'] ?? 'Non défini') . "</td></tr>";
echo "<tr><td>Utilisateur</td><td>" . htmlspecialchars($config['user'] ?? 'Non défini') . "</td></tr>";
echo "<tr><td>Mot de passe</td><td>" . (isset($config['pass']) ? '***' : 'Non défini') . "</td></tr>";
echo "</table>";

// Test de connexion
echo "<h2>Test de connexion</h2>";

try {
    $start = microtime(true);
    $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10
    ];
    
    $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
    $time = round((microtime(true) - $start) * 1000, 2);
    
    echo "<p class='success'>✅ Connexion réussie en {$time}ms</p>";
    
    // Test de requête
    $stmt = $pdo->query('SELECT VERSION() as version');
    $version = $stmt->fetch();
    echo "<p>Version MySQL: " . htmlspecialchars($version['version']) . "</p>";
    
    // Lister les tables
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tables trouvées (" . count($tables) . ")</h3>";
    if (!empty($tables)) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ Aucune table trouvée - Base de données vide</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erreur de connexion: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Suggestions de résolution
    echo "<h3>Solutions possibles:</h3>";
    echo "<ul>";
    echo "<li>Vérifiez que MySQL/MariaDB est démarré</li>";
    echo "<li>Vérifiez les informations de connexion</li>";
    echo "<li>Vérifiez que la base de données existe</li>";
    echo "<li>Vérifiez les permissions de l'utilisateur</li>";
    echo "</ul>";
}

echo "<div style='margin-top: 30px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 5px;'>";
echo "<h3>🔒 Sécurité</h3>";
echo "<p>Supprimez ce fichier après utilisation:</p>";
echo "<p><code>rm " . __FILE__ . "</code></p>";
echo "</div>";

echo "</div></body></html>";
?>
