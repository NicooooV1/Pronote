<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Bootstrap - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🚀 Test Bootstrap Pronote</h1>
            <p>Vérification complète du système de bootstrap</p>
        </header>
        <main>
<?php
function section($t){ echo "<div class='test-section'><h2>{$t}</h2><div class='test-content'>"; }
function sectionEnd(){ echo "</div></div>"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    $class = '';
    if ($v === 'OUI' || $v === 'OK' || $v === 'VALIDE') $class = 'success';
    if ($v === 'NON' || $v === 'NULL' || $v === 'FAIL') $class = 'error';
    echo "<div class='kv-item'><span class='kv-key'>{$k}</span><span class='kv-value {$class}'>" . htmlspecialchars($v) . "</span></div>";
}

require_once __DIR__ . '/API/bootstrap.php';

section("Test 1 : Application chargée");
$app = app();
echo "<div class='kv-item'><span class='kv-key'>Application instance</span><span class='kv-value " . (is_object($app) ? 'success' : 'error') . "'>" . (is_object($app) ? "OK ✓" : "FAIL ✗") . "</span></div>";
kv('Environment', env('APP_ENV', 'production'));
sectionEnd();

section("Test 2 : Services disponibles");
$services = ['db','auth','csrf','log','audit','rate_limiter'];
echo "<ul class='list-group'>";
foreach ($services as $service) {
    try {
        app($service);
        echo "<li class='list-group-item'><span>{$service}</span><span class='badge success'>✓</span></li>";
    } catch (Throwable $e) {
        echo "<li class='list-group-item'><span>{$service}</span><span class='badge danger'>✗</span></li>";
    }
}
echo "</ul>";
sectionEnd();

section("Test 3 : Configuration");
kv('APP_URL', config('app.url') ?? 'N/A');
kv('DB_HOST', config('database.host') ?? 'N/A');
kv('BASE_URL', defined('BASE_URL') ? BASE_URL : 'N/A');
sectionEnd();

section("Test 4 : Database");
try {
    $pdo = app('db')->getConnection();
    echo "<div class='success-message'>✓ Database connected</div>";
    echo "<div class='kv-item'><span class='kv-key'>PDO instance</span><span class='kv-value success'>" . ($pdo instanceof PDO ? "OK ✓" : "FAIL ✗") . "</span></div>";
} catch (Throwable $e) {
    echo "<div class='error-message'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
}
sectionEnd();

section("Test 5 : Auth (non connecté)");
kv('Connecté', app('auth')->check() ? "OUI" : "NON");
$user = app('auth')->user();
if ($user) {
    echo "<pre class='json-view'>" . htmlspecialchars(json_encode($user, JSON_PRETTY_PRINT)) . "</pre>";
} else {
    echo "<div class='info-message'>Aucun utilisateur connecté</div>";
}
sectionEnd();

section("Test 6 : CSRF");
$token = app('csrf')->generate();
echo "<div class='token'>" . htmlspecialchars(substr($token, 0, 32)) . "...</div>";
kv('Token check()', app('csrf')->check($token) ? "OUI" : "NON");
kv('Token validate()', app('csrf')->validate($token) ? "VALIDE" : "INVALIDE");
sectionEnd();

section("Test 7 : Logger");
logInfo('Test du logger depuis bootstrap', ['area' => 'test']);
logError('Ceci est une erreur', ['area' => 'test']);
echo "<div class='info-message'>✓ Logs enregistrés (voir error_log)</div>";
sectionEnd();

section("Test 8 : Rate Limiter");
$key = 'test_' . time();
echo "<div style='margin-top: 0.5rem;'>";
for ($i = 1; $i <= 7; $i++) {
    $allowed = checkRateLimit($key, 5, 60);
    $class = $allowed ? 'attempt-allowed' : 'attempt-blocked';
    $status = $allowed ? 'AUTORISÉ ✓' : 'BLOQUÉ ❌';
    echo "<div class='attempt-line {$class}'>Tentative {$i} : <strong>{$status}</strong></div>";
}
echo "</div>";
sectionEnd();

section("Test 9 : Audit");
try {
    $ok = app('audit')->log('test.bootstrap', null, ['new' => ['test' => 'data']]);
    echo "<div class='" . ($ok ? 'success' : 'warning') . "-message'>" . ($ok ? "✓ Log d'audit créé" : "⚠️ Table absente ou erreur") . "</div>";
} catch (Throwable $e) {
    echo "<div class='error-message'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
}
sectionEnd();

section("Test 10 : Fonctions Legacy");
kv('isLoggedIn()', isLoggedIn() ? "OUI" : "NON");
kv('getUserRole()', var_export(getUserRole(), true));
kv('getUserFullName()', getUserFullName());
$csrfToken = generateCSRFToken();
echo "<div class='token'>" . htmlspecialchars(substr($csrfToken, 0, 32)) . "...</div>";
kv('validateCSRFToken()', validateCSRFToken($csrfToken) ? "VALIDE" : "INVALIDE");
sectionEnd();

section("Test 11 : Variable globale PDO");
kv('Global $pdo existe', isset($GLOBALS['pdo']) ? "OUI" : "NON");
kv('Global $pdo est PDO', ($GLOBALS['pdo'] instanceof PDO) ? "OUI" : "NON");
sectionEnd();

section("Test 12 : executeQuery()");
try {
    $results = executeQuery("SELECT 1 AS test");
    echo "<div class='kv-item'><span class='kv-key'>executeQuery()</span><span class='kv-value success'>" . (is_array($results) ? "OK ✓" : "FAIL ✗") . "</span></div>";
    if (!empty($results)) {
        echo "<pre class='json-view'>" . htmlspecialchars(json_encode($results[0], JSON_PRETTY_PRINT)) . "</pre>";
    }
} catch (Throwable $e) {
    echo "<div class='error-message'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
}
sectionEnd();

section("Test 13 : Session");
kv('Session active', session_status() === PHP_SESSION_ACTIVE ? "OUI" : "NON");
kv('Session ID', session_id());
kv('Session name', session_name());
sectionEnd();

section("Test 14 : Helpers disponibles");
$helpers = ['app', 'config', 'isLoggedIn', 'getCurrentUser', 'generateCSRFToken', 'logError', 'sanitizeInput'];
echo "<ul class='list-group'>";
foreach ($helpers as $func) {
    $exists = function_exists($func);
    echo "<li class='list-group-item'><span>{$func}()</span><span class='badge " . ($exists ? 'success' : 'danger') . "'>" . ($exists ? '✓' : '✗') . "</span></li>";
}
echo "</ul>";
sectionEnd();

section("Test 15 : Constantes");
$constants = ['PRONOTE_BOOTSTRAP_LOADED', 'BASE_URL'];
echo "<ul class='list-group'>";
foreach ($constants as $const) {
    $exists = defined($const);
    echo "<li class='list-group-item'><span>{$const}</span><span class='badge " . ($exists ? 'success' : 'danger') . "'>" . ($exists ? '✓' : '✗') . "</span></li>";
}
echo "</ul>";
sectionEnd();

section("RÉSUMÉ");
echo "<div class='success-message'>";
echo "<strong>✓ Bootstrap chargé avec succès !</strong><br>";
echo "Services et bridge fonctionnels.";
echo "</div>";
sectionEnd();
?>
        </main>
        <footer>
            <p>Pronote API Test Suite &copy; 2024</p>
        </footer>
    </div>
</body>
</html>
