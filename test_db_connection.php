<?php
/**
 * Script de test de connexion à la base de données
 * SUPPRIMER après résolution du problème
 */

echo "<h1>Test de connexion à la base de données</h1>";

// Remplacez 'VOTRE_MOT_DE_PASSE' par le mot de passe que vous avez défini
$config = [
    'host' => 'localhost',
    'dbname' => 'pronote',
    'user' => 'pronote_user',
    'pass' => 'VotreMotDePasseFort123!' // Remplacez par votre mot de passe
];

echo "<h2>Configuration testée</h2>";
echo "<ul>";
echo "<li>Hôte : " . htmlspecialchars($config['host']) . "</li>";
echo "<li>Base de données : " . htmlspecialchars($config['dbname']) . "</li>";
echo "<li>Utilisateur : " . htmlspecialchars($config['user']) . "</li>";
echo "<li>Mot de passe : ***</li>";
echo "</ul>";

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    echo "<p style='color: green;'><strong>✓ Connexion réussie !</strong></p>";
    
    // Test d'une requête simple
    $version = $pdo->query('SELECT VERSION() as version')->fetch();
    echo "<p>Version MariaDB : " . htmlspecialchars($version['version']) . "</p>";
    
    // Tester la création d'une table temporaire
    $pdo->exec("CREATE TEMPORARY TABLE test_table (id INT, name VARCHAR(50))");
    echo "<p style='color: green;'>✓ Permissions d'écriture OK</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>✗ Connexion échouée :</strong></p>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<p style='color: orange;'><strong>Solution :</strong> Vérifiez le nom d'utilisateur et le mot de passe.</p>";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<p style='color: orange;'><strong>Solution :</strong> La base de données 'pronote' n'existe pas. Créez-la d'abord.</p>";
    }
}

echo "<p style='color: red;'><strong>IMPORTANT :</strong> Supprimez ce fichier après avoir résolu le problème.</p>";
?>
