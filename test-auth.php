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

    // Test 2 : Tentative d'authentification (DB requise)
    echo "=== Test 2 : Tentative d'authentification ===\n";
    $result = $auth->attempt([
        'email' => 'test@example.com',
        'password' => 'password123',
        'type' => 'eleve'
    ]);
    echo "Auth réussie: " . ($result ? "OUI" : "NON") . "\n\n";

    // Test 3 : Simulation de connexion manuelle
    echo "=== Test 3 : Simulation connexion manuelle ===\n";
    $auth->login(1, 'eleve');
    echo "Connecté: " . ($auth->check() ? "OUI" : "NON") . "\n";
    echo "User: " . json_encode($auth->user(), JSON_PRETTY_PRINT) . "\n\n";

    // Test 4 : Logout
    echo "=== Test 4 : Déconnexion ===\n";
    $auth->logout();
    echo "Connecté après logout: " . ($auth->check() ? "OUI" : "NON") . "\n";
    
} catch (Exception $e) {
    echo "❌ Auth test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
