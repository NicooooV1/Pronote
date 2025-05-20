<?php
/**
 * Outil de diagnostic des problèmes de chemin et de redirection
 * À placer dans le répertoire API/tools
 */

// Afficher les erreurs en mode développement
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnostic des chemins Pronote</h1>";

echo "<h2>Informations sur le serveur</h2>";
echo "<pre>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "\n";
echo "</pre>";

echo "<h2>Détection du chemin de base</h2>";

// Méthode 1: Basée sur SCRIPT_NAME
$basePathMethod1 = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])));
echo "<p>Méthode 1 (SCRIPT_NAME): <code>{$basePathMethod1}</code></p>";

// Méthode 2: Basée sur REQUEST_URI
$requestUri = $_SERVER['REQUEST_URI'];
$toolPath = '/API/tools/path_debug.php';
$basePathMethod2 = str_replace($toolPath, '', $requestUri);
if (substr($basePathMethod2, -1) === '/') {
    $basePathMethod2 = substr($basePathMethod2, 0, -1);
}
echo "<p>Méthode 2 (REQUEST_URI): <code>{$basePathMethod2}</code></p>";

// Méthode 3: Répertoire du fichier actuel
$scriptPath = realpath(__FILE__);
$projectRoot = dirname(dirname(dirname($scriptPath)));
echo "<p>Méthode 3 (Chemin fichier): <code>{$projectRoot}</code></p>";

// Vérifier les fichiers de configuration
echo "<h2>Configuration actuelle</h2>";
$configFile = dirname(dirname(__FILE__)) . '/config/env.php';
if (file_exists($configFile)) {
    include_once $configFile;
    if (defined('BASE_URL')) {
        echo "<p>BASE_URL défini dans config: <code>" . BASE_URL . "</code></p>";
    } else {
        echo "<p>BASE_URL non défini dans le fichier de configuration!</p>";
    }
} else {
    echo "<p>Fichier de configuration non trouvé: {$configFile}</p>";
}

// Tester l'accessibilité des chemins importants
echo "<h2>Test d'accessibilité des chemins relatifs</h2>";
$paths = [
    'login/public/index.php',
    'accueil/accueil.php',
    'notes/notes.php',
    'absences/absences.php',
    'agenda/agenda.php',
    'cahierdetextes/cahierdetextes.php',
    'messagerie/index.php'
];

// Créer du HTML et CSS de base pour les résultats de test
echo "<style>
.success { color: green; }
.error { color: red; }
table { border-collapse: collapse; width: 100%; }
th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
</style>";

echo "<table>";
echo "<tr><th>Chemin</th><th>URL construite</th><th>Résultat</th></tr>";

foreach ($paths as $path) {
    // Tester avec les différentes méthodes de détection du chemin de base
    $url1 = $basePathMethod1 . '/' . $path;
    $url2 = $basePathMethod2 . '/' . $path;
    
    // Construire l'URL complète
    $fullUrl1 = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . 
                '://' . $_SERVER['HTTP_HOST'] . $url1;
    
    // Tester si le fichier existe
    $fileExists = file_exists(dirname(dirname(dirname(__FILE__))) . '/' . $path);
    
    echo "<tr>";
    echo "<td>{$path}</td>";
    echo "<td><a href=\"{$fullUrl1}\" target=\"_blank\">{$url1}</a></td>";
    echo "<td class=\"" . ($fileExists ? 'success' : 'error') . "\">" . 
         ($fileExists ? 'Fichier existe' : 'Fichier non trouvé') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Recommandations</h2>";
echo "<p>Basé sur l'analyse des chemins, la valeur recommandée pour BASE_URL est:</p>";
echo "<code>{$basePathMethod1}</code>";
echo "<p>Pour configurer cette valeur, modifiez le fichier <code>API/config/env.php</code> et ajoutez/modifiez:</p>";
echo "<pre>if (!defined('BASE_URL')) define('BASE_URL', '{$basePathMethod1}');</pre>";

// Vérifier les redirections
echo "<h2>Vérification des redirections</h2>";
echo "<p>Pour vérifier les problèmes de redirection, essayez ces liens:</p>";
echo "<ul>";
echo "<li><a href=\"{$basePathMethod1}/login/public/index.php\" target=\"_blank\">Page de connexion</a></li>";
echo "<li><a href=\"{$basePathMethod1}/login/public/logout.php\" target=\"_blank\">Déconnexion</a></li>";
echo "<li><a href=\"{$basePathMethod1}/accueil/accueil.php\" target=\"_blank\">Page d'accueil</a></li>";
echo "</ul>";

// Ajouter un outil de génération de .htaccess
echo "<h2>Générer un fichier .htaccess</h2>";
echo "<p>Si vous rencontrez des problèmes de redirection, un fichier .htaccess bien configuré peut aider.</p>";
echo "<form method=\"post\" action=\"generate_htaccess.php\">";
echo "<label>Chemin de base: <input type=\"text\" name=\"basePath\" value=\"{$basePathMethod1}\" /></label><br>";
echo "<label>Forcer HTTPS: <input type=\"checkbox\" name=\"forceHttps\" value=\"1\" /></label><br>";
echo "<input type=\"submit\" value=\"Générer .htaccess\" />";
echo "</form>";

// Ajouter un lien retour au diagnostic principal
echo "<p><a href=\"../../diagnostic.php\">Retour à la page de diagnostic principale</a></p>";
?>
