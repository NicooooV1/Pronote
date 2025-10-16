<?php
// Bootstrap the application
$app = require_once __DIR__ . '/API/bootstrap.php';

use Pronote\Auth\AuthManager;

try {
    // Get auth from container
    $auth = $app->make('auth');

    // Test 1 : Vérifier non connecté
    echo "=== Test 1 : Check non authentifié ===\n";
    echo "Connecté: " . ($auth->check() ? "OUI" : "NON") . "\n";
    echo "User: " . var_export($auth->user(), true) . "\n\n";

    // Test 2 : Tentative d'authentification (va échouer sans DB)
    echo "=== Test 2 : Tentative d'authentification ===\n";
    $result = $auth->attempt([
        'identifiant' => 'test.user',
        'password' => 'password123',
        'profil' => 'eleve'
    ]);
    echo "Auth réussie: " . ($result ? "OUI" : "NON") . "\n\n";

    // Test 3 : Simulation de connexion manuelle
    echo "=== Test 3 : Simulation connexion manuelle ===\n";
    $auth->guard()->login([
        'id' => 1,
        'identifiant' => 'test.user',
        'nom' => 'User',
        'prenom' => 'Test',
        'mail' => 'test@example.com',
        'profil' => 'eleve',
        'classe' => '6A'
    ]);

    echo "Connecté: " . ($auth->check() ? "OUI" : "NON") . "\n";
    echo "User ID: " . $auth->id() . "\n";
    echo "User: " . json_encode($auth->user(), JSON_PRETTY_PRINT) . "\n\n";

    // Test 4 : Vérification rôle
    echo "=== Test 4 : Vérification rôle ===\n";
    echo "Est élève: " . ($auth->hasRole('eleve') ? "OUI" : "NON") . "\n";
    echo "Est prof: " . ($auth->hasRole('professeur') ? "OUI" : "NON") . "\n\n";

    // Test 5 : Logout
    echo "=== Test 5 : Déconnexion ===\n";
    $auth->logout();
    echo "Connecté après logout: " . ($auth->check() ? "OUI" : "NON") . "\n";
    
} catch (Exception $e) {
    echo "❌ Auth test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
