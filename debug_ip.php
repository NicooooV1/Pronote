<?php
/**
 * Script de diagnostic temporaire - À SUPPRIMER après résolution
 */
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnostic IP</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>Diagnostic IP pour installation Pronote</h1>
    
    <h2>Informations détectées</h2>
    <table border="1" style="border-collapse: collapse;">
        <tr>
            <th>Variable</th>
            <th>Valeur</th>
        </tr>
        <tr>
            <td>REMOTE_ADDR</td>
            <td><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Non défini') ?></td>
        </tr>
        <tr>
            <td>HTTP_X_FORWARDED_FOR</td>
            <td><?= htmlspecialchars($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Non défini') ?></td>
        </tr>
        <tr>
            <td>HTTP_X_REAL_IP</td>
            <td><?= htmlspecialchars($_SERVER['HTTP_X_REAL_IP'] ?? 'Non défini') ?></td>
        </tr>
        <tr>
            <td>HTTP_CLIENT_IP</td>
            <td><?= htmlspecialchars($_SERVER['HTTP_CLIENT_IP'] ?? 'Non défini') ?></td>
        </tr>
    </table>
    
    <h2>Fichier .env</h2>
    <?php
    $envFile = __DIR__ . '/.env';
    echo "<p><strong>Chemin :</strong> " . htmlspecialchars($envFile) . "</p>";
    echo "<p><strong>Existe :</strong> " . (file_exists($envFile) ? 'Oui' : 'Non') . "</p>";
    echo "<p><strong>Lisible :</strong> " . (is_readable($envFile) ? 'Oui' : 'Non') . "</p>";
    
    if (file_exists($envFile) && is_readable($envFile)) {
        echo "<h3>Contenu du fichier .env :</h3>";
        echo "<pre>" . htmlspecialchars(file_get_contents($envFile)) . "</pre>";
    } else {
        echo "<p style='color: red;'>Le fichier .env n'est pas accessible !</p>";
    }
    ?>
    
    <h2>Commandes suggérées</h2>
    <pre>
# Sur le serveur, exécutez :
chmod 644 /var/www/html/pronote/.env
chown webadmin:www-data /var/www/html/pronote/.env

# Puis ajoutez cette IP dans le fichier .env :
echo "ALLOWED_INSTALL_IP=192.168.1.80,82.65.180.135,<?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'IP_INCONNUE') ?>" > /var/www/html/pronote/.env
    </pre>
</body>
</html>
