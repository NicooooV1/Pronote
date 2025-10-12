<?php
/**
 * Script de correction des permissions pour l'installation de Pronote
 * Ce script aide à résoudre les problèmes de permissions couramment rencontrés
 * lors de l'installation de l'application Pronote.
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

// Répertoires à corriger
$directories = [
    'API/logs',
    'API/config',
    'uploads',
    'temp',
    'login/logs'
];

$results = [];
$allOk = true;

// Vérifier si on peut détecter l'utilisateur du serveur web
$webServerUser = null;
if (function_exists('posix_getpwuid')) {
    $webServerUser = posix_getpwuid(posix_geteuid());
}

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction des permissions - Pronote</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .status-box {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
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
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>🔧 Correction des permissions pour l'installation de Pronote</h1>
    
    <div class='status-box'>";

echo "<p>Détection de l'environnement du serveur :</p>
    <ul>
        <li><strong>Système d'exploitation :</strong> " . PHP_OS . "</li>
        <li><strong>Serveur web :</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Non détecté') . "</li>";

if ($webServerUser) {
    echo "<li><strong>Utilisateur du serveur web :</strong> {$webServerUser['name']} (UID: {$webServerUser['uid']})</li>";
}

echo "</ul>";

// Créer les répertoires s'ils n'existent pas et corriger les permissions
foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    $result = [
        'directory' => $dir,
        'status' => 'success',
        'message' => 'OK',
        'permissions_before' => null,
        'permissions_after' => null
    ];
    
    // Vérifier si le répertoire existe
    if (!is_dir($path)) {
        // Essayer de créer le répertoire
        if (!@mkdir($path, 0755, true)) {
            $result['status'] = 'error';
            $result['message'] = "Impossible de créer le répertoire";
            $allOk = false;
        } else {
            $result['message'] = "Répertoire créé";
        }
    } else {
        $result['permissions_before'] = substr(sprintf('%o', fileperms($path)), -4);
    }
    
    // Appliquer les permissions si le répertoire existe maintenant
    if (is_dir($path)) {
        // Essayer d'abord 0755
        @chmod($path, 0755);
        
        // Test d'écriture
        $testFile = $path . '/test_perm_' . uniqid() . '.tmp';
        $canWrite = @file_put_contents($testFile, 'test');
        
        if ($canWrite) {
            @unlink($testFile);
        } else {
            // Si 0755 n'est pas suffisant, essayer 0775
            @chmod($path, 0775);
            $canWrite = @file_put_contents($testFile, 'test');
            
            if ($canWrite) {
                @unlink($testFile);
            } else {
                // Si toujours pas, essayer 0777 en dernier recours
                @chmod($path, 0777);
                $canWrite = @file_put_contents($testFile, 'test');
                
                if ($canWrite) {
                    @unlink($testFile);
                } else {
                    $result['status'] = 'error';
                    $result['message'] = "Impossible d'écrire dans le répertoire même après chmod 0777";
                    $allOk = false;
                }
            }
        }
        
        $result['permissions_after'] = substr(sprintf('%o', fileperms($path)), -4);
        
        // Vérifier si les permissions ont été modifiées
        if ($result['permissions_before'] !== $result['permissions_after']) {
            $result['message'] = "Permissions modifiées de {$result['permissions_before']} à {$result['permissions_after']}";
        }
        
        // Vérifier si le propriétaire doit être changé
        $ownerChangeNeeded = false;
        if ($webServerUser && $result['status'] === 'success') {
            $currentOwner = posix_getpwuid(fileowner($path));
            if (!$currentOwner || $currentOwner['name'] !== $webServerUser['name']) {
                $ownerChangeNeeded = true;
            }
        }
        
        // Si le changement de propriétaire est nécessaire
        if ($ownerChangeNeeded) {
            $newOwner = $webServerUser['name'];
            $newGroup = $webServerUser['name'];
            
            // Essayer de changer le propriétaire
            if (@chown($path, $newOwner) && @chgrp($path, $newGroup)) {
                $result['message'] .= ", propriétaire changé en {$newOwner}";
            } else {
                $result['status'] = 'error';
                $result['message'] .= ", échec du changement de propriétaire";
                $allOk = false;
            }
        }
        
        // Vérifier si les permissions sont suffisantes
        if ($result['status'] === 'success' && !$canWrite) {
            $result['status'] = 'warning';
            $result['message'] = "Le répertoire existe mais n'est pas accessible en écriture";
            $allOk = false;
        }
    }
    
    $results[] = $result;
}

// Afficher les résultats
echo "<h2>Résultats de la correction :</h2>";

if ($allOk) {
    echo "<div class='success'><strong>✅ Tous les répertoires sont correctement configurés !</strong></div>";
} else {
    echo "<div class='error'><strong>⚠️ Certains problèmes n'ont pas pu être résolus automatiquement</strong></div>";
}

echo "<table>
    <tr>
        <th>Répertoire</th>
        <th>Statut</th>
        <th>Anciennes permissions</th>
        <th>Nouvelles permissions</th>
        <th>Message</th>
    </tr>";

foreach ($results as $result) {
    $statusClass = $result['status'] === 'success' ? 'success' : ($result['status'] === 'warning' ? 'warning' : 'error');
    $statusIcon = $result['status'] === 'success' ? '✅' : ($result['status'] === 'warning' ? '⚠️' : '❌');
    
    echo "<tr class='{$statusClass}'>
        <td><code>{$result['directory']}</code></td>
        <td>{$statusIcon}</td>
        <td>{$result['permissions_before']}</td>
        <td>{$result['permissions_before'] !== $result['permissions_after'] ? '<strong>' . $result['permissions_after'] . '</strong>' : $result['permissions_after']}</td>
        <td>{$result['message']}</td>
    </tr>";
}

echo "</table>";

// Afficher les commandes manuelles si nécessaire
if (!$allOk) {
    echo "<div class='warning'>
        <h3>Corrections manuelles nécessaires</h3>
        <p>Certains problèmes doivent être corrigés manuellement. Exécutez les commandes suivantes sur votre serveur :</p>
        <pre>";
    
    foreach ($directories as $dir) {
        $path = __DIR__ . '/' . $dir;
        echo "mkdir -p {$path}\n";
        echo "chmod -R 777 {$path}\n";
    }
    
    echo "</pre>
        <p>Ou, si vous connaissez l'utilisateur du serveur web (généralement www-data, apache, ou nginx) :</p>
        <pre>";
    
    $webUser = "www-data";  // Par défaut pour Apache/Nginx sur beaucoup de systèmes Linux
    if ($webServerUser) {
        $webUser = $webServerUser['name'];
    }
    
    foreach ($directories as $dir) {
        $path = __DIR__ . '/' . $dir;
        echo "mkdir -p {$path}\n";
        echo "chown -R {$webUser}:{$webUser} {$path}\n";
    }
    
    echo "</pre>
    </div>";
}

echo "<div style='text-align: center; margin-top: 20px;'>
    <a href='install.php' class='btn'>Retourner à l'installation</a>
</div>";

echo "</div>
</body>
</html>";
?>
