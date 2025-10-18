<?php
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Security/Validator.php';

// helpers
function section($t){ echo "=== {$t} ===\n"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    echo "{$k}: {$v}\n";
}

try {
    // Test 1 : Validation basique
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

    echo "Validation: " . ($ok1 ? "PASS" : "FAIL") . "\n";
    print_r($validator->errors());

    // Test 2 : Validation avec erreurs
    section("Test 2 : Validation avec erreurs");
    $data2 = [
        'email' => 'invalid-email',
        'password' => '123'
    ];

    $ok2 = $validator->validate($data2, [
        'email' => 'required|email',
        'password' => 'required|min:8'
    ]);

    echo "Validation: " . ($ok2 ? "PASS" : "FAIL") . "\n";
    print_r($validator->errors());
    
} catch (Exception $e) {
    echo "❌ Validator test failed: " . $e->getMessage() . "\n";
}
