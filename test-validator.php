<?php
require_once __DIR__ . '/API/Security/Validator.php';

use Pronote\Security\Validator;

try {
    // Test 1 : Validation basique
    echo "=== Test 1 : Validation basique ===\n";
    $data1 = [
        'email' => 'test@example.com',
        'password' => '12345678',
        'age' => 25
    ];

    $validator1 = Validator::make($data1, [
        'email' => 'required|email',
        'password' => 'required|min:8',
        'age' => 'required|numeric|min:18'
    ]);

    $validator1->validate();
    echo "Validation: " . ($validator1->passes() ? "PASS" : "FAIL") . "\n";
    print_r($validator1->errors());

    // Test 2 : Validation avec erreurs
    echo "\n=== Test 2 : Validation avec erreurs ===\n";
    $data2 = [
        'email' => 'invalid-email',
        'password' => '123',
        'age' => 'abc'
    ];

    $validator2 = Validator::make($data2, [
        'email' => 'required|email',
        'password' => 'required|min:8',
        'age' => 'required|integer|min:18'
    ]);

    $validator2->validate();
    echo "Validation: " . ($validator2->passes() ? "PASS" : "FAIL") . "\n";
    print_r($validator2->errors());

    // Test 3 : Messages personnalisés
    echo "\n=== Test 3 : Messages personnalisés ===\n";
    $data3 = ['username' => 'ab'];

    $validator3 = Validator::make($data3, [
        'username' => 'required|min:3|max:20'
    ], [
        'username.min' => 'Le nom d\'utilisateur est trop court !',
        'username.max' => 'Le nom d\'utilisateur est trop long !'
    ]);

    $validator3->validate();
    print_r($validator3->errors());

    // Test 4 : Confirmation
    echo "\n=== Test 4 : Confirmation de mot de passe ===\n";
    $data4 = [
        'password' => 'secret123',
        'password_confirmation' => 'secret123'
    ];

    $validator4 = Validator::make($data4, [
        'password' => 'required|confirmed'
    ]);

    $validator4->validate();
    echo "Confirmation: " . ($validator4->passes() ? "PASS" : "FAIL") . "\n";

    // Test 5 : In rule
    echo "\n=== Test 5 : In rule ===\n";
    $data5 = ['role' => 'admin'];

    $validator5 = Validator::make($data5, [
        'role' => 'required|in:admin,user,moderator'
    ]);

    $validator5->validate();
    echo "In rule: " . ($validator5->passes() ? "PASS" : "FAIL") . "\n";
    
} catch (Exception $e) {
    echo "❌ Validator test failed: " . $e->getMessage() . "\n";
}
