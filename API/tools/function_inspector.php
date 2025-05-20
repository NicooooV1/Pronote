<?php
/**
 * Outil de diagnostic pour les fonctions d'authentification
 */

// Afficher les erreurs pour le diagnostic
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lister les fichiers importants
$files = [
    'auth_central.php' => __DIR__ . '/../../API/auth_central.php',
    'auth_bridge.php' => __DIR__ . '/../../API/auth_bridge.php',
    'autoload.php' => __DIR__ . '/../../API/autoload.php',
    'bootstrap.php' => __DIR__ . '/../../API/bootstrap.php',
    'notes/includes/auth.php' => __DIR__ . '/../../notes/includes/auth.php',
    'absences/includes/auth.php' => __DIR__ . '/../../absences/includes/auth.php',
    'cahierdetextes/includes/auth.php' => __DIR__ . '/../../cahierdetextes/includes/auth.php',
];

// Fonctions à vérifier pour éviter les redéclarations
$functions_to_check = [
    'isLoggedIn',
    'getCurrentUser',
    'getUserRole',
    'requireLogin',
    'getUserInitials',
    'canManageNotes',
    'canManageAbsences',
    'isAdmin',
    'isTeacher',
    'isStudent',
    'isParent',
    'isVieScolaire'
];

// Initialiser les résultats
$results = [];
$file_contents = [];
$function_locations = [];

// Fonction pour extraire les déclarations de fonctions
function extractFunctions($content) {
    $functions = [];
    $pattern = '/function\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/';
    
    if (preg_match_all($pattern, $content, $matches)) {
        foreach ($matches[1] as $match) {
            $functions[] = $match;
        }
    }
    
    return $functions;
}

// Analyser chaque fichier
foreach ($files as $name => $path) {
    if (file_exists($path) && is_readable($path)) {
        $content = file_get_contents($path);
        $file_contents[$name] = $content;
        $functions = extractFunctions($content);
        
        $results[$name] = [
            'exists' => true,
            'functions' => $functions,
            'size' => filesize($path),
            'modified' => date('Y-m-d H:i:s', filemtime($path))
        ];
        
        // Enregistrer les fonctions trouvées et leur localisation
        foreach ($functions as $func) {
            if (!isset($function_locations[$func])) {
                $function_locations[$func] = [];
            }
            $function_locations[$func][] = $name;
        }
    } else {
        $results[$name] = [
            'exists' => false,
            'error' => "Fichier non trouvé ou inaccessible"
        ];
    }
}

// Créer une sortie HTML
echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic des fonctions d\'authentification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        .file-header { margin-top: 20px; font-weight: bold; }
        .function-list { margin-left: 20px; }
    </style>
</head>
<body>
    <h1>Diagnostic des fonctions d\'authentification</h1>';

// Afficher la table des fonctions essentielles
echo '<h2>Fonctions essentielles vérifiées</h2>
<table>
    <tr>
        <th>Fonction</th>
        <th>Statut</th>
        <th>Déclarée dans</th>
    </tr>';

foreach ($functions_to_check as $function) {
    $status = isset($function_locations[$function]) ? 'success' : 'error';
    $message = isset($function_locations[$function]) ? 'Trouvée' : 'Non trouvée';
    $locations = isset($function_locations[$function]) ? implode(', ', $function_locations[$function]) : 'Aucun';
    
    // Vérifier les duplications
    $is_duplicated = isset($function_locations[$function]) && count($function_locations[$function]) > 1;
    if ($is_duplicated) {
        $status = 'warning';
        $message = 'Déclarée plusieurs fois';
    }
    
    echo "<tr>
        <td>{$function}</td>
        <td class=\"{$status}\">{$message}</td>
        <td>{$locations}</td>
    </tr>";
}
echo '</table>';

// Résumé des problèmes potentiels
$problems = [];
foreach ($functions_to_check as $function) {
    if (!isset($function_locations[$function])) {
        $problems[] = "La fonction <code>{$function}</code> n'est pas déclarée.";
    } else if (count($function_locations[$function]) > 1) {
        $problems[] = "La fonction <code>{$function}</code> est déclarée plusieurs fois: " . implode(', ', $function_locations[$function]);
    }
}

if (!empty($problems)) {
    echo '<h2>Problèmes détectés</h2>
    <ul>';
    foreach ($problems as $problem) {
        echo "<li class=\"error\">{$problem}</li>";
    }
    echo '</ul>
    
    <h3>Solution recommandée</h3>
    <p>Utilisez le système d\'authentification centralisé en incluant <code>API/auth_central.php</code> au lieu de déclarer vos propres fonctions d\'authentification dans chaque module.</p>
    <p>Exemple de code à ajouter au début de vos fichiers:</p>
    <pre>
require_once __DIR__ . \'/../API/auth_central.php\';
</pre>';
} else {
    echo '<h2>Aucun problème détecté</h2>
    <p class="success">Toutes les fonctions essentielles sont correctement déclarées.</p>';
}

// Détails des fichiers analysés
echo '<h2>Détails des fichiers analysés</h2>';
foreach ($results as $name => $info) {
    echo "<div class=\"file-header\">{$name}</div>";
    if ($info['exists']) {
        echo "<div>Taille: {$info['size']} octets | Modifié: {$info['modified']}</div>";
        echo "<div class=\"function-list\">Fonctions: " . (empty($info['functions']) ? "Aucune" : implode(', ', $info['functions'])) . "</div>";
    } else {
        echo "<div class=\"error\">{$info['error']}</div>";
    }
}

echo '<p><a href="../../diagnostic.php">Retour à la page de diagnostic principale</a></p>';
echo '</body></html>';
?>
