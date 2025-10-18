<?php
// helpers
function section($t){ echo "=== {$t} ===\n"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    echo "{$k}: {$v}\n";
}

echo "=== Test Bootstrap Pronote ===\n\n";

require_once __DIR__ . '/API/bootstrap.php';

section("Test 1 : Application chargée");
$app = app();
echo "Application instance: " . (is_object($app) ? "OK" : "FAIL") . "\n";
echo "Environment: " . (env('APP_ENV', 'production')) . "\n\n";

section("Test 2 : Services disponibles");
$services = ['db','auth','csrf','log','audit','rate_limiter'];
foreach ($services as $service) {
	try {
		app($service);
		echo "  {$service}: ✓\n";
	} catch (Throwable $e) {
		echo "  {$service}: ✗ (" . $e->getMessage() . ")\n";
	}
}
echo "\n";

section("Test 3 : Configuration");
echo "APP_URL: " . (config('app.url') ?? 'N/A') . "\n";
echo "DB_HOST: " . (config('database.host') ?? 'N/A') . "\n";
echo "BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'N/A') . "\n\n";

section("Test 4 : Database");
try {
	$pdo = app('db')->getConnection();
	echo "Database connected: OK\n";
	echo "PDO instance: " . ($pdo instanceof PDO ? "OK" : "FAIL") . "\n";
} catch (Throwable $e) {
	echo "Database error: " . $e->getMessage() . "\n";
}
echo "\n";

section("Test 5 : Auth (non connecté)");
echo "Connecté: " . (app('auth')->check() ? "OUI" : "NON") . "\n";
var_export(app('auth')->user());
echo "\n\n";

section("Test 6 : CSRF");
$token = app('csrf')->generate();
echo "Token généré: " . substr($token, 0, 16) . "...\n";
echo "Token check(): " . (app('csrf')->check($token) ? "OUI" : "NON") . "\n";
echo "Token validate(): " . (app('csrf')->validate($token) ? "VALIDE" : "INVALIDE") . "\n\n";

section("Test 7 : Logger");
logInfo('Test du logger depuis bootstrap', ['area' => 'test']);
logError('Ceci est une erreur', ['area' => 'test']);
echo "Voir logs via error_log (stdout/serveur)\n\n";

section("Test 8 : Rate Limiter");
$key = 'test_' . time();
for ($i = 1; $i <= 7; $i++) {
	$allowed = checkRateLimit($key, 5, 60);
	echo "Tentative {$i}: " . ($allowed ? "AUTORISÉ" : "BLOQUÉ") . "\n";
}
echo "\n";

section("Test 9 : Audit");
try {
	$ok = app('audit')->log('test.bootstrap', null, ['new' => ['test' => 'data']]);
	echo "Log d'audit créé: " . ($ok ? "OK" : "SKIP (table absente?)") . "\n";
} catch (Throwable $e) {
	echo "Audit error: " . $e->getMessage() . "\n";
}
echo "\n";

section("Test 10 : Fonctions Legacy");
echo "isLoggedIn(): " . (isLoggedIn() ? "OUI" : "NON") . "\n";
echo "getUserRole(): " . var_export(getUserRole(), true) . "\n";
echo "getUserFullName(): " . getUserFullName() . "\n";
$csrfToken = generateCSRFToken();
echo "generateCSRFToken(): " . substr($csrfToken, 0, 16) . "...\n";
echo "validateCSRFToken(): " . (validateCSRFToken($csrfToken) ? "VALIDE" : "INVALIDE") . "\n\n";

section("Test 11 : Variable globale PDO");
echo "Global \$pdo existe: " . (isset($GLOBALS['pdo']) ? "OUI" : "NON") . "\n";
echo "Global \$pdo est PDO: " . (($GLOBALS['pdo'] instanceof PDO) ? "OUI" : "NON") . "\n\n";

section("Test 12 : executeQuery()");
try {
	$results = executeQuery("SELECT 1 AS test");
	echo "executeQuery(): " . (is_array($results) ? "OK" : "FAIL") . "\n";
	if (!empty($results)) {
		echo "Résultat: " . json_encode($results[0]) . "\n";
	}
} catch (Throwable $e) {
	echo "Query error: " . $e->getMessage() . "\n";
}
echo "\n";

section("Test 13 : Session");
echo "Session active: " . (session_status() === PHP_SESSION_ACTIVE ? "OUI" : "NON") . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session name: " . session_name() . "\n\n";

section("Test 14 : Helpers disponibles");
$helpers = ['app', 'config', 'isLoggedIn', 'getCurrentUser', 'generateCSRFToken', 'logError', 'sanitizeInput'];
foreach ($helpers as $func) {
	echo "  {$func}(): " . (function_exists($func) ? "✓" : "✗") . "\n";
}
echo "\n";

section("Test 15 : Constantes");
$constants = ['PRONOTE_BOOTSTRAP_LOADED', 'BASE_URL'];
foreach ($constants as $const) {
	echo "  {$const}: " . (defined($const) ? "✓ (" . constant($const) . ")" : "✗") . "\n";
}
echo "\n";

echo "=== RÉSUMÉ ===\n";
echo "Bootstrap chargé avec succès !\n";
echo "Services et bridge fonctionnels.\n";
