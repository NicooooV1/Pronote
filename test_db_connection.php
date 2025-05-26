<?php
/**
 * Test de connexion à la base de données
 */

header('Content-Type: text/html; charset=UTF-8');

// Vérifier l'accès sécurisé
$allowedIPs = ['127.0.0.1', '::1', '192.168.1.80', '82.65.180.135', '192.168.1.163', '192.168.1.193'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($clientIP, $allowedIPs)) {
    die('Accès non autorisé depuis: ' . $clientIP);
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

// Charger la configuration
$config = [];
$configFile = __DIR__ . '/API/config/env.php';

if (file_exists($configFile)) {
    include $configFile;
    if (defined('DB_HOST')) $config['host'] = DB_HOST;
    if (defined('DB_NAME')) $config['name'] = DB_NAME;
    if (defined('DB_USER')) $config['user'] = DB_USER;
    if (defined('DB_PASS')) $config['pass'] = DB_PASS;
} else {
    echo "<p class='error'>❌ Fichier de configuration non trouvé</p>";
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
