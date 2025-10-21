<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Application - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🚀 Test de l'Application</h1>
            <p>Vérification de l'initialisation et de la configuration</p>
        </header>
        <main>
<?php
require_once __DIR__ . '/API/bootstrap.php';

function section($t){ echo "<div class='test-section'><h2>{$t}</h2><div class='test-content'>"; }
function sectionEnd(){ echo "</div></div>"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    $class = '';
    if ($v === 'OUI' || $v === 'OK') $class = 'success';
    if ($v === 'NON' || $v === 'NULL') $class = 'error';
    echo "<div class='kv-item'><span class='kv-key'>{$k}</span><span class='kv-value {$class}'>" . htmlspecialchars($v) . "</span></div>";
}

try {
    $app = app();
    section('Test Application');
    kv('App loaded', is_object($app));
    kv('App URL', config('app.url', 'http://localhost'));
    kv('DB Host', config('database.host', 'localhost'));
    sectionEnd();
} catch (Exception $e) {
    section('Erreur');
    echo "<div class='error-message'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    sectionEnd();
}
?>
        </main>
        <footer>
            <p>Pronote API Test Suite &copy; 2024</p>
        </footer>
    </div>
</body>
</html>
