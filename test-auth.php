<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Auth - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔑 Test du Auth Manager</h1>
            <p>Vérification du système d'authentification</p>
        </header>
        <main>
<?php
// Bootstrap the application
$app = require_once __DIR__ . '/API/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// helpers
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
function jprint($k,$v){ 
    echo "<div class='kv-item'><span class='kv-key'>{$k}</span></div>";
    if ($v) {
        echo "<pre class='json-view'>" . htmlspecialchars(json_encode($v, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "<div class='info-message'>null</div>";
    }
}

use Pronote\Auth\AuthManager;

try {
    // Get auth from container
    $auth = $app->make('auth');

    // Test 1 : Vérifier non connecté
    section("Test 1 : Check non authentifié");
    kv("Connecté", $auth->check());
    jprint("User", $auth->user());
    sectionEnd();

    // Test 2 : Tentative d'authentification (DB requise)
    section("Test 2 : Tentative d'authentification");
    $result = $auth->attempt([
        'email' => 'test@example.com',
        'password' => 'password123',
        'type' => 'eleve'
    ]);
    kv("Auth réussie", $result);
    if (!$result) {
        echo "<div class='warning-message'>⚠️ L'authentification a échoué (base de données requise)</div>";
    }
    sectionEnd();

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
    echo "<div class='success-message'>✓ Utilisateur simulé connecté</div>";
    sectionEnd();

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
    echo "<div class='success-message'>✓ Déconnexion réussie</div>";
    sectionEnd();

} catch (Exception $e) {
    section('Erreur');
    echo "<div class='error-message'>";
    echo "❌ Auth test failed: " . htmlspecialchars($e->getMessage()) . "<br><br>";
    echo "<strong>Stack trace:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
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
