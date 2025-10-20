<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test CSRF - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔐 Test CSRF Protection</h1>
            <p>Vérification des tokens anti-CSRF</p>
        </header>
        <main>
<?php
session_start();
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Security/CSRF.php';

function section($t){ echo "<div class='test-section'><h2>{$t}</h2><div class='test-content'>"; }
function sectionEnd(){ echo "</div></div>"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    $class = '';
    if ($v === 'OUI') $class = 'success';
    if ($v === 'NON') $class = 'error';
    echo "<div class='kv-item'><span class='kv-key'>{$k}</span><span class='kv-value {$class}'>{$v}</span></div>";
}

try {
    section('CSRF Protection');
    $csrf = new \API\Security\CSRF(3600, 10);

    $token1 = $csrf->generate();
    echo "<div class='info-message'><strong>Token généré :</strong></div>";
    echo "<div class='token'>" . htmlspecialchars($token1) . "</div>";

    $valid = $csrf->validate($token1);
    kv('Token valide', $valid);

    $valid2 = $csrf->validate($token1);
    echo "<div class='kv-item'><span class='kv-key'>Token déjà utilisé</span><span class='kv-value " . ($valid2 ? 'error' : 'success') . "'>" . ($valid2 ? "OUI (❌ problème)" : "NON (✓ correct)") . "</span></div>";

    echo "<div class='info-message' style='margin-top: 1rem;'><strong>HTML Field :</strong><br>" . htmlspecialchars($csrf->field()) . "</div>";
    echo "<div class='info-message'><strong>HTML Meta :</strong><br>" . htmlspecialchars($csrf->meta()) . "</div>";
    
    sectionEnd();
    
} catch (Exception $e) {
    section('Erreur');
    echo "<div class='error-message'>❌ CSRF test failed: " . htmlspecialchars($e->getMessage()) . "</div>";
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
