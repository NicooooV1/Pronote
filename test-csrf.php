<?php
session_start();
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Security/CSRF.php';

try {
    $csrf = new \API\Security\CSRF(3600, 10);

    // Test génération
    $token1 = $csrf->generate();
    echo "✅ Token généré: {$token1}\n";

    // Test validation
    $valid = $csrf->validate($token1);
    echo "✅ Token valide: " . ($valid ? "OUI" : "NON") . "\n";

    // Test usage unique
    $valid2 = $csrf->validate($token1);
    echo "✅ Token déjà utilisé: " . ($valid2 ? "OUI" : "NON") . " (doit être NON)\n";

    // Test field
    echo "✅ HTML Field: " . $csrf->field() . "\n";
    
    // Test meta
    echo "✅ HTML Meta: " . $csrf->meta() . "\n";
    
} catch (Exception $e) {
    echo "❌ CSRF test failed: " . $e->getMessage() . "\n";
}
