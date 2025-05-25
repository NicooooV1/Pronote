<?php
/**
 * Script pour corriger automatiquement les permissions
 * À exécuter une seule fois pour résoudre les problèmes de permissions
 * SUPPRIMER après utilisation
 */

header('Content-Type: text/html; charset=UTF-8');

// Vérifier que ce script est exécuté depuis le bon répertoire
if (!file_exists(__DIR__ . '/install.php')) {
    die('Ce script doit être placé dans le même répertoire que install.php');
}

echo "<!DOCTYPE html><html><head><title>Correction des permissions</title><meta charset='UTF-8'></head><body>";
echo "<h1>Correction automatique des permissions</h1>";

$directories = [
    'API',
    'API/config', 
    'API/logs',
    'uploads',
    'temp'
];

$results = [];
$allFixed = true;

foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    $result = ['name' => $dir, 'status' => 'error', 'message' => ''];
    
    try {
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($path)) {
            if (mkdir($path, 0755, true)) {
                $result['message'] .= "Répertoire créé. ";
            } else {
                $result['message'] = "Impossible de créer le répertoire.";
                $allFixed = false;
                $results[] = $result;
                continue;
            }
        }
        
        // Vérifier/corriger les permissions
        $currentPerms = substr(sprintf('%o', fileperms($path)), -4);
        
        if (!is_readable($path)) {
            if (@chmod($path, 0755)) {
                $result['message'] .= "Permissions de lecture corrigées. ";
            } else {
                $result['message'] .= "Impossible de corriger les permissions de lecture. ";
                $allFixed = false;
            }
        }
        
        if (!is_writable($path)) {
            if (@chmod($path, 0755)) {
                $result['message'] .= "Permissions d'écriture corrigées. ";
            } else {
                $result['message'] .= "Impossible de corriger les permissions d'écriture. ";
                $allFixed = false;
            }
        }
        
        // Test de création de fichier
        $testFile = $path . '/test_permissions.txt';
        if (file_put_contents($testFile, 'test') !== false) {
            @unlink($testFile);
            $result['status'] = 'success';
            $result['message'] .= "Test d'écriture réussi.";
        } else {
            $result['message'] .= "Test d'écriture échoué.";
            $allFixed = false;
        }
        
        $newPerms = substr(sprintf('%o', fileperms($path)), -4);
        $result['message'] .= " Permissions: {$currentPerms} → {$newPerms}";
        
    } catch (Exception $e) {
        $result['message'] = "Erreur: " . $e->getMessage();
        $allFixed = false;
    }
    
    $results[] = $result;
}

echo "<h2>Résultats de la correction</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Répertoire</th><th>Statut</th><th>Détails</th></tr>";

foreach ($results as $result) {
    $color = $result['status'] === 'success' ? 'green' : 'red';
    echo "<tr>";
    echo "<td>" . htmlspecialchars($result['name']) . "</td>";
    echo "<td style='color: {$color};'>" . ucfirst($result['status']) . "</td>";
    echo "<td>" . htmlspecialchars($result['message']) . "</td>";
    echo "</tr>";
}

echo "</table>";

if ($allFixed) {
    echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3 style='color: #155724;'>✓ Toutes les permissions ont été corrigées avec succès !</h3>";
    echo "<p>Vous pouvez maintenant continuer l'installation en accédant à <a href='install.php'>install.php</a></p>";
    echo "<p><strong>Important :</strong> Supprimez ce fichier après utilisation pour des raisons de sécurité.</p>";
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3 style='color: #721c24;'>⚠ Certains problèmes n'ont pas pu être résolus automatiquement</h3>";
    echo "<p>Vous devez exécuter manuellement les commandes suivantes sur votre serveur :</p>";
    echo "<pre>";
    echo "cd " . htmlspecialchars(__DIR__) . "\n";
    foreach ($directories as $dir) {
        echo "mkdir -p {$dir}\n";
        echo "chmod 755 {$dir}\n";
    }
    echo "chown -R webadmin:www-data API uploads temp\n";
    echo "</pre>";
    echo "</div>";
}

echo "<h2>Commandes manuelles (si nécessaire)</h2>";
echo "<p>Si la correction automatique ne fonctionne pas, exécutez ces commandes sur votre serveur :</p>";
echo "<pre>";
echo "# Se placer dans le répertoire de l'application\n";
echo "cd " . htmlspecialchars(__DIR__) . "\n\n";
echo "# Créer tous les répertoires nécessaires\n";
foreach ($directories as $dir) {
    echo "mkdir -p {$dir}\n";
}
echo "\n# Définir les permissions appropriées\n";
foreach ($directories as $dir) {
    echo "chmod 755 {$dir}\n";
}
echo "\n# Définir le propriétaire approprié (ajustez selon votre configuration)\n";
echo "chown webadmin:www-data API/config API/logs uploads temp\n\n";
echo "# Vérifier les permissions\n";
echo "ls -la API/\n";
echo "</pre>";

echo "</body></html>";
?>
