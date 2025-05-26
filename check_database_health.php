<?php
/**
 * Script de vérification de santé de la base de données
 * SUPPRIMER après utilisation
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html><html><head><title>Vérification Santé Base de Données</title><meta charset='UTF-8'>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; }
.warning { color: orange; }
.error { color: red; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>
</head><body>";

echo "<h1>Vérification de santé de la base de données Pronote</h1>";

// Configuration - MODIFIER avec vos paramètres
$config = [
    'host' => 'localhost',
    'dbname' => 'pronote',
    'user' => 'pronote_user',
    'pass' => 'VOTRE_MOT_DE_PASSE_ICI' // REMPLACER par votre mot de passe
];

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<p class='success'>✓ Connexion à la base de données réussie</p>";
    
    // Tables essentielles à vérifier
    $essentialTables = [
        'administrateurs' => ['nom', 'prenom', 'mail', 'identifiant', 'mot_de_passe', 'role', 'actif'],
        'eleves' => ['nom', 'prenom', 'mail', 'identifiant', 'mot_de_passe', 'classe'],
        'professeurs' => ['nom', 'prenom', 'mail', 'identifiant', 'mot_de_passe', 'matiere'],
        'parents' => ['nom', 'prenom', 'mail', 'identifiant', 'mot_de_passe'],
        'vie_scolaire' => ['nom', 'prenom', 'mail', 'identifiant', 'mot_de_passe'],
        'notes' => ['id'],
        'absences' => ['id'],
        'devoirs' => ['id'],
        'cahier_texte' => ['id'],
        'evenements' => ['id'],
        'messages' => ['id']
    ];
    
    $overallHealth = true;
    $issues = [];
    $tableStats = [];
    
    echo "<div class='section'>";
    echo "<h2>Vérification des tables essentielles</h2>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Existe</th><th>Colonnes</th><th>Enregistrements</th><th>Statut</th></tr>";
    
    foreach ($essentialTables as $tableName => $requiredColumns) {
        $tableStatus = 'success';
        $statusText = '✓ OK';
        
        // Vérifier existence de la table
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            $tableStatus = 'error';
            $statusText = '✗ Table manquante';
            $overallHealth = false;
            $issues[] = "Table '{$tableName}' manquante";
        } else {
            // Vérifier les colonnes
            $stmt = $pdo->prepare("DESCRIBE {$tableName}");
            $stmt->execute();
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $missingColumns = array_diff($requiredColumns, $existingColumns);
            
            // Compter les enregistrements
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$tableName}");
            $countStmt->execute();
            $recordCount = $countStmt->fetch()['count'];
            
            $tableStats[$tableName] = [
                'exists' => true,
                'columns' => count($existingColumns),
                'records' => $recordCount,
                'missing_columns' => $missingColumns
            ];
            
            if (!empty($missingColumns)) {
                $tableStatus = 'warning';
                $statusText = '⚠ Colonnes manquantes: ' . implode(', ', $missingColumns);
                $issues[] = "Table '{$tableName}' manque les colonnes: " . implode(', ', $missingColumns);
            }
        }
        
        echo "<tr>";
        echo "<td>{$tableName}</td>";
        echo "<td class='" . ($tableExists ? 'success' : 'error') . "'>" . ($tableExists ? 'Oui' : 'Non') . "</td>";
        echo "<td>" . ($tableExists ? count($existingColumns) . ' colonnes' : 'N/A') . "</td>";
        echo "<td>" . ($tableExists ? $recordCount : 'N/A') . "</td>";
        echo "<td class='{$tableStatus}'>{$statusText}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
    
    // Résumé de la santé globale
    echo "<div class='section'>";
    echo "<h2>Résumé de la santé de la base de données</h2>";
    
    if ($overallHealth && empty($issues)) {
        echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "<h3 class='success'>✓ Base de données en bonne santé</h3>";
        echo "<p>Toutes les tables essentielles sont présentes et correctement structurées.</p>";
        echo "<p><strong>Vous pouvez procéder à l'utilisation de l'application.</strong></p>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "<h3 class='error'>⚠ Problèmes détectés</h3>";
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li class='error'>{$issue}</li>";
        }
        echo "</ul>";
        echo "<p><strong>Actions recommandées:</strong></p>";
        echo "<ol>";
        echo "<li>Exécutez le script <a href='fix_complete_database.php'>fix_complete_database.php</a> pour corriger les problèmes</li>";
        echo "<li>Si les problèmes persistent, utilisez l'outil de reconstruction: <a href='API/tools/rebuild_db.php'>rebuild_db.php</a></li>";
        echo "<li>En dernier recours, relancez l'installation complète après avoir supprimé le fichier install.lock</li>";
        echo "</ol>";
        echo "</div>";
    }
    
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3 class='error'>Erreur de connexion à la base de données</h3>";
    echo "<p>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Vérifiez:</strong></p>";
    echo "<ul>";
    echo "<li>Les paramètres de connexion dans ce script</li>";
    echo "<li>Que le serveur MySQL/MariaDB fonctionne</li>";
    echo "<li>Que l'utilisateur a les bonnes permissions</li>";
    echo "<li>Que la base de données existe</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<p style='margin-top: 30px;'><strong>Important :</strong> Supprimez ce fichier après utilisation pour des raisons de sécurité.</p>";
echo "</body></html>";
?>
