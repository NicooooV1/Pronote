<?php
/**
 * Script de test des permissions d'écriture
 * À SUPPRIMER après résolution du problème
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test des permissions</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>Test des permissions d'écriture</h1>
    
    <?php
    $directories = [
        'API' => __DIR__ . '/API',
        'API/config' => __DIR__ . '/API/config',
        'API/logs' => __DIR__ . '/API/logs',
        'uploads' => __DIR__ . '/uploads',
        'temp' => __DIR__ . '/temp'
    ];
    
    echo "<h2>Test des répertoires</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Répertoire</th><th>Existe</th><th>Lisible</th><th>Écriture</th><th>Test création fichier</th><th>Actions</th></tr>";
    
    foreach ($directories as $name => $path) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($name) . "</td>";
        
        $exists = is_dir($path);
        echo "<td style='color: " . ($exists ? 'green' : 'red') . "'>" . ($exists ? 'Oui' : 'Non') . "</td>";
        
        $readable = $exists ? is_readable($path) : false;
        echo "<td style='color: " . ($readable ? 'green' : 'red') . "'>" . ($readable ? 'Oui' : 'Non') . "</td>";
        
        $writable = $exists ? is_writable($path) : false;
        echo "<td style='color: " . ($writable ? 'green' : 'red') . "'>" . ($writable ? 'Oui' : 'Non') . "</td>";
        
        // Test de création de fichier
        $canCreateFile = false;
        $testFile = $path . '/test_' . time() . '.txt';
        if ($exists && $writable) {
            try {
                $result = file_put_contents($testFile, 'test');
                if ($result !== false) {
                    $canCreateFile = true;
                    @unlink($testFile); // Supprimer le fichier de test
                }
            } catch (Exception $e) {
                // Ignore
            }
        }
        echo "<td style='color: " . ($canCreateFile ? 'green' : 'red') . "'>" . ($canCreateFile ? 'Oui' : 'Non') . "</td>";
        
        // Actions recommandées
        echo "<td>";
        if (!$exists) {
            echo "<code>mkdir -p " . htmlspecialchars($path) . "</code><br>";
            echo "<code>chmod 755 " . htmlspecialchars($path) . "</code>";
        } elseif (!$writable) {
            echo "<code>chmod 755 " . htmlspecialchars($path) . "</code><br>";
            echo "<code>chown webadmin:www-data " . htmlspecialchars($path) . "</code>";
        } else {
            echo "✓ OK";
        }
        echo "</td>";
        
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Test spécifique de création du fichier de configuration
    echo "<h2>Test de création du fichier de configuration</h2>";
    $configDir = __DIR__ . '/API/config';
    $configFile = $configDir . '/test_env.php';
    
    echo "<p><strong>Répertoire de configuration :</strong> " . htmlspecialchars($configDir) . "</p>";
    
    if (!is_dir($configDir)) {
        echo "<p style='color: red;'>Le répertoire n'existe pas. Commandes à exécuter :</p>";
        echo "<pre>";
        echo "mkdir -p " . htmlspecialchars($configDir) . "\n";
        echo "chmod 755 " . htmlspecialchars($configDir) . "\n";
        echo "chown webadmin:www-data " . htmlspecialchars($configDir);
        echo "</pre>";
    } else {
        $testContent = "<?php\n// Fichier de test\ndefine('TEST', true);\n";
        
        try {
            $result = file_put_contents($configFile, $testContent, LOCK_EX);
            if ($result !== false) {
                echo "<p style='color: green;'>✓ Création de fichier réussie</p>";
                @unlink($configFile); // Nettoyer
            } else {
                echo "<p style='color: red;'>✗ Échec de création de fichier</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Erreur lors de la création : " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        // Informations détaillées sur les permissions
        $perms = fileperms($configDir);
        echo "<p><strong>Permissions actuelles :</strong> " . sprintf('%o', $perms & 0777) . "</p>";
        
        if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
            $owner = posix_getpwuid(fileowner($configDir));
            $group = posix_getgrgid(filegroup($configDir));
            echo "<p><strong>Propriétaire :</strong> " . htmlspecialchars($owner['name']) . "</p>";
            echo "<p><strong>Groupe :</strong> " . htmlspecialchars($group['name']) . "</p>";
        }
    }
    ?>
    
    <h2>Commandes de correction</h2>
    <pre>
# Créer tous les répertoires nécessaires
mkdir -p /var/www/html/pronote/API/config
mkdir -p /var/www/html/pronote/API/logs
mkdir -p /var/www/html/pronote/uploads
mkdir -p /var/www/html/pronote/temp

# Définir les permissions correctes
chmod 755 /var/www/html/pronote/API
chmod 755 /var/www/html/pronote/API/config
chmod 775 /var/www/html/pronote/API/logs
chmod 775 /var/www/html/pronote/uploads
chmod 775 /var/www/html/pronote/temp

# Définir le propriétaire (adapter selon votre configuration)
chown -R webadmin:www-data /var/www/html/pronote/API
chown -R webadmin:www-data /var/www/html/pronote/uploads
chown -R webadmin:www-data /var/www/html/pronote/temp
    </pre>
    
    <p style="color: red;"><strong>IMPORTANT :</strong> Supprimez ce fichier après avoir résolu le problème.</p>
</body>
</html>
