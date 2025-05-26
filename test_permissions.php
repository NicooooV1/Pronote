<?php
/**
 * Test rapide des permissions - Version fonctionnelle
 * Ce script teste et corrige automatiquement les permissions
 */

header('Content-Type: text/html; charset=UTF-8');

// Vérifier l'accès sécurisé
$allowedIPs = ['127.0.0.1', '::1', '192.168.1.80', '82.65.180.135', '192.168.1.163', '192.168.1.193'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($clientIP, $allowedIPs)) {
    die('Accès non autorisé depuis: ' . $clientIP);
}

echo "<!DOCTYPE html><html><head><title>Test et Correction des Permissions</title><meta charset='UTF-8'>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 800px; background: white; padding: 20px; border-radius: 8px; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #fd7e14; font-weight: bold; }
    .action { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
    pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    th { background: #f8f9fa; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔧 Test et Correction des Permissions</h1>";

$directories = [
    'API',
    'API/config', 
    'API/logs',
    'uploads',
    'temp',
    'login/logs'
];

$results = [];
$allGood = true;

// Phase 1: Diagnostic
echo "<h2>📊 Phase 1: Diagnostic</h2>";
echo "<table>";
echo "<tr><th>Répertoire</th><th>Existe</th><th>Permissions</th><th>Propriétaire</th><th>Écritable</th><th>Test</th></tr>";

foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    $result = [
        'path' => $path,
        'exists' => is_dir($path),
        'readable' => false,
        'writable' => false,
        'write_test' => false,
        'permissions' => 'N/A',
        'owner' => 'N/A'
    ];
    
    if ($result['exists']) {
        $stat = stat($path);
        $result['permissions'] = sprintf('%o', $stat['mode'] & 0777);
        $result['readable'] = is_readable($path);
        $result['writable'] = is_writable($path);
        
        $owner = posix_getpwuid($stat['uid']);
        $result['owner'] = $owner ? $owner['name'] : "UID:{$stat['uid']}";
        
        // Test d'écriture réel
        $testFile = $path . '/test_' . time() . '.txt';
        $result['write_test'] = @file_put_contents($testFile, 'test') !== false;
        if ($result['write_test']) {
            @unlink($testFile);
        }
    }
    
    if (!$result['exists'] || !$result['write_test']) {
        $allGood = false;
    }
    
    $results[$dir] = $result;
    
    // Affichage du résultat
    echo "<tr>";
    echo "<td>{$dir}</td>";
    echo "<td class='" . ($result['exists'] ? 'success' : 'error') . "'>" . ($result['exists'] ? 'Oui' : 'Non') . "</td>";
    echo "<td>{$result['permissions']}</td>";
    echo "<td>{$result['owner']}</td>";
    echo "<td class='" . ($result['writable'] ? 'success' : 'error') . "'>" . ($result['writable'] ? 'Oui' : 'Non') . "</td>";
    echo "<td class='" . ($result['write_test'] ? 'success' : 'error') . "'>" . ($result['write_test'] ? 'OK' : 'ÉCHEC') . "</td>";
    echo "</tr>";
}

echo "</table>";

// Phase 2: Correction automatique
echo "<h2>🔧 Phase 2: Correction Automatique</h2>";

$fixedCount = 0;
$totalCount = count($directories);

foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    $result = $results[$dir];
    
    echo "<h3>Correction de: {$dir}</h3>";
    
    // Créer le répertoire s'il n'existe pas
    if (!$result['exists']) {
        if (@mkdir($path, 0755, true)) {
            echo "<p class='success'>✅ Répertoire créé</p>";
        } else {
            echo "<p class='error'>❌ Impossible de créer le répertoire</p>";
            continue;
        }
    }
    
    // Tester l'écriture
    $testFile = $path . '/test_correction_' . time() . '.txt';
    $canWrite = @file_put_contents($testFile, 'test correction') !== false;
    
    if ($canWrite) {
        @unlink($testFile);
        echo "<p class='success'>✅ Écriture déjà fonctionnelle</p>";
        $fixedCount++;
        continue;
    }
    
    // Essayer différentes permissions
    $permissions = ['0755', '0775', '0777'];
    $fixed = false;
    
    foreach ($permissions as $perm) {
        echo "<p>🔄 Test permissions {$perm}...</p>";
        
        if (@chmod($path, octdec($perm))) {
            $testFile = $path . '/test_' . $perm . '_' . time() . '.txt';
            if (@file_put_contents($testFile, 'test') !== false) {
                @unlink($testFile);
                echo "<p class='success'>✅ Succès avec {$perm}</p>";
                $fixed = true;
                $fixedCount++;
                break;
            }
        }
    }
    
    if (!$fixed) {
        echo "<p class='error'>❌ Correction automatique impossible</p>";
    }
}

// Résultat final
echo "<h2>📋 Résultat Final</h2>";

if ($fixedCount == $totalCount) {
    echo "<div class='action' style='background: #d4edda; color: #155724;'>";
    echo "<h3>🎉 Succès Total!</h3>";
    echo "<p>Tous les répertoires ({$fixedCount}/{$totalCount}) sont maintenant accessibles en écriture.</p>";
    echo "<p><a href='install.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Continuer l'installation</a></p>";
    echo "</div>";
} else {
    echo "<div class='action' style='background: #fff3cd; color: #856404;'>";
    echo "<h3>⚠️ Correction Partielle</h3>";
    echo "<p>Répertoires corrigés: {$fixedCount}/{$totalCount}</p>";
    echo "<p>Solutions manuelles requises:</p>";
    
    echo "<h4>Commandes SSH à exécuter:</h4>";
    echo "<pre>";
    echo "cd " . __DIR__ . "\n\n";
    echo "# Créer tous les répertoires\n";
    foreach ($directories as $dir) {
        echo "mkdir -p {$dir}\n";
    }
    echo "\n# Méthode 1: Permissions 755\n";
    foreach ($directories as $dir) {
        echo "chmod 755 {$dir}\n";
    }
    echo "\n# Méthode 2: Permissions 777 (temporaire)\n";
    foreach ($directories as $dir) {
        echo "chmod 777 {$dir}\n";
    }
    echo "\n# Méthode 3: Changer propriétaire (si root)\n";
    foreach ($directories as $dir) {
        echo "chown www-data:www-data {$dir}\n";
    }
    echo "</pre>";
    echo "</div>";
}

// Informations système
echo "<h2>ℹ️ Informations Système</h2>";
echo "<table>";
echo "<tr><td>Utilisateur PHP</td><td>" . get_current_user() . " (UID: " . getmyuid() . ")</td></tr>";
echo "<tr><td>Groupe PHP</td><td>GID: " . getmygid() . "</td></tr>";
echo "<tr><td>Répertoire courant</td><td>" . __DIR__ . "</td></tr>";
echo "<tr><td>Umask</td><td>" . sprintf('%04o', umask()) . "</td></tr>";
echo "</table>";

echo "<div style='margin-top: 30px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 5px;'>";
echo "<h3>🔒 Important</h3>";
echo "<p>Supprimez ce fichier après utilisation:</p>";
echo "<pre>rm " . __FILE__ . "</pre>";
echo "</div>";

echo "</div></body></html>";
?>
