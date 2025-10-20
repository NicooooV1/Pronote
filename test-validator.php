<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Validator - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>✅ Test du Validator</h1>
            <p>Vérification des règles de validation</p>
        </header>
        <main>
<?php
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Security/Validator.php';

function section($t){ echo "<div class='test-section'><h2>{$t}</h2><div class='test-content'>"; }
function sectionEnd(){ echo "</div></div>"; }

try {
    section("Test 1 : Validation basique");
    $data1 = [
        'email' => 'test@example.com',
        'password' => '12345678'
    ];

    $validator = new \API\Security\Validator();
    $ok1 = $validator->validate($data1, [
        'email' => 'required|email',
        'password' => 'required|min:8'
    ]);

    echo "<div class='kv-item'><span class='kv-key'>Validation</span><span class='kv-value " . ($ok1 ? 'success' : 'error') . "'>" . ($ok1 ? "PASS ✓" : "FAIL ✗") . "</span></div>";
    
    if (!empty($validator->errors())) {
        echo "<div class='error-message'>";
        echo "<strong>Erreurs :</strong><br>";
        foreach ($validator->errors() as $field => $errors) {
            echo "• <strong>{$field}</strong> : " . implode(', ', $errors) . "<br>";
        }
        echo "</div>";
    } else {
        echo "<div class='success-message'>✓ Aucune erreur de validation</div>";
    }
    sectionEnd();

    section("Test 2 : Validation avec erreurs");
    $data2 = [
        'email' => 'invalid-email',
        'password' => '123'
    ];

    $ok2 = $validator->validate($data2, [
        'email' => 'required|email',
        'password' => 'required|min:8'
    ]);

    echo "<div class='kv-item'><span class='kv-key'>Validation</span><span class='kv-value " . ($ok2 ? 'success' : 'error') . "'>" . ($ok2 ? "PASS ✓" : "FAIL ✗") . "</span></div>";
    
    if (!empty($validator->errors())) {
        echo "<div class='error-message'>";
        echo "<strong>Erreurs détectées (attendu) :</strong><br>";
        foreach ($validator->errors() as $field => $errors) {
            echo "• <strong>{$field}</strong> : " . implode(', ', $errors) . "<br>";
        }
        echo "</div>";
    }
    sectionEnd();
    
} catch (Exception $e) {
    section('Erreur');
    echo "<div class='error-message'>❌ Validator test failed: " . htmlspecialchars($e->getMessage()) . "</div>";
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
