<?php
session_start();
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Security/CSRF.php';

// helpers
function section($t){ echo "=== {$t} ===\n"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    echo "{$k}: {$v}\n";
}

try {
    section('CSRF');
    $csrf = new \API\Security\CSRF(3600, 10);

    $token1 = $csrf->generate();
    echo "Token: {$token1}\n";

    $valid = $csrf->validate($token1);
    kv('Token valide', $valid);

    $valid2 = $csrf->validate($token1);
    echo "Token déjà utilisé: " . ($valid2 ? "OUI" : "NON") . " (doit être NON)\n";

    echo "HTML Field: " . $csrf->field() . "\n";
    echo "HTML Meta: " . $csrf->meta() . "\n";
    
} catch (Exception $e) {
    echo "❌ CSRF test failed: " . $e->getMessage() . "\n";
}
