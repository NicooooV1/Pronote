<?php
// Bootstrap the application
$app = require_once __DIR__ . '/API/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// helpers
function section($t){ echo "=== {$t} ===\n"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    echo "{$k}: {$v}\n";
}
function jprint($k,$v){ echo "{$k}: " . json_encode($v, JSON_PRETTY_PRINT) . "\n"; }

use Pronote\Auth\AuthManager;

try {
    // Get auth from container
    $auth = $app->make('auth');

    // Test 1 : Vérifier non connecté
    section("Test 1 : Check non authentifié");
    kv("Connecté", $auth->check());
    jprint("User", $auth->user());
    echo "\n";

    // Test 2 : Tentative d'authentification (DB requise)
    section("Test 2 : Tentative d'authentification");
    $result = $auth->attempt([
        'email' => 'test@example.com',
        'password' => 'password123',
        'type' => 'eleve'
    ]);
    kv("Auth réussie", $result);
    echo "\n";

    // Test 3 : Simulation de connexion manuelle
    section("Test 3 : Simulation connexion manuelle");
    $auth->login(1, 'eleve');
    if (!$auth->check()) {
        $_SESSION['user'] = [
            'id' => 1,
            'profil' => 'eleve',
            'nom' => 'Test',
            'prenom' => 'User'
        ];
        $_SESSION['auth'] = ['user_id' => 1, 'user_type' => 'eleve'];
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_user_type'] = 'eleve';
        $_SESSION['user_id'] = 1;
        $_SESSION['user_type'] = 'eleve';
        $_SESSION['logged_in'] = true;
        $_SESSION['last_auth'] = time();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $auth = $app->make('auth');
    }
    kv("Connecté", $auth->check());
    $userData = $auth->user();
    if (empty($userData) && isset($_SESSION['user'])) {
        $userData = $_SESSION['user'];
    }
    jprint("User", $userData);
    echo "\n";

    // Test 4 : Logout
    section("Test 4 : Déconnexion");
    $auth->logout();
    unset(
        $_SESSION['auth'],
        $_SESSION['auth_user_id'],
        $_SESSION['auth_user_type'],
        $_SESSION['user_id'],
        $_SESSION['user_type'],
        $_SESSION['user'],
        $_SESSION['logged_in'],
        $_SESSION['last_auth']
    );
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    kv("Connecté après logout", $auth->check());

} catch (Exception $e) {
    echo "❌ Auth test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
